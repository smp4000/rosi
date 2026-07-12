# ROSI – Architektur-Überblick

> Zweck dieses Dokuments: In 10 Minuten verstehen, wie das System zusammenhängt —
> auch wenn man den Code monatelang nicht angefasst hat.
> Stand: 13.07.2026 · bei größeren Umbauten bitte MITPFLEGEN.

---

## 1. Die drei Bausteine

```
┌────────────────────┐   HTTPS/JSON    ┌──────────────────────────┐
│  ROSI POS App      │ ──────────────► │  ROSI Backend (Laravel)  │
│  (Android, Kotlin/ │                 │  rosi.aral-welle.com     │
│   Compose)         │ ◄────────────── │  All-Inkl, PHP 8.3       │
│  C:\...\rosi_app   │   APK-Updater   │  C:\...\rosi             │
└────────┬───────────┘                 └──────────┬───────────────┘
         │ Bluetooth (TSPL)                       │ Poll (HTTPS)
         ▼                                        ▼
   TSC-Etikettendrucker              ┌──────────────────────────┐
   (Station OHNE PC)                 │ "ROSI Print" Tray-Agent  │
                                     │ (.NET 8, Windows-PC an   │
                                     │  der Station)            │
                                     │ tools/RosiPrintAgent     │
                                     └──────────┬───────────────┘
                                                ▼
                                     DYMO / TSC / Brother (lokal)
```

- **Backend** (dieses Repo): Laravel 12 + Filament 5. Zwei Panels:
  `/admin` (Super-Admin) und `/dashboard` (Partner + Büro). API unter `/api/v1`.
- **App** (`rosi_app`, eigenes Repo): MDE-/Handy-App für das Stations-Personal.
  Verteilt sich selbst über den **In-App-Updater** (Admin lädt APK hoch,
  App prüft `/app-version/latest`). Debug-Key-Signatur beibehalten!
- **Print-Agent** (`tools/RosiPrintAgent`): Tray-App am Stations-PC. Holt
  Druckjobs aus der Queue und druckt lokal (DYMO-WebService, TSC raw, Brother).

---

## 2. Authentifizierung — die drei Stufen

Alles Detail in `routes/api.php` (Kopf-Kommentar), Kurzfassung:

| Stufe | Ausweis | Wofür |
|---|---|---|
| 1. Geräte-Token (`device_token`) | Dauer-Token je registriertem Gerät, bcrypt-Hash + HMAC-Lookup in `devices` | **Lesen** (Artikel-Suche, MHD-Liste, Temperaturen, Drucker) |
| 2. Session-Token (Sanctum Bearer, 12 h) | Mitarbeiter-Login per PIN/Scan am Gerät | **Schreiben** (Gutscheine, MHD anlegen, Abschriften, Schicht) |
| 3. Agent-Token | eigener Token je Print-Agent (`print_agents`) | Nur Agent-Endpunkte (heartbeat/claim/ack) |

