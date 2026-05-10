<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot Lieferant <-> Tankstelle mit Kundennummer.
 * Allgemein nutzbar (Zeitungen, Bistro, etc.).
 */
class SupplierStation extends Pivot
{
    use HasUuids;

    protected $table = 'supplier_stations';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = true;

    protected $fillable = [
        'supplier_id', 'gas_station_id', 'kundennummer',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function gasStation(): BelongsTo
    {
        return $this->belongsTo(GasStation::class);
    }
}
