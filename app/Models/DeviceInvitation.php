<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Einladung fuer die POS-App.
 * Partner laedt Mitarbeiter ein → MA klickt Link → Geraet wird registriert.
 */
class DeviceInvitation extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'station_id',
        'user_id',
        'invited_by',
        'token',
        'channel',
        'status',
        'expires_at',
        'accepted_at',
        'device_id',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    // ── Beziehungen ──────────────────────────────────────────

    /** Fuer welche Tankstelle? */
    public function station(): BelongsTo
    {
        return $this->belongsTo(GasStation::class, 'station_id');
    }

    /** Fuer welchen Mitarbeiter? */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Wer hat eingeladen? */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /** Welches Geraet wurde registriert? */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    // ── Hilfsmethoden ────────────────────────────────────────

    /** Ist die Einladung noch gueltig? */
    public function isValid(): bool
    {
        return $this->status === 'pending'
            && $this->expires_at->isFuture();
    }

    /** Einladung als angenommen markieren */
    public function markAccepted(Device $device): void
    {
        $this->update([
            'status' => 'accepted',
            'accepted_at' => now(),
            'device_id' => $device->id,
        ]);
    }

    /** Neuen zufaelligen Token generieren */
    public static function generateToken(): string
    {
        return Str::random(64);
    }
}
