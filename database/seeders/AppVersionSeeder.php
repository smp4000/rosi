<?php

namespace Database\Seeders;

use App\Models\AppVersion;
use Illuminate\Database\Seeder;

/**
 * Versionshistorie fuer Web- und App-Plattform.
 * Wird bei jedem Release erweitert.
 */
class AppVersionSeeder extends Seeder
{
    public function run(): void
    {
        $versions = [
            // --- Web-Versionen ---
            [
                'platform' => 'web',
                'version' => '1.0.0',
                'release_date' => '2026-03-08',
                'changes' => [
                    'Initiales Projekt mit Mandanten-Verwaltung',
                    'Tankstellen-Verwaltung (CRUD)',
                    'Mitarbeiter-Verwaltung mit Profilen',
                    'Rollen- und Berechtigungssystem',
                ],
            ],
            [
                'platform' => 'web',
                'version' => '1.1.0',
                'release_date' => '2026-03-16',
                'changes' => [
                    'Marken-Verwaltung (Aral, Shell, etc.)',
                    'Erweiterte Tankstellen-Daten (Waschanlage, Kraftstoffe, Wettbewerber)',
                    'Artikelverwaltung mit CSV-Import',
                    'EAN-Verwaltung und Artikel-Gruppen',
                ],
            ],
            [
                'platform' => 'web',
                'version' => '1.2.0',
                'release_date' => '2026-03-19',
                'changes' => [
                    'Dokumentvorlagen-System',
                    'Platzhalter-Einstellungen',
                    'Krankenversicherungs-Verwaltung',
                    'Mitarbeiter-Bankkonten',
                ],
            ],
            [
                'platform' => 'web',
                'version' => '1.3.0',
                'release_date' => '2026-03-23',
                'changes' => [
                    'Firmenkunden-Verwaltung',
                    'Tankkarten-System',
                    'Rechnungswesen (Batches, Einzel-Rechnungen, E-Mail-/Druck-Protokoll)',
                    'Rechnungs-Einstellungen',
                ],
            ],
            [
                'platform' => 'web',
                'version' => '1.4.0',
                'release_date' => '2026-03-28',
                'changes' => [
                    'POS-App Geraete-Verwaltung',
                    'Geraete-Einladungen per QR-Code/Link',
                    'Mitarbeiter-Ausweise mit QR-Code (PDF)',
                    'Scan-Code und PIN fuer POS-Login',
                ],
            ],
            [
                'platform' => 'web',
                'version' => '1.5.0',
                'release_date' => '2026-04-06',
                'changes' => [
                    'MHD-Kontrolle: Uebersicht, Ampel-System, Filter',
                    'Abschreibungsgruende-Verwaltung',
                    'Dashboard-Alerts (MHD-Warnungen)',
                    'Versionshistorie-Widget',
                    'Partner/Inhaber in Mitarbeiter-Liste sichtbar',
                ],
            ],

            // --- App-Versionen ---
            [
                'platform' => 'android',
                'version' => '1.0.0',
                'release_date' => '2026-03-28',
                'changes' => [
                    'Geraete-Registrierung per QR-Code',
                    'Mitarbeiter-Login (Scanner, Code-Eingabe)',
                    'Home-Screen mit Hamburger-Menue',
                ],
            ],
            [
                'platform' => 'android',
                'version' => '1.1.0',
                'release_date' => '2026-03-29',
                'changes' => [
                    'NFC-Login (NDEF Text Records)',
                    'Scanner Auto-Login',
                    'Settings: GPS-Position, Versionshistorie aus API',
                ],
            ],
            [
                'platform' => 'android',
                'version' => '1.3.0',
                'release_date' => '2026-03-30',
                'changes' => [
                    'Artikelinfo: Suche per EAN/Name/Artikelnr',
                    'Kamera-Scanner (ML Kit)',
                    'Dynamische Server-URL',
                    'Verbindungs-Check',
                ],
            ],
            [
                'platform' => 'android',
                'version' => '1.4.0',
                'release_date' => '2026-04-05',
                'changes' => [
                    'MHD-Kontrolle: Artikel scannen, MHD-Datum erfassen',
                    'Ampel-System (rot/orange/gruen)',
                    'MHD verlaengern oder abschreiben',
                    'Dashboard-Warnung bei abgelaufenen Artikeln',
                    'Duplikat-Schutz via Kenner (MD5)',
                ],
            ],
            [
                'platform' => 'android',
                'version' => '1.5.0',
                'release_date' => '2026-04-06',
                'changes' => [
                    'NFC-Ausweise schreiben',
                    'Lieferschein-Import',
                    'Passwortschutz-Toggle in Settings',
                    'Mitarbeiter-Tracking',
                ],
            ],

            // --- v1.6.0 ---
            [
                'platform' => 'web',
                'version' => '1.6.0',
                'release_date' => '2026-04-07',
                'changes' => [
                    'Tankbetrug-Modul: Meldungen aus POS-App empfangen und verwalten',
                    'Tankbetrug-Tabelle mit Gruppierung nach Tankstelle',
                    'Archiv-Tabs: Erledigte/Abgelehnte Faelle ausblenden',
                    'Tankbetrug-API: Formulardaten und Meldungs-Endpunkte',
                    'Tankstellen: Kamera-Verfuegbarkeit (has_camera) Feld',
                ],
            ],
            [
                'platform' => 'android',
                'version' => '1.6.0',
                'release_date' => '2026-04-07',
                'changes' => [
                    'Tankbetrug melden: Komplettes Formular mit Pflichtfeldern',
                    'Belegfoto per Kamera aufnehmen (Required)',
                    'Unterschrift-Pad mit Freihand-Zeichnung',
                    'Rechtsbelehrung mit Pflicht-Akzeptierung',
                    'Dezimaltrennzeichen-Fix fuer deutsche Geraete',
                ],
            ],
            // --- v1.7.0 ---
            [
                'platform' => 'web',
                'version' => '1.7.0',
                'release_date' => '2026-04-07',
                'changes' => [
                    'Print-Gateway: Adressetiketten ueber DYMO WebApi drucken',
                    'API-Endpunkte: POST /print/label, GET /print/printers',
                    'Automatische DYMO-Drucker-Erkennung auf dem Server',
                ],
            ],
            [
                'platform' => 'android',
                'version' => '1.7.0',
                'release_date' => '2026-04-07',
                'changes' => [
                    'Adressetiketten drucken ueber Server Print-Gateway (DYMO)',
                    'Bluetooth-Drucker (TSC): Direktdruck per TSPL-Befehle',
                    'Drucker-Erkennung: NSD, Bluetooth, USB und Subnet-Scan',
                    'Einstellungen: Tab-basiertes Layout (Allgemein, Verbindung, Module, MHD, Sicherheit)',
                    'Bluetooth-Berechtigung fuer Android 12+',
                ],
            ],
        ];

        $count = 0;

        foreach ($versions as $data) {
            AppVersion::updateOrCreate(
                ['version' => $data['version'], 'platform' => $data['platform']],
                [
                    'release_date' => $data['release_date'],
                    'changes' => $data['changes'],
                    'is_published' => true,
                ],
            );
            $count++;
        }

        $this->command->info("  {$count} App-Versionen (Web + Android) erstellt/aktualisiert.");
    }
}
