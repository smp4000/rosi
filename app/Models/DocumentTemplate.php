<?php

namespace App\Models;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dokumentenvorlagen-Model.
 * Globale Systemvorlagen (tenant_id = NULL) und mandantenspezifische Vorlagen.
 *
 * Hinweis: KEIN BelongsToTenant-Trait, da tenant_id nullable ist (globale Vorlagen).
 * Stattdessen eigener Scope der sowohl globale als auch mandantenspezifische Vorlagen zeigt.
 */
class DocumentTemplate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'type',
        'category',
        'name',
        'description',
        'content',
        'variables',
        'header_html',
        'footer_html',
        'is_default',
        'is_active',
        'is_onboarding',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'is_onboarding' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // --- Beziehungen ---

    /**
     * Mandant (nullable = globale Systemvorlage).
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Dokumente die aus dieser Vorlage erstellt wurden.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'template_id');
    }

    // --- Scopes ---

    /**
     * Vorlagen fuer den aktuellen Mandanten + globale Vorlagen.
     * Zeigt sowohl mandantenspezifische als auch systemweite Vorlagen.
     */
    public function scopeForCurrentTenant($query)
    {
        $tenantId = session('tenant_id');

        return $query->where(function ($q) use ($tenantId) {
            $q->whereNull('tenant_id'); // Globale Vorlagen
            if ($tenantId) {
                $q->orWhere('tenant_id', $tenantId); // Mandanten-Vorlagen
            }
        });
    }

    /**
     * Nur aktive Vorlagen.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Vorlagen nach Typ filtern.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Vorlagen nach Kategorie filtern.
     */
    public function scopeOfCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    // --- Hilfsmethoden ---

    /**
     * Ist dies eine globale Systemvorlage?
     */
    public function isGlobal(): bool
    {
        return $this->tenant_id === null;
    }
}
