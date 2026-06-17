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
            // --- v1.9.0 ---
            [
                'platform' => 'web',
                'version' => '1.9.0',
                'release_date' => '2026-04-14',
                'changes' => [
                    'DYMO Label-Templates in Datenbank mit Platzhaltern',
                    'Druckvorlagen-Auswahl: Stationen koennen zwischen Templates waehlen',
                    'Demo-Druck direkt im Browser (JavaScript → DYMO)',
                    'Admin-Panel: Template-Verwaltung mit Kategorie und Modell-Auswahl',
                    'DYMO Drucker-Seite: Port-Scanning, Testdruck, Verbindungspruefung',
                    'PrintLabel2 API-Fix fuer DYMO Connect',
                    'Template-Zuordnung via device_token (Tenant-Erkennung)',
                ],
            ],
            [
                'platform' => 'android',
                'version' => '1.9.0',
                'release_date' => '2026-04-14',
                'changes' => [
                    'Tankbetrug-Druck ueber Template-API (Server ersetzt Platzhalter)',
                    'DYMO-Druck ueber XAMPP-Server statt Direktverbindung',
                    'device_token wird bei Druck mitgesendet (Template-Zuordnung)',
                    'Datum/Uhrzeit: Keine Zukunft erlaubt bei Tankbetrug-Meldung',
                    'Tastatur-Fix: Auto-Scroll zum fokussierten Feld (imePadding)',
                    'Tankbetrug-ID + Mitarbeiter als Platzhalter beim Druck',
                ],
            ],
            // --- v1.7.0 ---
            [
                'platform' => 'web',
                'version' => '1.7.0',
                'release_date' => '2026-04-07',
                'changes' => [
                    'Print-Gateway: Adressetiketten ueber DYMO WebApi drucken',
                    'Tankbetrug-Etikett: Automatischer DYMO-Druck bei neuer Meldung (101x54mm)',
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
            // --- v1.9.1 ---
            [
                'platform' => 'web',
                'version' => '1.9.1',
                'release_date' => '2026-05-01',
                'changes' => [
                    'Abo-Pruefung: API blockiert Zugang bei abgelaufenem Abonnement/Testphase',
                    'Abo-Check in Login- und Scan-Login-Endpunkten',
                    'API-Middleware CheckApiAccess fuer geschuetzte Routen',
                    'Filament: Super-Admin-Fallback fuer Berechtigungsprobleme behoben',
                    'Vite-Build fuer All-Inkl Shared Hosting (kein npm auf Server)',
                    'Versionshistorie: Scroll-Hinweis fuer aeltere Versionen',
                ],
            ],
            [
                'platform' => 'android',
                'version' => '1.9.1',
                'release_date' => '2026-05-01',
                'changes' => [
                    'Abo-Fehlermeldung: Schoene Anzeige bei abgelaufenem Abonnement',
                    'Retrofit errorBody-Parsing fuer HTTP 4xx/5xx Fehler behoben',
                    'Login zeigt Server-Fehlermeldungen korrekt an (statt generisch)',
                    'DYMO-Druck: Port 41950 als erster Scan-Port (netsh portproxy)',
                    'Label-Rendering ueber All-Inkl statt Render.com',
                ],
            ],
            // --- v2.0.0 ---
            [
                'platform' => 'web',
                'version' => '2.0.0',
                'release_date' => '2026-05-02',
                'changes' => [
                    'Schichtabrechnung: Komplettes Backend mit Wizard-Ablauf',
                    'Tresor-Einlagen: API mit automatischem DYMO-Etikettdruck',
                    'Tresor-Label: DieCutLabel 8.0 Format mit Platzhaltern',
                    'Warenruecknahmen: API mit Foto-Upload (Multipart)',
                    'Ruecknahme-Gruende: Verwaltung im Partner-Panel (CRUD)',
                    'API: Ruecknahme-Gruende Endpoint mit Sonstiges-Option',
                    'Schichtabrechnung: Prueffragen, Muenzrollen, Zaehlerstaende',
                    'Kassenbericht: IST/SOLL-Vergleich mit Differenzberechnung',
                ],
            ],
            [
                'platform' => 'android',
                'version' => '2.0.0',
                'release_date' => '2026-05-02',
                'changes' => [
                    'Schichtabrechnung: 8-Schritt-Wizard (Prueffragen bis Unterschrift)',
                    'Tresor-Einlagen: Erfassung mit automatischem DYMO-Etikettdruck',
                    'Warenruecknahmen: Grund als Dropdown (vom Server konfigurierbar)',
                    'Warenruecknahmen: Bonnummer numerisch + Pflicht, Bon-Foto Pflicht',
                    'Warenruecknahmen: Sonstiges-Option mit Freitext-Eingabe',
                    'Datum/Uhrzeit-Picker fuer Warenruecknahmen',
                    'HomeScreen: Aktive Schicht gelb markiert und oben links angeordnet',
                    'HomeScreen: Schicht-Pruefung pro eingeloggtem Mitarbeiter',
                    'Muenzrollen: Bei neuer Schicht auf 0 zurueckgesetzt',
                ],
            ],
            // --- v2.0.1 (03.05.2026) ---
            [
                'platform' => 'web',
                'version' => '2.0.1',
                'release_date' => '2026-05-03',
                'changes' => [
                    'Schicht-Auswertung: Liste aller Schichten im Partner-Panel mit Filtern',
                    'Schicht-Detailansicht mit allen Anhaengen (Bon-Fotos, Kassenbericht, Unterschrift)',
                    'PDF-Download einer Schichtabrechnung (kompakt + Anlagen-Anhang)',
                    'Bearbeiten-Button auf Schicht-Detailseite (Beginn/Ende/Status/Bemerkungen)',
                    'Bilder-Streaming-Route: Bilder direkt aus private Storage (kein storage:link noetig)',
                    'API: /shift-settlements/last-values fuer Smart-Defaults',
                    'API: /shift-settlements/mine + /{id}/details fuer App-Liste eigener Schichten',
                    'API: /{id}/comments fuer nachtraegliche Kommentare',
                    'Filament: Schicht-Einstellungen (DYMO-Etikett automatisch drucken Toggle)',
                    'Seeder: Zwei zusaetzliche Ruecknahme-Gruende (Falscher Artikel, Fehlende Materialien)',
                ],
            ],
            [
                'platform' => 'android',
                'version' => '2.0.1',
                'release_date' => '2026-05-03',
                'changes' => [
                    'Smart-Defaults: Anfangsbestaende aus letzter Schicht der Tankstelle',
                    'Endbestaende automatisch mit Anfangswerten vorbelegt',
                    '+/- Buttons fuer Zaehlerstaende (Waschanlage/Kaffee/Pfand)',
                    'Tresor-Einlage: Schnellbuttons 100/200/300/400/500/600/700 EUR',
                    'Tresor-Einlage: Muenzgeld-Checkbox entfernt',
                    'Optionales Foto vom Kassenbericht-Zettel (Schritt 7/8)',
                    'Auto-Print-Setting des Servers respektiert',
                    'App im Hochformat-Lock (kein Phone-Compat-Mode auf Tablets)',
                    'Unterschrift-Schritt: Querformat, nur Pad + Zurueck/Loeschen/Abschliessen',
                    'Tankbetrug-Unterschrift nutzt jetzt Vollbild-Dialog (Querformat)',
                    'Meine Schichten-Modul: read-only Liste eigener Schichten mit Kommentaren',
                ],
            ],
            // --- v2.1.0 (10.05.2026) ---
            [
                'platform' => 'web',
                'version' => '2.1.0',
                'release_date' => '2026-05-10',
                'changes' => [
                    'Zeitungen-Modul: Komplettes Backend fuer Presseerzeugnisse',
                    'ZUGFeRD/Factur-X-Parser fuer eingebettete Rechnungs-XML (EN16931)',
                    'PVG-Rechnungs-Import: Lieferant + Tankstelle automatisch erkannt',
                    'Datenbank: 12 Tabellen newspaper_* (Artikel, Ausgaben, Rechnungen, Bewegungen)',
                    'Allgemeine Lieferanten-Verwaltung (modul-uebergreifend)',
                    'Pivot supplier_stations mit Kundennummer pro Tankstelle',
                    'Filament: Lieferanten-Resource unter Stammdaten mit Stationen-RelationManager',
                    'Filament: Zeitungen-Dashboard mit KPIs + PDF-Upload',
                    'Filament: Zeitungs-Artikel + Rechnungen Resources mit CSV-Export',
                    'EAN-Inspector: 419/439=7%, 414/434=19%, Pruefziffer-Validierung',
                    'API: /api/v1/kiosk/* (Lookup, Save, Upsert-Pending)',
                    'Auto-Cleanup von livewire-tmp Uploads',
                    'Wochentag aus Bezeichnung extrahiert (1Mo, 2Di, ... 7So)',
                ],
            ],
            [
                'platform' => 'android',
                'version' => '2.1.0',
                'release_date' => '2026-05-10',
                'changes' => [
                    'Zeitungen-Modul: Lieferung, Remission, Inventur als 3 Screens',
                    'Hardware-Scanner Bridge fuer MDE-Scanner (Keyboard-Wedge)',
                    'Auto-Submit nach 400ms Pause (auch ohne Enter-Terminator)',
                    'Kamera-Backup-Scanner mit CameraX + ML Kit',
                    'Scanner-Overlay: Scan-Rahmen + animierter roter Strich',
                    'Kamera bleibt nach Scan offen, gruener Banner + Beep',
                    'Duplikat-Logik: gleiche EAN -> Menge +1, rutscht nach oben',
                    'Auto-Anlegen unbekannter EANs als "Zeitung" mit Preis aus EAN',
                    'Auto-Scroll: letzter Scan immer oben sichtbar',
                    'KW-Defaults: Lieferung aktuelle KW, Remission Vorwoche',
                    'Drei neue Tiles im HomeScreen (Gruppe Shop)',
                ],
            ],
            // --- v2.2.0 (12.05.2026) ---
            [
                'platform' => 'web',
                'version' => '2.2.0',
                'release_date' => '2026-05-12',
                'changes' => [
                    'Gutschein-Modul: Ausgabe in Gruppen (z.B. 4567.000-4567.499), Einloesung mit Teilbetraegen',
                    'Gutscheinnummern eindeutig pro Tankstelle (statt global)',
                    'Gutschein-Status: aktiv, teilweise eingeloest, eingeloest, abgelaufen, storniert',
                    'Betrag in Worten auf dem Etikett (z.B. "fuenfzig Euro")',
                    'DYMO Print-Queue: Druckauftraege von der App an den PC-Browser via Datenbank',
                    'Label-Vorlagen-System mit Platzhaltern (LabelTemplate)',
                    'Filament: Gutschein-Uebersicht mit Status-Badges, Gutschein-Ausgabe-Seite mit Web-Druck',
                    'API: /api/v1/vouchers/* (lookup, check-group, generate, redeem)',
                ],
            ],
            [
                'platform' => 'android',
                'version' => '2.2.0',
                'release_date' => '2026-05-12',
                'changes' => [
                    'Gutschein-Modul: Einloesen per Scan (Teilbetraege moeglich)',
                    'Gutscheine ausgeben: Gruppe + Anzahl mit Live-Pruefung auf Nummernkonflikte',
                    'DYMO-Etikettendruck direkt aus der App (lokales Netzwerk)',
                    'Druckauftraege alternativ an den PC-Browser senden (Print-Queue)',
                    'Zwei neue Tiles im HomeScreen (Gruppe Shop)',
                ],
            ],
            // --- v2.3.0 (13.06.2026) ---
            [
                'platform' => 'web',
                'version' => '2.3.0',
                'release_date' => '2026-06-13',
                'changes' => [
                    'Rollenbasiertes Sicherheitssystem: Permission-Katalog als einzige Quelle (103 Rechte)',
                    'Rollen-Matrix mit Checkboxen unter Einstellungen -> Rollen & Rechte',
                    'Eigene Rollen pro Partner anlegbar (z.B. Hausmeister), System-Rollen geschuetzt',
                    'Rollen pro Tankstelle: gleicher Mitarbeiter kann Kassierer in A und Schichtleiter in B sein',
                    'Fein granulare Aktionen: Liste/Details/Anlegen/Bearbeiten/Loeschen/Sammel-Loeschen/Wiederherstellen',
                    'Buttons werden ohne Berechtigung automatisch ausgeblendet, URL-Aufruf liefert 403',
                    'Dashboard fein granuliert: Statistik, Warnungen, Schnellaktionen einzeln schaltbar',
                    'MDE-Stationsanmeldung: permanenter Stations-QR mit GPS-Pruefung und Freigabe-Warteschlange',
                    'Geraete-Freigabe im Dashboard mit GPS-Abweichungs-Anzeige und Navigations-Badge',
                    'Admin-API fuer NFC-Beschreibung: Mitarbeiter mit Adresse, Geburtsdatum, festem Zugangscode',
                    'Sync-Befehle: rosi:permissions-sync und rosi:roles-migrate (idempotent)',
                    'Doku: docs/ROLLENSYSTEM.md + Info-Box in der Rollen-Matrix',
                ],
            ],
            [
                'platform' => 'android',
                'version' => '2.3.0',
                'release_date' => '2026-06-13',
                'changes' => [
                    'Anmeldung an der Tankstelle per permanentem Stations-QR (Code an der Kasse)',
                    'GPS-Pruefung bei der Anmeldung: vor Ort sofort aktiv, sonst Freigabe-Wartebildschirm',
                    'Stationswechsel durch erneuten Scan an der neuen Tankstelle',
                    'Rechte vom Server: Kacheln und Menue zeigen nur erlaubte Module (pro Station)',
                    'Admin-Bereich: Stations-Icon 3 Sekunden halten + Tages-Sicherheitscode',
                    'NFC-Chips beschreiben: Zugangscode, Name, Adresse, Geburtsdatum auf den Chip',
                    'Neues Home-Design: weisse Kacheln mit farbigen Icons, Profil-Drawer mit Gruppen',
                    'DYMO-Druck: automatischer Fallback PrintLabel2 -> PrintLabel bei aelteren Diensten',
                    'Gutschein-Pruefung zeigt Fehler jetzt sichtbar an',
                ],
            ],
            // --- v2.4.0 (14.06.2026) ---
            [
                'platform' => 'web',
                'version' => '2.4.0',
                'release_date' => '2026-06-14',
                'changes' => [
                    'In-App-Updater Backend: APK-Upload im Admin-Panel (Versionshistorie)',
                    'Felder version_code, APK-Datei, Groesse und Pflicht-Update pro Version',
                    'API: /api/v1/app-version/latest liefert neueste installierbare Version',
                    'API: /api/v1/app-version/download/{version} streamt die APK mit korrektem MIME',
                ],
            ],
            [
                'platform' => 'android',
                'version' => '2.4.0',
                'release_date' => '2026-06-14',
                'changes' => [
                    'In-App-Updater: automatische Update-Pruefung beim Start',
                    '"Nach Updates suchen"-Button in den Einstellungen',
                    'Update-Dialog mit Changelog, APK-Download mit Fortschrittsanzeige',
                    'Installation ueber den Android-Paketinstaller, Pflicht-Updates moeglich',
                ],
            ],
            // --- v2.5.0 (14.06.2026) ---
            [
                'platform' => 'android',
                'version' => '2.5.0',
                'release_date' => '2026-06-14',
                'changes' => [
                    'NFC-Admin: persoenliche Anmeldung mit PIN + Protokoll (wer beschreibt Chips)',
                    'Vollbild: obere Statusleiste wird ausgeblendet',
                    'Warnungen faerben die betroffene Kachel (rot/gelb) und sortieren sie nach oben',
                    'Setup-Screen scrollbar (VERBINDEN-Button immer erreichbar)',
                    'Einstellungs-Passwort wie NFC: aktuelles Datum rueckwaerts',
                ],
            ],

            // --- v2.6.0 (14.06.2026) ---
            [
                'platform' => 'web',
                'version' => '2.6.0',
                'release_date' => '2026-06-14',
                'changes' => [
                    'Abschriften-System: Warenverluste aus der POS-App erfassen und auswerten',
                    'Dashboard-Liste aller Abschriften mit Filtern (Station, Grund, Quelle, Zeitraum)',
                    'PDF-Tagesbericht gruppiert nach Grund mit EK/VK-Summen und Zusammenfassung',
                    'Berichts-Archiv: erzeugte PDF-Berichte werden protokolliert und sind herunterladbar',
                    'Neue Datenquelle depreciation_entries mit EK/VK-Snapshot zum Erfassungszeitpunkt',
                ],
            ],
            [
                'platform' => 'android',
                'version' => '2.6.0',
                'release_date' => '2026-06-14',
                'changes' => [
                    'Neues Modul "Abschriften": Warenverluste direkt am Geraet erfassen',
                    'Einzel- und Sammel-Erfassung (Batch) per Scan',
                    'Grund auswaehlen, Menge erfassen; Einkaufs-/Verkaufspreis automatisch',
                    'MHD abschreiben mit Mengeneingabe (0 = nur als geprueft markiert, nicht abgeschrieben)',
                ],
            ],

            // --- v2.6.5 (15.06.2026) ---
            [
                'platform' => 'web',
                'version' => '2.6.5',
                'release_date' => '2026-06-15',
                'changes' => [
                    'DYMO-Druck-Ueberarbeitung: EIN zuverlaessiger Druckweg ueber eine Warteschlange',
                    'Neuer "ROSI Print"-Agent (Windows-Tray) druckt lokal am Stations-PC -> kein Firewall-/Netz-Thema',
                    'Dashboard: Druck-Agenten verwalten (Token erzeugen, Online-Status, gemeldete Drucker)',
                    'Drucker-Zuordnung pro Station als Auswahlfelder (Job-Typ -> Drucker)',
                    'Dashboard: Druckauftraege-Liste mit Status, Fehlern und Wiederholung; Testdruck-Knopf',
                    'Warteschlange mit Ablaufzeit (TTL), Retry und automatischem Aufraeumen haengender Jobs',
                    'In-App-Updater abgesichert: version_code wird automatisch aus der APK gelesen',
                ],
            ],

            // --- v2.6.9 (15.06.2026) ---
            [
                'platform' => 'web',
                'version' => '2.6.9',
                'release_date' => '2026-06-15',
                'changes' => [
                    'Gutschein-, Tankbetrug- und Tresor-Druck laufen ueber die Druck-Warteschlange (Stations-Agent)',
                    'Schichtabrechnung-Detailansicht im modernen Karten-Layout aufgeraeumt',
                    'Schichtabrechnung-PDF: Kassenbon-/Bon-Fotos (auch WebP) werden korrekt eingebettet',
                    'Tresor-Etikett: Platzhalter Station/Mitarbeiter/Zeit werden korrekt gefuellt',
                ],
            ],
            [
                'platform' => 'android',
                'version' => '2.6.9',
                'release_date' => '2026-06-15',
                'changes' => [
                    'Etikettendruck laeuft jetzt ueber den Stations-Drucker (Gutschein, Tankbetrug, Tresor) - kein Tablet-Druck mehr',
                    'Keine "Kein Drucker konfiguriert"-Meldung mehr nach dem Erfassen',
                    'Hinweis in der App: "Etikett wird am Stations-Drucker gedruckt"',
                    'Unterschrift-Vollbild (Tankbetrug): Buttons wieder sichtbar, Feld zentriert',
                    'Abschriften-Scan: sofortige Suche ohne Enter, Feld leeren + Fokus zurueck ins Feld',
                    'Batch-Abschriften: zuletzt gescannter Artikel oben, schnelles Scannen ohne verschmolzene EANs',
                    'Standard-Abschreibgrund "MHD-Ueberschreitung" vorausgewaehlt (sonst Pflichtauswahl)',
                ],
            ],

            // --- v2.7.0 (Web) / v2.7.8 (App) — 17.06.2026 ---
            [
                'platform' => 'web',
                'version' => '2.7.0',
                'release_date' => '2026-06-17',
                'changes' => [
                    'Etiketten-Vorlagen erweitert: Tresor "Modern" + "Klassisch", Tankbetrug "Detailliert", Adress-Etikett "Brief", neues "Stationen/Monat"',
                    'Fix: DYMO-Etiketten (Adresse/Tankbetrug) druckten nicht (HTTP 400) - Vorlagen auf DYMOLabel Version 4 umgestellt',
                    'Druckvorlagen-Seite: "Demo drucken" laeuft ueber die Warteschlange (Stations-Agent) statt ueber den Browser; Drucker-Auswahl',
                    'Adress-Etiketten: Backend fuer Speichern + Nachdrucken (eigene Station als Absender, Adress-Suche)',
                ],
            ],
            [
                'platform' => 'android',
                'version' => '2.8.0',
                'release_date' => '2026-06-17',
                'changes' => [
                    'Partner-Login: geschuetzter Bereich (Einstellungen) oeffnet sich fuer Partner/Inhaber ohne Passwort',
                    'Neu: Adress-Etiketten drucken - eigene Tankstelle als Absender, Empfaengeradresse mit intelligenter Adress-Suche',
                    'Adress-Etiketten werden gespeichert und koennen jederzeit erneut gedruckt werden (mit Druckzaehler)',
                    'Adress-Etiketten als Startbildschirm-Kachel - nur fuer Partner/Inhaber sichtbar',
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
