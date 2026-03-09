<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * DSGVO Audit-Log Model.
 * Protokolliert alle datenschutzrelevanten Aktionen.
 * Alte/Neue Werte werden verschluesselt gespeichert.
 *
 * Hinweis: KEIN BelongsToTenant-Trait, da tenant_id nullable ist
 * und Super-Admin-Aktionen ohne Mandant protokolliert werden.
 */
class AuditLog extends Model
{
    use HasFactory;

    /**
     * Kein updated_at Feld - Audit-Logs werden nie geaendert.
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'user_type',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'encrypted:array',
            'new_values' => 'encrypted:array',
        ];
    }

    // --- Beziehungen ---

    /**
     * Mandant (optional - NULL bei Super-Admin-Aktionen).
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Handelnder Benutzer.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Betroffenes Model (polymorph).
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    // --- Scopes ---

    /**
     * Logs nach Aktion filtern.
     */
    public function scopeForAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Logs nach Mandant filtern.
     */
    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Logs nach Benutzer filtern.
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Logs nach betroffenes Model filtern.
     */
    public function scopeForAuditable($query, string $type, string $id)
    {
        return $query->where('auditable_type', $type)
            ->where('auditable_id', $id);
    }

    // --- Factory-Methode ---

    /**
     * Neuen Audit-Eintrag erstellen.
     * Wird vom AuditService aufgerufen.
     */
    public static function log(
        string $action,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
    ): static {
        $user = auth()->user();

        return static::create([
            'tenant_id' => session('tenant_id'),
            'user_id' => $user?->id,
            'user_type' => $user?->type,
            'action' => $action,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'reason' => $reason,
        ]);
    }
}
