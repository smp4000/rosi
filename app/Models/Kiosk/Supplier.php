<?php

namespace App\Models\Kiosk;

use App\Models\GasStation;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lieferant fuer Zeitungen (z.B. PVG).
 * Pro Tankstelle gibt es eine eigene Kundennummer (Pivot).
 */
class Supplier extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'newspaper_suppliers';

    protected $fillable = [
        'tenant_id', 'name', 'short_code', 'vat_id',
        'email', 'phone', 'address', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function stations(): BelongsToMany
    {
        return $this->belongsToMany(GasStation::class, 'newspaper_supplier_stations')
            ->using(SupplierStation::class)
            ->withPivot(['id', 'kundennummer', 'created_at', 'updated_at'])
            ->withTimestamps();
    }

    public function supplierStations(): HasMany
    {
        return $this->hasMany(SupplierStation::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'supplier_id');
    }
}
