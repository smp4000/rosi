<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Einladungen-Model fuer Mitarbeiter-Registrierung.
 * Token-basierte Einladungen mit Ablaufdatum und Rollenzuweisung.
 */
class Invitation extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'email',
        'token',
        'type',
        'role_id',
        'gas_station_ids',
        'invited_by',
        'message',
        'expires_at',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'gas_station_ids' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    // --- Beziehungen ---

    /**
     * Einladender Benutzer (Partner/Manager).
     */
    public function invitedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    // --- Hilfsmethoden ---

    /**
     * Ist die Einladung noch gueltig?
     */
    public function isValid(): bool
    {
        return ! $this->isExpired() && ! $this->isAccepted();
    }

    /**
     * Ist die Einladung abgelaufen?
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Wurde die Einladung bereits angenommen?
     */
    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    /**
     * Einladung als angenommen markieren.
     */
    public function markAsAccepted(): void
    {
        $this->update(['accepted_at' => now()]);
    }

    /**
     * Neuen Einladungs-Token generieren.
     */
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32)); // 64 Zeichen hex
    }
}
