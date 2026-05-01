<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Konfigurierbare Prueffrage fuer Schichtabrechnungen.
 * Wird im Admin-Panel verwaltet.
 *
 * Beispiele:
 * - "Haben Sie Waren ohne Kassenbon angetroffen?" (yes_no)
 * - "Traegt der Kassierer Aral-Kleidung?" (yes_no_comment)
 */
class ShiftSettlementCheckQuestion extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'gas_station_id',
        'question_text',
        'question_type',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    // --- Beziehungen ---

    /** Station (null = gilt fuer alle) */
    public function gasStation(): BelongsTo
    {
        return $this->belongsTo(GasStation::class);
    }

    /** Antworten auf diese Frage */
    public function answers(): HasMany
    {
        return $this->hasMany(ShiftSettlementCheckAnswer::class, 'question_id');
    }

    // --- Scopes ---

    /** Nur aktive Fragen */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Fragen fuer eine bestimmte Station.
     * Gibt station-spezifische UND globale (station_id = null) Fragen zurueck.
     */
    public function scopeForStation($query, string $stationId)
    {
        return $query->where(function ($q) use ($stationId) {
            $q->where('gas_station_id', $stationId)
                ->orWhereNull('gas_station_id');
        })->orderBy('sort_order');
    }

    // --- Accessors ---

    /** Fragetyp-Label auf Deutsch */
    public function getQuestionTypeLabelAttribute(): string
    {
        return match ($this->question_type) {
            'yes_no' => 'Ja / Nein',
            'yes_no_comment' => 'Ja / Nein + Kommentar',
            'text' => 'Freitext',
            'number' => 'Zahl',
            default => $this->question_type,
        };
    }
}
