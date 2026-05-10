<?php

namespace App\Models\Kiosk;

use App\Models\GasStation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot-Tabelle Lieferant <-> Tankstelle mit Kundennummer.
 */
class SupplierStation extends Pivot
{
    use HasUuids;

    protected $table = 'newspaper_supplier_stations';

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
