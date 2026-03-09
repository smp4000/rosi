<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Service fuer DSGVO-Datenexport (Art. 15 DSGVO - Auskunftsrecht).
 * Exportiert alle gespeicherten Daten eines Benutzers als JSON.
 * Sensible Felder werden entschluesselt und im Klartext exportiert
 * (der Benutzer hat das Recht auf vollstaendige Auskunft).
 */
class DataExportService
{
    /**
     * Alle Daten eines Benutzers fuer den Export sammeln.
     * Gibt ein strukturiertes Array mit allen gespeicherten Daten zurueck.
     */
    public function collectUserData(User $user): array
    {
        $data = [
            'export_info' => [
                'erstellt_am' => now()->format('d.m.Y H:i:s'),
                'benutzer_id' => $user->id,
                'rechtsgrundlage' => 'Art. 15 DSGVO - Auskunftsrecht',
            ],
            'persoenliche_daten' => $this->getPersonalData($user),
            'mandant' => $this->getTenantData($user),
            'einwilligungen' => $this->getConsentData($user),
            'aktivitaets_protokoll' => $this->getAuditData($user),
        ];

        // Personalbogen (wenn vorhanden)
        if ($user->employeeProfile) {
            $data['personalbogen'] = $this->getEmployeeProfileData($user);
        }

        // Dokumente
        $data['dokumente'] = $this->getDocumentData($user);

        // KI-Chat-Verlaeufe
        $data['ki_chat_verlaeufe'] = $this->getAiChatData($user);

        // Audit-Log als Export loggen
        app(AuditService::class)->logExport($user);

        return $data;
    }

    /**
     * Daten als JSON-Datei exportieren und Pfad zurueckgeben.
     */
    public function exportAsJson(User $user): string
    {
        $data = $this->collectUserData($user);
        $filename = 'dsgvo-export-' . $user->id . '-' . now()->format('Y-m-d-His') . '.json';
        $path = 'exports/' . $filename;

        Storage::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $path;
    }

    /**
     * Persoenliche Daten des Benutzers.
     */
    protected function getPersonalData(User $user): array
    {
        return [
            'vorname' => $user->first_name,
            'nachname' => $user->last_name,
            'email' => $user->email,
            'telefon' => $user->phone,
            'typ' => $user->type,
            'sprache' => $user->locale,
            'email_verifiziert_am' => $user->email_verified_at?->format('d.m.Y H:i:s'),
            'letzter_login' => $user->last_login_at?->format('d.m.Y H:i:s'),
            'konto_aktiv' => $user->is_active,
            'registriert_am' => $user->created_at?->format('d.m.Y H:i:s'),
            'zwei_faktor_aktiv' => $user->two_factor_confirmed_at !== null,
        ];
    }

    /**
     * Mandantendaten (wenn vorhanden).
     */
    protected function getTenantData(User $user): ?array
    {
        $tenant = $user->tenant;

        if (! $tenant) {
            return null;
        }

        return [
            'firmenname' => $tenant->name,
            'email' => $tenant->email,
            'telefon' => $tenant->phone,
            'strasse' => $tenant->street,
            'plz' => $tenant->zip,
            'stadt' => $tenant->city,
            'land' => $tenant->country,
            'steuernummer' => $tenant->tax_id,
            'handelsregister' => $tenant->trade_register,
            'abo_status' => $tenant->subscription_status,
            'testphase_endet' => $tenant->trial_ends_at?->format('d.m.Y'),
        ];
    }

    /**
     * Personalbogen-Daten (fuer Mitarbeiter).
     */
    protected function getEmployeeProfileData(User $user): ?array
    {
        $profile = $user->employeeProfile;

        if (! $profile) {
            return null;
        }

        return [
            'geburtsdatum' => $profile->date_of_birth,
            'geburtsort' => $profile->place_of_birth,
            'geschlecht' => $profile->gender,
            'familienstand' => $profile->marital_status,
            'staatsangehoerigkeit' => $profile->nationality,
            'strasse' => $profile->street,
            'plz' => $profile->zip,
            'stadt' => $profile->city,
            'land' => $profile->country,
            'steuer_id' => $profile->tax_id,
            'steuerklasse' => $profile->tax_class,
            'sozialversicherungsnummer' => $profile->social_security_number,
            'krankenkasse' => $profile->health_insurance_name,
            'bank' => $profile->bank_name,
            'iban' => $profile->iban,
            'bic' => $profile->bic,
            'kontoinhaber' => $profile->account_holder,
            'beschaeftigung_beginn' => $profile->employment_start?->format('d.m.Y'),
            'beschaeftigung_ende' => $profile->employment_end?->format('d.m.Y'),
            'beschaeftigungstyp' => $profile->employment_type,
            'wochenstunden' => $profile->weekly_hours,
            'urlaubstage' => $profile->vacation_days,
            'gehalt' => $profile->salary,
            'gehaltstyp' => $profile->salary_type,
        ];
    }

    /**
     * Einwilligungsdaten.
     */
    protected function getConsentData(User $user): array
    {
        return $user->consentLogs()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($log) => [
                'typ' => $log->type,
                'version' => $log->version,
                'zugestimmt_am' => $log->accepted_at?->format('d.m.Y H:i:s'),
                'widerrufen_am' => $log->revoked_at?->format('d.m.Y H:i:s'),
            ])
            ->toArray();
    }

    /**
     * Dokumentendaten (ohne Datei-Inhalte).
     */
    protected function getDocumentData(User $user): array
    {
        return $user->documents()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($doc) => [
                'titel' => $doc->title,
                'typ' => $doc->type,
                'kategorie' => $doc->category,
                'status' => $doc->status,
                'erstellt_am' => $doc->created_at?->format('d.m.Y H:i:s'),
                'unterschrieben_am' => $doc->signed_at?->format('d.m.Y H:i:s'),
            ])
            ->toArray();
    }

    /**
     * KI-Chat-Daten.
     */
    protected function getAiChatData(User $user): array
    {
        return $user->aiChatMessages()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($msg) => [
                'gespraechs_id' => $msg->conversation_id,
                'rolle' => $msg->role,
                'nachricht' => $msg->content,
                'erstellt_am' => $msg->created_at?->format('d.m.Y H:i:s'),
            ])
            ->toArray();
    }

    /**
     * Audit-Daten (eigene Aktivitaeten).
     */
    protected function getAuditData(User $user): array
    {
        return \App\Models\AuditLog::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(500) // Letzte 500 Eintraege
            ->get()
            ->map(fn ($log) => [
                'aktion' => $log->action,
                'betroffenes_model' => $log->auditable_type,
                'ip_adresse' => $log->ip_address,
                'zeitpunkt' => $log->created_at?->format('d.m.Y H:i:s'),
            ])
            ->toArray();
    }
}
