<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Artikelgruppen-Hierarchie (Aral/BP Standard).
 * System-Eintraege (tenant_id=NULL) + Partner-eigene Gruppen.
 */
class ArticleGroup extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'business_area_id',
        'business_area',
        'revenue_account_id',
        'revenue_account',
        'product_group_id',
        'product_group',
        'article_group_04_id',
        'article_group_04',
        'article_group_03_id',
        'article_group_03',
        'article_group_02_01_id',
        'article_group_02_01',
        'vat_rate',
        'vat_category',
        'youth_protection',
        'lease_rate',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'vat_rate' => 'decimal:2',
            'lease_rate' => 'decimal:2',
            'is_system' => 'boolean',
        ];
    }

    // ── Relationships ──

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    // ── Scopes ──

    /**
     * System-Eintraege (tenant_id=NULL) + eigene Tenant-Eintraege.
     */
    public function scopeForTenant($query, ?string $tenantId)
    {
        return $query->where(function ($q) use ($tenantId) {
            $q->whereNull('tenant_id');
            if ($tenantId) {
                $q->orWhere('tenant_id', $tenantId);
            }
        });
    }

    /**
     * Nach Geschaeftsbereich filtern.
     */
    public function scopeBusinessArea($query, int $businessAreaId)
    {
        return $query->where('business_area_id', $businessAreaId);
    }

    // ── Helpers ──

    /**
     * Optionen fuer Select-Felder (article_group_04 als Label).
     */
    public static function getOptions(?string $tenantId = null): array
    {
        $query = static::query();
        if ($tenantId) {
            $query->forTenant($tenantId);
        }

        return $query
            ->orderBy('business_area_id')
            ->orderBy('revenue_account_id')
            ->orderBy('article_group_04_id')
            ->get()
            ->mapWithKeys(fn ($g) => [$g->id => "{$g->business_area} > {$g->article_group_04}"])
            ->toArray();
    }

    /**
     * Geschaeftsbereiche als Filter-Optionen.
     */
    public static function getBusinessAreaOptions(): array
    {
        return static::query()
            ->whereNotNull('business_area_id')
            ->distinct()
            ->orderBy('business_area_id')
            ->pluck('business_area', 'business_area_id')
            ->toArray();
    }
}
