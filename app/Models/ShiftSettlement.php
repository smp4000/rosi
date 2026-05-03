<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Schichtabrechnung / Tagesabrechnung.
 *
 * Wird beim Schichtbeginn erstellt und beim Schichtende abgeschlossen.
 * Enthaelt Muenzrollen, Zaehlerstaende, Tresor-Einlagen, Warenruecknahmen.
 */
class ShiftSettlement extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant, Auditable;

    protected $fillable = [
        'tenant_id',
        'gas_station_id',
        'user_id',
        'shift_id',
        'status',
        'started_at',
        'ended_at',
        'cash_remaining',
        'safe_total',
        'cash_report_soll',
        'cash_difference',
        'cash_report_photo',
        'notes',
        'signature',
    ];

    protected array $auditExclude = ['signature'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'cash_remaining' => 'decimal:2',
            'safe_total' => 'decimal:2',
            'cash_report_soll' => 'decimal:2',
            'cash_difference' => 'decimal:2',
        ];
    }

    // --- Beziehungen ---

    /** Tankstelle */
    public function gasStation(): BelongsTo
    {
        return $this->belongsTo(GasStation::class);
    }

    /** Mitarbeiter der die Schicht macht */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Optionale Verknuepfung zur Schichtplanung */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /** Prueffragen-Antworten */
    public function checkAnswers(): HasMany
    {
        return $this->hasMany(ShiftSettlementCheckAnswer::class);
    }

    /** Muenzrollen-Bestand */
    public function coinRolls(): HasMany
    {
        return $this->hasMany(ShiftSettlementCoinRoll::class);
    }

    /** Zaehlerstaende (Waschanlage, Kaffee, etc.) */
    public function counters(): HasMany
    {
        return $this->hasMany(ShiftSettlementCounter::class);
    }

    /** Tresor-Einlagen / Safebags */
    public function safeDeposits(): HasMany
    {
        return $this->hasMany(ShiftSettlementSafeDeposit::class);
    }

    /** Warenruecknahmen / Stornos */
    public function returns(): HasMany
    {
        return $this->hasMany(ShiftSettlementReturn::class);
    }

    /** Nachtraegliche Kommentare */
    public function comments(): HasMany
    {
        return $this->hasMany(ShiftSettlementComment::class);
    }

    // --- Status-Helfer ---

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Schicht abschliessen.
     * Berechnet automatisch safe_total und cash_difference.
     */
    public function complete(): void
    {
        // Tresor-Einlagen summieren (nur zur Dokumentation)
        $safeTotal = $this->safeDeposits()->sum('amount');

        // Kassendifferenz: IST - SOLL
        // Tresor-Einlagen sind bereits im Kassensystem als Ausgabe verbucht,
        // der SOLL-Wert beruecksichtigt sie schon → nicht nochmal abziehen.
        $difference = (float) $this->cash_remaining - (float) $this->cash_report_soll;

        $this->update([
            'status' => 'completed',
            'ended_at' => now(),
            'safe_total' => $safeTotal,
            'cash_difference' => $difference,
        ]);
    }

    // --- Accessors ---

    /** Formattierter Gesamtbetrag Tresor */
    public function getFormattedSafeTotalAttribute(): string
    {
        return number_format((float) $this->safe_total, 2, ',', '.') . ' €';
    }

    /** Formattierte Kassendifferenz */
    public function getFormattedCashDifferenceAttribute(): string
    {
        $diff = (float) $this->cash_difference;
        $prefix = $diff > 0 ? '+' : '';

        return $prefix . number_format($diff, 2, ',', '.') . ' €';
    }

    /** Status-Label auf Deutsch */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'active' => 'Aktiv',
            'completed' => 'Abgeschlossen',
            'cancelled' => 'Abgebrochen',
            default => $this->status,
        };
    }
}
