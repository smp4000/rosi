<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Schichtvorlagen-Model.
 * Wiederverwendbare Schichtdefinitionen (Frueh-, Spaet-, Nachtschicht, etc.).
 */
class ShiftTemplate extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'gas_station_id',
        'name',
        'start_time',
        'end_time',
        'break_minutes',
        'shift_type',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'break_minutes' => 'integer',
        ];
    }

    // --- Beziehungen ---

    /**
     * Zugehoerige Tankstelle (optional, wenn stationsspezifisch).
     */
    public function gasStation(): BelongsTo
    {
        return $this->belongsTo(GasStation::class);
    }

    // --- Hilfsmethoden ---

    /**
     * Dauer der Schicht in Stunden (ohne Pause).
     */
    public function getDurationHoursAttribute(): float
    {
        $start = \Carbon\Carbon::parse($this->start_time);
        $end = \Carbon\Carbon::parse($this->end_time);

        // Nachtschicht: Ende am naechsten Tag
        if ($end->lt($start)) {
            $end->addDay();
        }

        $totalMinutes = $start->diffInMinutes($end) - $this->break_minutes;

        return round($totalMinutes / 60, 2);
    }
}
