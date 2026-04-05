<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Abschreibungsgruende fuer Warenabschriften.
 *
 * Standard-Gruende (is_default=true, tenant_id=null) sind fuer alle Tenants sichtbar.
 * Tenants koennen eigene Gruende erstellen (is_default=false, tenant_id gesetzt),
 * die nur fuer sie sichtbar sind.
 */
class DepreciationReason extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'status',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    // -- Relationships --

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    // -- Scopes --

    /** Nur aktive Gruende */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /** Standard-Gruende (fuer alle Tenants) */
    public function scopeDefaults($query)
    {
        return $query->where('is_default', true);
    }

    /** Gruende die ein bestimmter Tenant sehen darf: Standard + eigene */
    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where(function ($q) use ($tenantId) {
            $q->where('is_default', true)
              ->orWhere('tenant_id', $tenantId);
        });
    }
}
