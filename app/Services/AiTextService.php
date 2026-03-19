<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * KI-Text-Generierung via Perplexity Sonar API.
 * Generiert komplette Dokumente basierend auf Dokumenttyp und Mitarbeiterdaten.
 */
class AiTextService
{
    protected string $apiKey;

    protected string $model;

    protected string $baseUrl = 'https://api.perplexity.ai';

    public function __construct()
    {
        $this->apiKey = config('services.perplexity.api_key', '');
        $this->model = config('services.perplexity.model', 'sonar');
    }

    /**
     * Pruefen ob der Service konfiguriert ist.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Dokumenttyp-Optionen fuer das Select-Feld.
     */
    public static function getDocumentTypeOptions(): array
    {
        return [
            'employment_contract' => 'Arbeitsvertrag',
            'termination' => 'Kuendigung',
            'warning' => 'Abmahnung',
            'work_certificate' => 'Arbeitszeugnis',
            'interim_certificate' => 'Zwischenzeugnis',
            'amendment' => 'Vertragsaenderung / Nachtrag',
            'vacation_request' => 'Urlaubsantrag',
            'sick_note' => 'Krankmeldung',
            'salary_adjustment' => 'Gehaltsanpassung',
            'training_certificate' => 'Schulungsbescheinigung',
            'reference_letter' => 'Empfehlungsschreiben',
            'custom' => 'Sonstiges (Freitext)',
        ];
    }

    /**
     * Komplettes Dokument von der KI generieren lassen.
     * Der User waehlt nur den Typ und gibt optional Zusatzinfos — die KI macht den Rest.
     */
    public function generateFullDocument(
        string $documentType,
        User $employee,
        ?string $additionalInstructions = null,
    ): string {
        $context = $this->buildEmployeeContext($employee);
        $documentLabel = self::getDocumentTypeOptions()[$documentType] ?? $documentType;

        // Spezifische Struktur-Vorgaben pro Dokumenttyp
        $structureGuides = $this->getStructureGuide($documentType);

        $systemPrompt = <<<PROMPT
Du bist ein professioneller HR-Dokumenten-Generator fuer ein deutsches Unternehmen (Tankstellenbranche).
Du erstellst komplette, rechtlich korrekte Dokumente basierend auf den bereitgestellten Mitarbeiterdaten.

WICHTIGE Regeln:
- Schreibe ausschliesslich auf Deutsch in formeller Geschaeftssprache (Sie-Form)
- Halte dich strikt an deutsches Arbeitsrecht (BGB, KSchG, TzBfG etc.)
- Gib NUR den fertigen HTML-Inhalt zurueck — KEINE Erklaerungen, KEINE Markdown-Codeblocks
- KEIN vollstaendiges HTML-Dokument (kein DOCTYPE, html, head, body, style Tags) — nur den Inhalt
- Verwende sauberes HTML: h2 fuer Titel, h3 fuer Paragraphen, p fuer Text, strong fuer Hervorhebungen
- Erfinde KEINE Daten — verwende NUR die bereitgestellten Mitarbeiterdaten
- Wo Daten fehlen (z.B. Gehalt), setze einen deutlichen Platzhalter: [zu vereinbarer Betrag]
- Verwende korrekte Anrede basierend auf dem Geschlecht (Herr/Frau/Person)
- Das Dokument muss vollstaendig und druckfertig sein
- Datumsformat: TT.MM.JJJJ
- Am Ende: Unterschriftszeilen fuer Arbeitgeber und Arbeitnehmer mit Datum und Ort
PROMPT;

        $userPrompt = <<<PROMPT
Erstelle ein komplettes Dokument vom Typ: {$documentLabel}

Mitarbeiterdaten:
{$context}

{$structureGuides}
PROMPT;

        if ($additionalInstructions) {
            $userPrompt .= "\n\nZusaetzliche Anweisungen vom Arbeitgeber:\n{$additionalInstructions}";
        }

        return $this->chat($systemPrompt, $userPrompt);
    }