Wichtige Klassen:
- `Device::findByPlainToken()` – schnelle Token-Suche (A-4: indizierter
  HMAC-„Wegweiser" + bcrypt-Verifikation). **Nie eigene Loops schreiben!**
- `AuthController` – Login (PIN + Stations-Check) / scanLogin.
- `CheckApiAccess` – prüft Abo/Trial des Mandanten (Stufe-2-Routen).

---

## 3. Mandantentrennung (Multi-Tenancy)

Shared Database, jede Tabelle hat `tenant_id`. Drei Mechanismen greifen ineinander
(T-1/T-2 aus dem Sicherheits-Audit 07/2026):

1. **`App\Support\TenantContext`** – scoped Singleton, „welcher Mandant ist
   gerade aktiv". Wird gesetzt durch:
   - Web: Session (`EnsureTenantContext` beim Panel-Login)
   - API: `SetApiTenantContext`-Middleware (aus Sanctum-User oder device_token)
   - Jobs/Commands: explizit `app(TenantContext::class)->set(...)` / `runFor(...)`
2. **`App\Scopes\TenantScope`** – hängt automatisch `WHERE tenant_id = ?` an.
   Liest Kontext zuerst, Session als Fallback. Kein Kontext = kein Filter
   (Super-Admin, CLI-Poller).
3. **`App\Traits\BelongsToTenant`** – aktiviert den Scope am Model + füllt
   `tenant_id` beim Erstellen automatisch. Im Trait-DocBlock steht die Liste
   der **bewusst globalen Models** (System-Kataloge etc.) — nicht „reparieren"!

**Absicherung:** `tests/Feature/TenantIsolationTest.php` („Mandant A sieht nie
Mandant B", Ende-zu-Ende). Läuft gegen MySQL-DB `rosi_test`:
`php artisan test --filter=TenantIsolationTest`

---

## 4. Drucken — EIN Weg über die Queue (+ Bluetooth-Ausnahme)

**Normalfall (Station mit PC):**
```
Feature (z.B. Gutschein-Ausgabe)
  → LabelTemplate (DB-Vorlage, Platzhalter {{...}})
  → PrintQueueService::enqueue()  (print_jobs, TTL, Ziel-Agent/Drucker)
  → Agent pollt /print/agent/jobs/claim → druckt → ack
```
- **DYMO**: DesktopLabel-XML, **zwingend `DYMOLabel Version="4"`** (V3 = HTTP 400).
- **TSC (DA210)**: TSPL-Vorlagen (`gutschein-tsc`), `CODEPAGE 1252`,
  **€ = Byte 0x80**, Umlaute via Latin-1, ALLE Etiketten eines Jobs in
  EINEM Raw-Job (sonst Spooler-Aussetzer).
- **Zuordnung**: Pro MDE-Gerät `devices.print_default` + `print_alternatives`
  (Dashboard-Aktion „Drucker" ODER App-Einstellungen).

**Ausnahme (Station ohne PC):** App druckt **direkt per Bluetooth-SPP** an den
TSC. Backend liefert dazu fertige TSPL-Etiketten (`direct_print=true` bei
generate/reprint), App sendet sie roh (`BluetoothPrinters.kt`, € → 0x80).

**Agent-Lebenszyklus:** Stations-Installer (Inno Setup) + `enroll.json` →
Self-Register → Partner gibt im Dashboard frei → Heartbeat meldet Drucker →
stilles Auto-Update über AppVersion (platform `print-agent`).

---

## 5. Temperatur-Management (HACCP)

```
Mobile-Alerts-Funksensoren → data199.com API (NUR letzte Messung!)
  → temperatures:poll (Cron alle 5 Min, TemperaturePollService)
     → cooling_readings (Historie — die API hat KEINE, wir bauen sie selbst)
     → Grenzwert-Auswertung (ROSI-eigene Soll-Werte, ARAL-HQM-Presets)
     → cooling_alerts (zu warm/kalt/offline/Batterie) + FCM-Push
  → App: Gauges + Verlaufsdiagramm (/temperatures/*)
  → Dashboard: Kühlmöbel-Verwaltung, Störungen + Maßnahmen-Doku (ARAL-Katalog)
```
- Rate-Limit data199: 3 Abrufe/Sensor/Minute → **nur der Server pollt**, gebündelt.
- Grenzen werden beim Speichern automatisch min/max-sortiert (Minusgrade!).
- Maßnahmenkatalog (9 Ziffern) = `cooling_measures`, Doku am Alarm
  (`measures`, `control_value`, `ticket_number`).
- Offen: P4-App-Teil (Firebase-Dateien fehlen noch), P5 Formblatt-PDF.

---

## 6. Cron / Scheduler (All-Inkl hat kein Shell-Crontab!)

KAS-Cronjob ruft alle 5 Minuten die URL auf:
```
https://rosi.aral-welle.com/cron-run/<CRON_TOKEN>     (Token in .env)
```
Die Route führt `schedule:run` aus → `routes/console.php`:
Temperatur-Poll, Druck-Queue-Cleanup, DSGVO-Aufräumjobs.

---

## 7. Externe Dienste

| Dienst | Wofür | Klasse |
|---|---|---|
| data199.com (Mobile Alerts) | Temperatur-Sensoren | `MobileAlertsClient` |
| Firebase FCM | App-Push (Störungen) | `FcmService` (HTTP v1, eigenes JWT — kein Paket) |
| Telegram | Nachrichten/Alarme | `TelegramService` |
| Perplexity Sonar | KI-Texte | `AiTextService` |
| OpenIBAN | IBAN-Rechner | (inline in Resources) |
| Nominatim/OSM | Geocoding/Karten | `BenzinpreisService` u.a. |

**TLS-Regel (A-5):** NIE `->withoutVerifying()` benutzen. Der
`AppServiceProvider` schaltet die Prüfung NUR bei `APP_ENV=local` global ab.

---

## 8. Deploy-Runbook

```bash
# Server (SSH: w01b773f@..., Pfad s.u.)
cd /www/htdocs/w01b773f/rosi.aral-welle.com
git pull
php artisan migrate --force
php artisan optimize:clear
# bei neuen Permissions:  php artisan rosi:permissions-sync
# bei Vorlagen-Aenderung: php artisan db:seed --class=LabelTemplateSeeder
```
- **App ausliefern:** APK bauen → Admin → Versionshistorie → hochladen
  (version_code MUSS steigen) + Changelog. Geräte updaten sich selbst.
- **Agent ausliefern:** EXE als AppVersion `print-agent` hochladen — Agents
  updaten sich still. Installer zusätzlich nach `public/downloads/`.
- Alte Upload-Dateien: Admin → Versionshistorie → „Alte Uploads löschen".

---

## 9. Sicherheits-Entscheidungen (Audit 07/2026, Kurzreferenz)

| Nr | Entscheidung |
|---|---|
| S-1/S-2 | Kein Dump/keine Debug-Routen im Repo; `/cron-run` mit `hash_equals` |
| A-1 | PIN-Login erzwingt Stationszugehörigkeit (403) |
| A-2 | `voucher_redemptions.station_id` = echte Station (nicht tenant_id) |
| A-3 | Schreiben nur mit Sanctum-Session; device_token allein = nur Lesen |
| A-4 | Geräte-Token: HMAC-Lookup (indiziert) + bcrypt-Verifikation, zentral im Device-Model |
| A-5 | TLS-Verify zentral; nur lokal aus |
| T-1/T-2 | TenantContext + Scope + Trait; 9 Tabellen nachgerüstet |
| W-1 | PDF-Downloads in Tenant-Unterordnern |

Details: Memory `security-audit-2026-07.md` + Kommentare an den Klassen selbst.
