<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bankkonto einer Tankstelle.
 * Jede Tankstelle kann mehrere Bankkonten haben (Geschaefts-, Agentur-, Lottokonto).
 */
class GasStationBankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'gas_station_id',
        'iban',
        'bank_name',
        'bic',
        'description',
        'account_type',
    ];

    /**
     * Sensible Felder verschluesselt speichern (DSGVO).
     */
    protected function casts(): array
    {
        return [
            'iban' => 'encrypted',
            'bank_name' => 'encrypted',
            'bic' => 'encrypted',
            'description' => 'encrypted',
        ];
    }

    /**
     * Zugehoerige Tankstelle.
     */
    public function gasStation(): BelongsTo
    {
        return $this->belongsTo(GasStation::class);
    }
}
