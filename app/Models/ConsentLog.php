<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DSGVO Einwilligungs-Log Model.
 * Protokolliert alle Einwilligungen und Widerrufe.
 * Typen: privacy_policy, terms, data_processing, cookies, marketing
 */
class ConsentLog extends Model
{
    use HasFactory;

    /**
     * Kein updated_at Feld.
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'type',
        'version',
        'accepted_at',
        'revoked_at',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    // --- Beziehungen ---

    /**
     * Benutzer der zugestimmt hat.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mandant (optional).
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // --- Hilfsmethoden ---

    /**
     * Ist die Einwilligung widerrufen?
     */
    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * Ist die Einwilligung noch aktiv?
     */
    public function isActive(): bool
    {
        return ! $this->isRevoked();
    }

    /**
     * Einwilligung widerrufen.
     */
    public function revoke(): void
    {
        $this->update(['revoked_at' => now()]);
    }

    // --- Factory-Methode ---

    /**
     * Neue Einwilligung protokollieren.
     */
    public static function recordConsent(
        string $userId,
        string $type,
        string $version,
        ?string $tenantId = null,
    ): static {
        return static::create([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'type' => $type,
            'version' => $version,
            'accepted_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
