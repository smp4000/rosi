<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Warenruecknahme-Gruende fuer Schichtabrechnungen.
 *
 * Global (tenant_id=null) fuer alle Tenants sichtbar.
 * Tenants koennen eigene Gruende erstellen (tenant_id gesetzt).
 */
class ShiftReturnReason extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'label',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
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
        return $query->where('is_active', true);
    }

    /** Gruende die ein bestimmter Tenant sehen darf: global + eigene */
    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where(function ($q) use ($tenantId) {
            $q->whereNull('tenant_id')
              ->orWhere('tenant_id', $tenantId);
        });
    }
}