    /**
     * Bestehenden Dokumentinhalt mit KI ueberarbeiten/personalisieren.
     */
    public function refineDocument(
        string $currentContent,
        string $instructions,
        User $employee,
    ): string {
        $context = $this->buildEmployeeContext($employee);

        $systemPrompt = <<<PROMPT
Du bist ein professioneller HR-Textgenerator fuer ein deutsches Unternehmen.
Du ueberarbeitest ein bestehendes Dokument basierend auf den Anweisungen des Nutzers.

Regeln:
- Gib NUR den ueberarbeiteten HTML-Inhalt zurueck, keine Erklaerungen
- Behalte die HTML-Struktur bei
- Schreibe auf Deutsch in formeller Geschaeftssprache
- Aendere nur was der Nutzer verlangt, behalte den Rest bei
PROMPT;

        $userPrompt = <<<PROMPT
Mitarbeiterdaten:
{$context}

Anweisung: {$instructions}

Aktueller Dokumentinhalt:
{$currentContent}
PROMPT;

        return $this->chat($systemPrompt, $userPrompt);
    }

    /**
     * Struktur-Vorgaben fuer bestimmte Dokumenttypen.
     */
    protected function getStructureGuide(string $type): string
    {
        $guides = [
            'employment_contract' => <<<'GUIDE'
Struktur fuer den Arbeitsvertrag:
- Kopf: "Arbeitsvertrag" als Titel, Vertragsparteien (Arbeitgeber + Arbeitnehmer mit Adressen)
- § 1 Beginn und Dauer (Eintrittsdatum verwenden, unbefristet als Standard)
- § 2 Probezeit (6 Monate, 2 Wochen Kuendigungsfrist)
- § 3 Taetigkeit (Rolle und Tankstelle(n) einfuegen)
- § 4 Arbeitszeit (Wochenstunden verwenden)
- § 5 Verguetung (Gehalt oder Platzhalter)
- § 6 Urlaub (Urlaubstage oder 30 Tage als Standard)
- § 7 Kuendigungsfristen (gesetzliche Fristen)
- § 8 Nebentaetigkeiten
- § 9 Verschwiegenheit
- § 10 Vertragsstrafe (optional)
- § 11 Schlussbestimmungen
- Unterschriften: Ort, Datum, Arbeitgeber, Arbeitnehmer
GUIDE,

            'termination' => <<<'GUIDE'
Struktur fuer die Kuendigung:
- Absender (Arbeitgeber) mit Adresse
- Empfaenger (Mitarbeiter) mit Adresse
- Datum und Ort
- Betreff: "Kuendigung des Arbeitsverhaeltnisses"
- Anrede
- Kuendigungserklaerung mit Frist (gesetzlich oder vertraglich)
- Hinweis auf Restansprueche (Urlaub, Zeugnis)
- Hinweis auf Meldepflicht bei der Agentur fuer Arbeit (§ 38 SGB III)
- Freistellung (optional)
- Bitte um schriftliche Bestätigung
- Grussformel und Unterschrift
GUIDE,

            'warning' => <<<'GUIDE'
Struktur fuer die Abmahnung:
- Absender und Empfaenger
- Datum
- Betreff: "Abmahnung"
- Beschreibung des Fehlverhaltens: [konkreter Vorfall — vom Arbeitgeber zu ergaenzen]
- Verweis auf verletzte Pflichten
- Aufforderung zur kuenftigen Vertragserfuellung
- Androhung arbeitsrechtlicher Konsequenzen bei Wiederholung
- Unterschrift Arbeitgeber
- Empfangsbestaetigung Arbeitnehmer
GUIDE,

            'work_certificate' => <<<'GUIDE'
Struktur fuer das Arbeitszeugnis (qualifiziert):
- Ueberschrift: "Arbeitszeugnis"
- Einleitung (Name, Geburtsdatum, Beschaeftigungszeitraum)
- Unternehmensbeschreibung (Tankstellenbetrieb)
- Taetigkeitsbeschreibung (basierend auf Rolle)
- Leistungsbeurteilung (Zeugnis-Codierung Note "gut" als Standard)
- Sozialverhalten
- Fuehrungsverhalten (falls Stationsleiter/Schichtleiter)
- Beendigungsgrund (auf eigenen Wunsch als Standard)
- Schlussformel mit Dank und guten Wuenschen
- Datum, Ort, Unterschrift
GUIDE,

            'interim_certificate' => <<<'GUIDE'
Struktur wie Arbeitszeugnis, aber:
- Ueberschrift: "Zwischenzeugnis"
- Formulierung im Praesens (Mitarbeiter ist noch beschaeftigt)
- Grund fuer Ausstellung angeben (z.B. auf Wunsch des Mitarbeiters)
- Keine Schlussformel mit Ausscheiden
GUIDE,

            'salary_adjustment' => <<<'GUIDE'
Struktur fuer Gehaltsanpassung:
- Bezug auf bestehenden Arbeitsvertrag
- Neues Gehalt: [Platzhalter — vom Arbeitgeber einzutragen]
- Gueltig ab: [Datum]
- Alle anderen Vertragsbestandteile bleiben unveraendert
- Unterschriften beider Parteien
GUIDE,

            'amendment' => <<<'GUIDE'
Struktur fuer Vertragsaenderung:
- Bezug auf bestehenden Arbeitsvertrag mit Datum
- Auflistung der geaenderten Paragraphen/Klauseln
- Platzhalter fuer die konkreten Aenderungen: [vom Arbeitgeber zu ergaenzen]
- Hinweis: alle uebrigen Bestimmungen bleiben unveraendert
- Unterschriften beider Parteien
GUIDE,
        ];

        return $guides[$type] ?? 'Erstelle ein professionelles, vollstaendiges Dokument mit allen notwendigen Abschnitten.';
    }

