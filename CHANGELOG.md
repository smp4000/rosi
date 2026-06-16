# ROSI Server - Changelog

## 17.06.2026 — Etiketten & Adress-Druck

### Adress-Etiketten (App-Partnerbereich)
- Neue Tabelle `address_labels` + Model + `AddressLabelController`
- Endpunkte: eigene Stationen als Absender, Nominatim-Adresssuche,
  Etikett anlegen+drucken, gespeichertes Etikett erneut drucken
- Druck ueber die Print-Queue (Adress-Vorlage), Speicherung zum Nachdrucken
  mit Druckzaehler

### Etiketten-Vorlagen
- Tresor-Etikett: neues Design "Modern" + altes "Klassisch" (beide auswaehlbar)
- Tankbetrug: viertes Design "Detailliert" (alle Felder inkl. Mitarbeiter+ID)
- Adress-Etikett "Brief"-Variante; auf die funktionierende Address-Rolle
  (89x28mm) umgestellt
- Neues Etikett "Stationen/Monat": grosses Datum + bis zu 4 Tankstellen
- **Fix DYMO HTTP 400**: DesktopLabel-Vorlagen jetzt `DYMOLabel Version 4`
  (HasFixedLength/LabelApplication/DataTable) — V3 wurde abgelehnt

### Druck-Queue
- Demo-Druck der Druckvorlagen-Seite laeuft ueber die Queue statt Browser-DYMO
  (Druckziel-Auswahl Agent+Drucker)
- Claim-Fallback: ziellose Jobs (Tresor/Tankbetrug) werden auch ohne
  Standard-Agent abgeholt

## v2.1.0-dev — 10.05.2026

### Zeitungen-Modul (Newspaper)
Komplettes Zeitschriften-Kiosk-Management-System fuer Tankstellen mit
PVG-Rechnungs-Import, Wareneingang, Remission und Inventur ueber die App.

**Datenbank** (12 Tabellen, alle UUID + tenant_id):
- `newspaper_articles` — Artikel-Stammdaten mit EK/VKP/MwSt/Pending-Flag/Wochentag
- `newspaper_article_issues` — Ausgaben/KW pro Artikel
- `newspaper_invoices` + `newspaper_order_lines` — PVG-Rechnungen + Positionen
  (mit `supplier_id` + `gas_station_id` FK)
- `newspaper_imports` + `newspaper_price_change_log` — Audit
- `newspaper_deliveries` + `newspaper_delivery_items` — Wareneingang pro Station
- `newspaper_remi_packages` + `newspaper_remi_items` — Remissionen (Mengen negativ)
- `newspaper_inventory_runs` + `newspaper_inventory_items` — Inventur

**Allgemeine Lieferanten-Verwaltung** (modul-uebergreifend):
- `suppliers` Tabelle erweitert um `short_code` + `is_active`
- Neue Pivot `supplier_stations` (Lieferant <-> Tankstelle + **Kundennummer**)
- `App\Models\Supplier::stations()` Many-to-Many mit Pivot

**Services**:
- `EanInspectorService` — EAN-13 Presse-Schema (419/439=7%, 414/434=19%),
  Bruttopreis aus Stelle 9-12, Pruefziffer-Validierung
- `PvgPdfParserService` — Klassischer Text-Parser fuer alte PVG-Rechnungen
- `ZugferdInvoiceParserService` — XML-Parser fuer ZUGFeRD/Factur-X (CrossIndustryInvoice
  nach EN16931). Wird bei eingebettetem XML automatisch bevorzugt
  - Liest Lieferant aus `ram:SellerTradeParty` und legt automatisch an
  - Liest Kundennummer aus `ram:BuyerTradeParty/ID`, ordnet Rechnung der
    richtigen Tankstelle ueber `supplier_stations` Pivot zu
  - Wochentag aus Bezeichnung extrahiert ("BILD Bund 1Mo" -> 1)

