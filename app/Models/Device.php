<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Registriertes Geraet fuer die POS-App.
 * Kann ein Zebra MDE (Firmengeraet) oder ein persoenliches Handy sein.
 */
class Device extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'station_id',
        'user_id',
        'device_type',
        'device_name',
        'device_os',
        'app_version',
        'device_token_hash',
        'is_active',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    // ── Beziehungen ──────────────────────────────────────────

    /** Zu welcher Tankstelle gehoert das Geraet? */
    public function station(): BelongsTo
    {
        return $this->belongsTo(GasStation::class, 'station_id');
    }

    /** Welcher Mitarbeiter nutzt das Geraet? (nur bei personal) */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Einladungen die zu diesem Geraet fuehrten */
    public function invitations(): HasMany
    {
        return $this->hasMany(DeviceInvitation::class);
    }

    // ── Hilfsmethoden ────────────────────────────────────────

    /** Ist das Geraet aktiv und darf die API nutzen? */
    public function isAllowed(): bool
    {
        return $this->is_active && ! $this->trashed();
    }

    /** Letzten Kontakt aktualisieren */
    public function touch_last_seen(): void
    {
        $this->update(['last_seen_at' => now()]);
    }
}
