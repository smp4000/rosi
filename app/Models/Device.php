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
        'print_default',
        'print_alternatives',
        'is_active',
        'approval_status',
        'registration_distance_m',
        'registration_latitude',
        'registration_longitude',
        'last_seen_at',
    ];

    // ── Freigabe-Status ──────────────────────────────────────
    public const APPROVAL_ACTIVE = 'active';     // Darf sofort arbeiten
    public const APPROVAL_PENDING = 'pending';   // Wartet auf Freigabe (GPS-Abweichung)
    public const APPROVAL_REJECTED = 'rejected'; // Abgelehnt

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'print_alternatives' => 'array',
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

    /**
     * Geraet anhand des Klartext-Tokens finden (Token ist gehasht gespeichert).
     * Liefert nur Geraete, die die API nutzen duerfen (aktiv + freigegeben).
     */
    public static function findByPlainToken(?string $plainToken): ?self
    {
        if (empty($plainToken)) {
            return null;
        }

        $devices = self::where('is_active', true)
            ->where('approval_status', self::APPROVAL_ACTIVE)
            ->get();

        foreach ($devices as $device) {
            if (\Illuminate\Support\Facades\Hash::check($plainToken, $device->device_token_hash)) {
                return $device;
            }
        }

        return null;
    }

    /** Ist das Geraet aktiv und darf die API nutzen? */
    public function isAllowed(): bool
    {
        return $this->is_active
            && $this->approval_status === self::APPROVAL_ACTIVE
            && ! $this->trashed();
    }

    /** Wartet das Geraet noch auf Freigabe durch den Partner? */
    public function isPending(): bool
    {
        return $this->approval_status === self::APPROVAL_PENDING;
    }

    /** Letzten Kontakt aktualisieren */
    public function touch_last_seen(): void
    {
        $this->update(['last_seen_at' => now()]);
    }
}
