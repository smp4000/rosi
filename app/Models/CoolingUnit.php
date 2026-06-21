<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kuehlmoebel mit Mobile-Alerts-Sensor und Soll-Temperaturbereich.
 */
class CoolingUnit extends Model
{
    use HasUuids, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'station_id', 'name', 'device_number', 'type', 'category', 'preset',
        'device_id', 'sensor_type', 'channel', 'phoneid',
        'target_min', 'target_max', 'track_humidity', 'humidity_min', 'humidity_max',
        'offline_after_min',
        'last_reading_at', 'last_value', 'last_humidity', 'last_lowbat', 'last_status',
        'is_active', 'sort',
    ];

    /**
     * Grenzwert-Presets nach ARAL HQM 7.0 (Maßnahmenkatalog Temperaturkontrolle).
     * key => [label, type, min, max]. min/max null = einseitige Grenze.
     */
    public const PRESETS = [
        'hackfleisch' => ['Kühlmöbel/-raum rohe Hackfleischprodukte', 'fridge', null, 2.0],
        'gefluegel' => ['Kühlmöbel/-raum rohes Frischgeflügel', 'fridge', null, 4.0],
        'kuehl_allg' => ['Kühlmöbel/-raum (allgemein)', 'fridge', null, 7.0],
        'tiefkuehl' => ['Tiefkühlmöbel/-raum', 'freezer', null, -18.0],
        'crushed_ice' => ['Crushed-Ice-Truhe', 'ice', -8.0, -5.0],
        'heisshalter' => ['Heißhaltegerät', 'heat_hold', 65.0, null],
        'fritteuse' => ['Fritteuse', 'fryer', null, 175.0],
        'kaffee_milch' => ['Heiße Milch (Kaffeemaschine)', 'heat_other', 60.0, 72.0],
    ];

    public const TYPE_LABELS = [
        'fridge' => 'Kühlmöbel',
        'freezer' => 'Tiefkühlmöbel',
        'counter' => 'Kühltheke',
        'cold_room' => 'Kühlraum',
        'ice' => 'Eis-Truhe',
        'heat_hold' => 'Heißhaltegerät',
        'fryer' => 'Fritteuse',
        'heat_other' => 'Heißgetränk/-gerät',
        'other' => 'Sonstiges',
    ];

    protected function casts(): array
    {
        return [
            'target_min' => 'decimal:2',
            'target_max' => 'decimal:2',
            'humidity_min' => 'decimal:2',
            'humidity_max' => 'decimal:2',
            'last_value' => 'decimal:2',
            'last_humidity' => 'decimal:2',
            'track_humidity' => 'boolean',
            'last_lowbat' => 'boolean',
            'is_active' => 'boolean',
            'last_reading_at' => 'datetime',
        ];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(GasStation::class, 'station_id');
    }

    public function readings(): HasMany
    {
        return $this->hasMany(CoolingReading::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(CoolingAlert::class);
    }

    public function activeAlerts(): HasMany
    {
        return $this->alerts()->where('status', 'active');
    }

    /**
     * Bewertet einen Temperaturwert gegen den Soll-Bereich.
     * @return string ok|high|low
     */
    public function evaluate(float $value): string
    {
        if ($this->target_max !== null && $value > (float) $this->target_max) {
            return 'high';
        }
        if ($this->target_min !== null && $value < (float) $this->target_min) {
            return 'low';
        }

        return 'ok';
    }
}
