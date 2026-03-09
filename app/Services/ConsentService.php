<?php

namespace App\Services;

use App\Models\ConsentLog;
use App\Models\User;

/**
 * Service fuer DSGVO-Einwilligungsmanagement.
 * Verwaltet Einwilligungen (Datenschutz, AGB, Cookies etc.)
 * und protokolliert alle Zustimmungen und Widerrufe.
 *
 * Aktuelle Versionen der Erklaerungen werden hier zentral gepflegt.
 * Bei Aenderung der Version muessen Benutzer erneut zustimmen.
 */
class ConsentService
{
    /**
     * Aktuelle Versionen der verschiedenen Erklaerungen.
     * Bei Aenderung wird der Benutzer zur erneuten Zustimmung aufgefordert.
     */
    protected array $currentVersions = [
        'privacy_policy' => '1.0',
        'terms' => '1.0',
        'data_processing' => '1.0',
        'cookies' => '1.0',
        'marketing' => '1.0',
    ];

    /**
     * Pflicht-Einwilligungen die bei Registrierung erforderlich sind.
     */
    protected array $requiredConsents = [
        'privacy_policy',
        'terms',
    ];

    /**
     * Einwilligung eines Benutzers aufzeichnen.
     */
    public function recordConsent(
        User $user,
        string $type,
        ?string $version = null,
    ): ConsentLog {
        $version = $version ?? $this->getCurrentVersion($type);

        return ConsentLog::recordConsent(
            userId: $user->id,
            type: $type,
            version: $version,
            tenantId: $user->tenant_id,
        );
    }

    /**
     * Alle Pflicht-Einwilligungen bei Registrierung aufzeichnen.
     */
    public function recordRegistrationConsents(User $user): void
    {
        foreach ($this->requiredConsents as $type) {
            $this->recordConsent($user, $type);
        }
    }

    /**
     * Einwilligung widerrufen.
     */
    public function revokeConsent(User $user, string $type): bool
    {
        $consent = ConsentLog::where('user_id', $user->id)
            ->where('type', $type)
            ->whereNull('revoked_at')
            ->latest('accepted_at')
            ->first();

        if (! $consent) {
            return false;
        }

        $consent->revoke();

        // Widerruf im Audit-Log protokollieren
        app(AuditService::class)->log(
            action: 'update',
            auditable: $consent,
            oldValues: ['revoked_at' => null],
            newValues: ['revoked_at' => now()->toIso8601String()],
            reason: 'DSGVO-Einwilligung widerrufen: ' . $type,
        );

        return true;
    }

    /**
     * Pruefen ob ein Benutzer eine aktive Einwilligung hat.
     */
    public function hasActiveConsent(User $user, string $type): bool
    {
        return ConsentLog::where('user_id', $user->id)
            ->where('type', $type)
            ->where('version', $this->getCurrentVersion($type))
            ->whereNull('revoked_at')
            ->exists();
    }

    /**
     * Pruefen ob alle Pflicht-Einwilligungen vorhanden sind.
     */
    public function hasAllRequiredConsents(User $user): bool
    {
        foreach ($this->requiredConsents as $type) {
            if (! $this->hasActiveConsent($user, $type)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Fehlende Pflicht-Einwilligungen ermitteln.
     */
    public function getMissingConsents(User $user): array
    {
        $missing = [];

        foreach ($this->requiredConsents as $type) {
            if (! $this->hasActiveConsent($user, $type)) {
                $missing[] = $type;
            }
        }

        return $missing;
    }

    /**
     * Alle aktiven Einwilligungen eines Benutzers laden.
     */
    public function getActiveConsents(User $user): array
    {
        return ConsentLog::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->get()
            ->groupBy('type')
            ->map(fn ($group) => $group->sortByDesc('accepted_at')->first())
            ->toArray();
    }

    /**
     * Einwilligungshistorie eines Benutzers laden.
     */
    public function getConsentHistory(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return ConsentLog::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Aktuelle Version einer Erklaerung abrufen.
     */
    public function getCurrentVersion(string $type): string
    {
        return $this->currentVersions[$type] ?? '1.0';
    }

    /**
     * Pruefen ob eine Einwilligung auf der aktuellen Version basiert.
     */
    public function isCurrentVersion(User $user, string $type): bool
    {
        $latest = ConsentLog::where('user_id', $user->id)
            ->where('type', $type)
            ->whereNull('revoked_at')
            ->latest('accepted_at')
            ->first();

        if (! $latest) {
            return false;
        }

        return $latest->version === $this->getCurrentVersion($type);
    }

    /**
     * Pflicht-Einwilligungen abrufen.
     */
    public function getRequiredConsents(): array
    {
        return $this->requiredConsents;
    }

    /**
     * Menschenlesbaren Namen fuer Einwilligungstyp zurueckgeben.
     */
    public function getConsentLabel(string $type): string
    {
        return match ($type) {
            'privacy_policy' => 'Datenschutzerklaerung',
            'terms' => 'Nutzungsbedingungen',
            'data_processing' => 'Auftragsverarbeitung',
            'cookies' => 'Cookie-Richtlinie',
            'marketing' => 'Marketing-Einwilligung',
            default => $type,
        };
    }
}