**API-Endpoints** (Praefix `/api/v1/kiosk` — intern weiter "kiosk" genannt):
- `GET /ping` Health-Check
- `GET /articles/lookup?ean=` EAN-Suche mit Issues + EAN-Info
- `GET /articles/by-objekt?objekt=` Objektnummer-Suche
- `POST /articles/upsert-pending` App legt unbekannten Artikel an
- `POST /deliveries/save` Wareneingang
- `POST /remissions/save` Remission (Mengen automatisch negiert)
- `POST /inventory/save` Inventur

**Filament Partner-Panel**:
- Navigation-Group "Zeitungen" mit Dashboard, Artikel, Rechnungen
- Dashboard-Page: KPIs, PDF-Upload, letzte Importe, Preisaenderungen
- NewspaperArticleResource (read-only) mit CSV-Export (Semikolon, UTF-8 BOM)
- NewspaperInvoiceResource mit Detailansicht (Lieferungen + Remissionen)
- Allgemeine SupplierResource unter Stammdaten:
  - Vollstaendige Stammdaten-Form (Firma/Kontakt/Adresse/Kategorie/Notizen)
  - StationsRelationManager: Tankstelle anhaengen + Kundennummer pflegen
  - Filter nach Kategorie + Aktiv-Status

**Artisan-Commands**:
- `php artisan kiosk:cleanup-temp [--hours=N]` — livewire-tmp aufraeumen
- `php artisan kiosk:backfill-weekdays` — Wochentag aus Bezeichnung nachtragen

**Auto-Cleanup**: livewire-tmp wird bei jedem Dashboard-Aufruf + nach jedem
PDF-Import bereinigt (Files >1 Min alt, schuetzt laufende Uploads).

**PDF-Upload**:
- smalot/pdfparser als Fallback wenn pdftotext-Binary fehlt (All-Inkl)
- wire:click="runImport" statt wire:submit (Filament FileUpload-Konflikt)
- Robuste Pfad-Aufloesung mit livewire-tmp Fallback

### Schichtabrechnung-Verbesserungen (03.05.)
- **Schicht-Auswertung im Partner-Panel** (`/dashboard/shift-settlements`):
  - Liste mit Filtern (Status, Tankstelle, Mitarbeiter, Zeitraum)
  - Detailansicht mit allen Anhaengen (Bilder via Streaming-Route)
  - PDF-Download (kompakt + Anlagen-Anhang am Ende)
  - Bearbeiten-Button (Beginn/Ende/Status/Bemerkungen)
- **Bilder-Streaming-Route** (`shift.attachment`): Bilder werden direkt
  aus dem private Storage ausgeliefert (kein storage:link noetig)
- **API-Endpoints**:
  - `GET /shift-settlements/last-values` (Defaults beim Schichtbeginn)
  - `GET /shift-settlements/mine` (eigene Schichten)
  - `GET /shift-settlements/{id}/details`
  - `POST /shift-settlements/{id}/comments` (nachtraegliche Kommentare)
- **shift_settlement_comments** Tabelle (Audit-Trail)
- **Filament Page "Schicht-Einstellungen"**: Toggle "DYMO automatisch drucken"
  in tenant_settings unter group='shift', key='auto_print_safe_label'
- **2 zusaetzliche Ruecknahme-Gruende** im Seeder:
  "Falscher Artikel Kunde wollte was anderes", "Fehlende Materialien"

## v2.0.0-dev — 02.05.2026

- Schichtabrechnung Modul (Wizard 8 Schritte)
- Tresor-Einlagen mit DYMO-Etikettdruck (DieCutLabel 8.0)
- Warenruecknahmen mit Server-Gruenden + Bon-Foto Pflicht
- HomeScreen-Logik fuer aktive Schicht
- Migration shift_return_reasons + ShiftReturnReasonResource

## v1.9.x

- Server-side Label-Render (DYMO + TSC Templates in DB)
- LAN-Druck-Gateway

## v1.7.x

- Bluetooth-Drucker (TSC), Tankbetrug-Etikett

## v1.6.x

- Tankbetrug-Modul (App + Web)

---

**Git**: master, letzter Commit `4f84785`
**Repository**: github.com/smp4000/rosi
