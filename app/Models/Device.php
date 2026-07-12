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
        'device_token_lookup',
        'push_token',
        'push_token_updated_at',
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

    // ─────────────────────────────────────────────────────────────────────
    //  Geraete-Token: Suche & Erzeugung  (siehe Migration ..._add_device_token_lookup)
    // ─────────────────────────────────────────────────────────────────────
    //
    //  Ablauf in Kurzform:
    //   - Beim Registrieren: setPlainToken() speichert den Token als bcrypt-Hash
    //     (device_token_hash, sicher) UND als HMAC (device_token_lookup, schnell suchbar).
    //   - Beim Zugriff: findByPlainToken() sucht das Geraet in O(1) ueber den HMAC
    //     und verifiziert dann final mit bcrypt.
    //
    //  Warum zwei Werte? bcrypt salzt jeden Hash zufaellig -> nicht suchbar.
    //  HMAC ist deterministisch -> indizierbar, aber (als reiner Wegweiser) unkritisch,
    //  weil die echte Authentifizierung weiterhin bcrypt macht.

    /**
     * Deterministischer, indizierbarer Lookup-Schluessel eines Klartext-Tokens.
     *
     * WICHTIG: Als HMAC-Geheimnis dient der APP_KEY. Wird der APP_KEY jemals
     * geaendert, passen die gespeicherten device_token_lookup-Werte nicht mehr.
     * Dann einmalig `UPDATE devices SET device_token_lookup = NULL` ausfuehren —
     * die Geraete tragen den Wert beim naechsten Zugriff automatisch neu ein
     * (siehe resolveByPlainToken, Fallback-Zweig).
     */
    public static function tokenLookup(string $plainToken): string
    {
        return hash_hmac('sha256', $plainToken, (string) config('app.key'));
    }

    /**
     * Setzt einen (neuen) Klartext-Token auf dem Geraet: bcrypt-Hash + HMAC-Lookup.
     * Speichert NICHT selbst — der Aufrufer ruft danach save()/create().
     */
    public function setPlainToken(string $plainToken): void
    {
        $this->device_token_hash = \Illuminate\Support\Facades\Hash::make($plainToken);
        $this->device_token_lookup = self::tokenLookup($plainToken);
    }

    /**
     * Zentrale, schnelle Geraete-Suche per Klartext-Token.
     *
     * @param  string|null  $plainToken  Der vom Geraet gesendete Token.
     * @param  \Closure  $filter  Setzt die Grundbedingungen auf den Query-Builder
     *                            (z.B. aktiv / freigegeben) und gibt ihn zurueck.
     *                            So teilen sich alle Aufrufer denselben schnellen
     *                            Lookup-Mechanismus, nur mit unterschiedlichen Filtern.
     */
    protected static function resolveByPlainToken(?string $plainToken, \Closure $filter): ?self
    {
        if (empty($plainToken)) {
            return null;
        }

        $lookup = self::tokenLookup($plainToken);

        // 1) SCHNELLER WEG: indizierte Suche ueber den HMAC-Wegweiser.
        //    Trifft alle Geraete, die bereits einen Lookup-Wert haben (Normalfall).
        $device = $filter(self::query())
            ->where('device_token_lookup', $lookup)
            ->first();

        if ($device && \Illuminate\Support\Facades\Hash::check($plainToken, $device->device_token_hash)) {
            return $device;
        }

        // 2) FALLBACK fuer Alt-Geraete OHNE Lookup-Wert (vor der Migration registriert):
        //    einmalig die bcrypt-Schleife — aber NUR ueber Geraete ohne Lookup,
        //    nicht ueber alle. Bei Treffer wird der Lookup nachgetragen, sodass
        //    dieses Geraet ab dann den schnellen Weg nimmt. Die teure Schleife
        //    schrumpft so mit der Zeit auf 0.
        $candidates = $filter(self::query())
            ->whereNull('device_token_lookup')
            ->whereNotNull('device_token_hash')
            ->get();

        foreach ($candidates as $candidate) {
            if (\Illuminate\Support\Facades\Hash::check($plainToken, $candidate->device_token_hash)) {
                $candidate->forceFill(['device_token_lookup' => $lookup])->saveQuietly();
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Geraet anhand des Klartext-Tokens finden — Standard fuer API-Endpunkte.
     * Liefert nur Geraete, die die API nutzen duerfen: aktiv UND freigegeben
     * (approval_status = active) und nicht geloescht.
     */
    public static function findByPlainToken(?string $plainToken): ?self
    {
        return self::resolveByPlainToken($plainToken, fn ($q) => $q
            ->where('is_active', true)
            ->where('approval_status', self::APPROVAL_ACTIVE));
    }

    /**
     * Geraet fuer Login/Registrierung finden.
     * Unterschied zu findByPlainToken(): der Freigabe-Status wird NICHT gefiltert,
     * damit auch noch nicht freigegebene (pending) Geraete gefunden werden — die
     * Login-/Setup-Logik entscheidet dann selbst, was mit einem pending-Geraet passiert.
     *
     * @param  bool  $withTrashed  Auch soft-geloeschte Geraete einbeziehen
     *                             (z.B. um "Geraet wurde geloescht" zu erkennen).
     */
    public static function findByPlainTokenForAuth(?string $plainToken, bool $withTrashed = false): ?self
    {
        return self::resolveByPlainToken($plainToken, function ($q) use ($withTrashed) {
            if ($withTrashed) {
                // Auch geloeschte Geraete finden (kein is_active-Filter — bewusst).
                return $q->withTrashed();
            }

            return $q->where('is_active', true);
        });
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
