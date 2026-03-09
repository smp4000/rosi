<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Mandanten-Model (Tankstellenpartner).
 * Jeder Partner ist ein Mandant mit eigenen Daten.
 * Auditable: Alle Aenderungen werden im Audit-Log protokolliert.
 */
class Tenant extends Model
{
    use HasFactory, HasUuids, SoftDeletes, Auditable;

    protected $fillable = [
        'name',
        'slug',
        'owner_id',
        'email',
        'phone',
        'street',
        'zip',
        'city',
        'country',
        'logo',
        'tax_id',
        'trade_register',
        'trial_ends_at',
        'subscription_status',
        'subscription_plan',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'trial_ends_at' => 'datetime',
    ];

    // --- Beziehungen ---

    /**
     * Inhaber/Ersteller des Mandanten.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Alle Benutzer dieses Mandanten.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Alle Tankstellen dieses Mandanten.
     */
    public function gasStations(): HasMany
    {
        return $this->hasMany(GasStation::class);
    }

    /**
     * Alle Kunden dieses Mandanten.
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Alle Lieferanten dieses Mandanten.
     */
    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    /**
     * Alle Dokumente dieses Mandanten.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Alle Einladungen dieses Mandanten.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    // --- Hilfsmethoden ---

    /**
     * Pruefen ob die Testphase aktiv ist.
     */
    public function isOnTrial(): bool
    {
        return $this->subscription_status === 'trial'
            && $this->trial_ends_at
            && $this->trial_ends_at->isFuture();
    }

    /**
     * Pruefen ob die Testphase abgelaufen ist.
     */
    public function isTrialExpired(): bool
    {
        return $this->subscription_status === 'trial'
            && $this->trial_ends_at
            && $this->trial_ends_at->isPast();
    }

    /**
     * Pruefen ob ein aktives Abonnement besteht.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscription_status === 'active';
    }

    /**
     * Pruefen ob der Mandant Zugang zum System hat (Trial oder Abo).
     */
    public function hasAccess(): bool
    {
        return $this->is_active && ($this->isOnTrial() || $this->hasActiveSubscription());
    }

    /**
     * Slug automatisch generieren aus dem Namen.
     */
    public static function generateSlug(string $name): string
    {
        $slug = \Illuminate\Support\Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        // Eindeutigkeit sicherstellen
        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