    /**
     * Perplexity Sonar API aufrufen (OpenAI-kompatibel).
     */
    protected function chat(string $systemPrompt, string $userPrompt): string
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Perplexity API-Key nicht konfiguriert. Bitte PERPLEXITY_API_KEY in .env setzen.');
        }

        try {
            $response = Http::withoutVerifying()
                ->timeout(90)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'temperature' => 0.3,
                    'max_tokens' => 4000,
                ]);

            if (! $response->successful()) {
                Log::error('Perplexity API Fehler', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \RuntimeException('KI-Generierung fehlgeschlagen (HTTP ' . $response->status() . ')');
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? '';

            // Markdown-Codeblocks entfernen falls die KI welche zurueckgibt
            $content = preg_replace('/^```html?\s*/i', '', $content);
            $content = preg_replace('/\s*```\s*$/', '', $content);

            // HTML-Wrapper entfernen (DOCTYPE, html, head, body Tags)
            // Die KI gibt manchmal ein volles HTML-Dokument zurueck
            $content = preg_replace('/<!DOCTYPE[^>]*>/i', '', $content);
            $content = preg_replace('/<html[^>]*>/i', '', $content);
            $content = preg_replace('/<\/html>/i', '', $content);
            $content = preg_replace('/<head>.*?<\/head>/is', '', $content);
            $content = preg_replace('/<\/?body[^>]*>/i', '', $content);

            return trim($content);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Perplexity API Verbindungsfehler', ['message' => $e->getMessage()]);
            throw new \RuntimeException('Verbindung zur KI fehlgeschlagen. Bitte spaeter erneut versuchen.');
        }
    }

    /**
     * Mitarbeiter-Kontext fuer den Prompt aufbauen.
     */
    protected function buildEmployeeContext(User $employee): string
    {
        $lines = [];

        // Geschlecht fuer korrekte Anrede
        $profile = $employee->employeeProfile;
        $gender = $profile?->gender;
        $anrede = match ($gender) {
            'male' => 'Herr',
            'female' => 'Frau',
            default => 'Person',
        };
        $lines[] = "Anrede: {$anrede}";
        $lines[] = "Vorname: " . ($employee->first_name ?? '');
        $lines[] = "Nachname: " . ($employee->last_name ?? '');
        $lines[] = "Voller Name: " . ($employee->name ?? 'Nicht angegeben');
        $lines[] = "E-Mail: " . ($employee->email ?? 'Nicht angegeben');

        if ($profile) {
            if ($profile->date_of_birth) {
                $dob = $profile->date_of_birth instanceof \Carbon\Carbon
                    ? $profile->date_of_birth->format('d.m.Y')
                    : (string) $profile->date_of_birth;
                $lines[] = "Geburtsdatum: " . $dob;
            }

            if ($profile->street) $lines[] = "Strasse: " . $profile->street;
            if ($profile->zip_code) $lines[] = "PLZ: " . $profile->zip_code;
            if ($profile->city) $lines[] = "Ort: " . $profile->city;

            $employmentTypes = [
                'full_time' => 'Vollzeit',
                'part_time' => 'Teilzeit',
                'minijob' => 'Minijob (520 EUR)',
                'trainee' => 'Ausbildung',
                'intern' => 'Praktikum',
                'temporary' => 'Befristet',
            ];
            if ($profile->employment_type) {
                $lines[] = "Beschaeftigungsart: " . ($employmentTypes[$profile->employment_type] ?? $profile->employment_type);
            }

            if ($profile->employment_start) {
                $start = $profile->employment_start instanceof \Carbon\Carbon
                    ? $profile->employment_start->format('d.m.Y')
                    : (string) $profile->employment_start;
                $lines[] = "Eintrittsdatum: " . $start;
            }
            if ($profile->weekly_hours) $lines[] = "Woechentliche Arbeitszeit: " . $profile->weekly_hours . " Stunden";
            if ($profile->salary) $lines[] = "Bruttogehalt: " . number_format($profile->salary, 2, ',', '.') . " EUR monatlich";
            if ($profile->vacation_days) $lines[] = "Urlaubstage pro Jahr: " . $profile->vacation_days;
        }

        // Tankstellen-Zuordnung mit Rollen
        $stations = $employee->gasStations;
        if ($stations && $stations->isNotEmpty()) {
            $roleMap = [
                'station_manager' => 'Stationsleiter/in',
                'shift_leader' => 'Schichtleiter/in',
                'cashier' => 'Kassierer/in',
                'attendant' => 'Tankwart/in',
                'trainee' => 'Auszubildende/r',
                'cleaner' => 'Reinigungskraft',
                'carwash' => 'Waschanlage',
                'employee' => 'Mitarbeiter/in',
            ];

            $stationDetails = [];
            foreach ($stations as $station) {
                $role = $station->pivot->station_role ?? 'employee';
                $roleName = $roleMap[$role] ?? $role;
                $isPrimary = $station->pivot->is_primary ? ' (Hauptstandort)' : '';
                $stationDetails[] = "{$station->name} als {$roleName}{$isPrimary}";
            }
            $lines[] = "Tankstelle(n): " . implode(', ', $stationDetails);
        }

        // Arbeitgeber-Infos aus Tenant
        $tenant = $employee->tenant;
        if ($tenant) {
            $lines[] = "Arbeitgeber: " . ($tenant->company_name ?? $tenant->name ?? 'Nicht angegeben');
            if ($tenant->street) $lines[] = "Arbeitgeber-Strasse: " . $tenant->street;
            if ($tenant->zip_code && $tenant->city) $lines[] = "Arbeitgeber-Ort: " . $tenant->zip_code . ' ' . $tenant->city;
        }

        return implode("\n", $lines);
    }
}
