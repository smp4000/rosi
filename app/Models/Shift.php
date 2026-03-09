<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Schicht-Model fuer den Dienstplan.
 * Verwaltet Schichten pro Mitarbeiter und Tankstelle.
 */
class Shift extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'gas_station_id',
        'user_id',
        // Schicht
        'date',
        'start_time',
        'end_time',
        'break_minutes',
        'shift_type',
        // Status
        'status',
        'notes',
        'swap_requested_by',
        'swap_target_user_id',
        'confirmed_by',
        'actual_start',
        'actual_end',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'break_minutes' => 'integer',
            'actual_start' => 'datetime:H:i',
            'actual_end' => 'datetime:H:i',
        ];
    }

    // --- Beziehungen ---

    /**
     * Tankstelle der Schicht.
     */
    public function gasStation(): BelongsTo
    {
        return $this->belongsTo(GasStation::class);
    }

    /**
     * Zugewiesener Mitarbeiter.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mitarbeiter der den Tausch angefragt hat.
     */
    public function swapRequestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'swap_requested_by');
    }

    /**
     * Ziel-Mitarbeiter des Tauschs.
     */
    public function swapTargetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'swap_target_user_id');
    }

    /**
     * Bestaetigender Benutzer (Partner/Manager).
     */
    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * Ersteller der Schicht.
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // --- Hilfsmethoden ---

    /**
     * Geplante Arbeitszeit in Stunden (ohne Pause).
     */
    public function getPlannedHoursAttribute(): float
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

    /**
     * Ist ein Schichttausch angefragt?
     */
    public function isSwapRequested(): bool
    {
        return $this->status === 'swap_requested';
    }

    /**
     * Ist die Schicht abgeschlossen?
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Ist die Schicht storniert?
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
