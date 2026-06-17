<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * "ROSI Print"-Tray-App auf einem Stations-PC. Holt Druckjobs der Station
 * ueber die Queue und druckt sie an den lokalen DYMO (localhost).
 */
class PrintAgent extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'station_id',
        'install_id',
        'name',
        'hostname',
        'token_hash',
        'printers',
        'app_version',
        'last_seen_at',
        'is_active',
        'is_default',
        'status',
        'claim_secret',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_BLOCKED = 'blocked';

    protected function casts(): array
    {
        return [
            'printers' => 'array',
            'last_seen_at' => 'datetime',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    protected $hidden = ['token_hash', 'claim_secret'];

    /** Agent anhand der Maschinen-GUID finden (ein PC = ein Agent). */
    public static function findByInstallId(?string $installId): ?self
    {
        return empty($installId) ? null : static::where('install_id', $installId)->first();
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(GasStation::class, 'station_id');
    }

    // ── Token ────────────────────────────────────────

    /** Klartext-Token -> Speicherhash (sha256 fuer O(1)-Lookup). */
    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /** Neues Token erzeugen, Hash setzen, Klartext zurueckgeben (nur einmalig sichtbar). */
    public function generateToken(): string
    {
        $plain = 'rpa_' . Str::random(48);
        $this->token_hash = self::hashToken($plain);

        return $plain;
    }

    /** Aktiven Agent anhand des Klartext-Tokens finden. */
    public static function findByToken(?string $plainToken): ?self
    {
        if (empty($plainToken)) {
            return null;
        }

        return static::where('token_hash', self::hashToken($plainToken))
            ->where('is_active', true)
            ->first();
    }

    public function online(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subMinutes(2));
    }
}
