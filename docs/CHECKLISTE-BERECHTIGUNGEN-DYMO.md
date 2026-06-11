# Checkliste: Berechtigungen, Rollen, MDE-Rechte, DYMO

Stand: 12.06.2026 — Ist-Analyse aus Code (rosi + rosi_app)

Legende: ✅ vorhanden · ⚠️ teilweise · ❌ fehlt

---

## 1. Berechtigungen & Rollen (Web)

### Ist-Stand
- ✅ Spatie laravel-permission v6 installiert (teams=true, tenant-faehig)
- ✅ 6 Rollen: super_admin_level_1/2/3, partner, stationsleiter, mitarbeiter
- ✅ 44 Permissions (admin.* + partner.*) im RolesAndPermissionsSeeder
- ✅ 8 Stations-Rollen im Pivot gas_station_user (station_manager, shift_leader, cashier, ...)
- ⚠️ Nur 6 Benutzer haben ueberhaupt Rollen zugewiesen
- ⚠️ Nur 4 von ~25 Partner-Resources pruefen Permissions (Invitation, Document, Employee, GasStation)

### To-Do
- [ ] **Permission-Gating fluechendeckend**: Alle Partner-Resources bekommen
      canViewAny/canCreate/canEdit/canDelete auf Basis der partner.* Permissions
      (Artikel, Rechnungen, Gutscheine, Lieferanten, Kiosk, Schichten, Geraete, ...)
- [ ] **Permissions nachziehen**: Fuer neue Module fehlen Permissions komplett
      (vouchers.*, devices.*, invoices.*, articles.*, suppliers.*, shifts.*, nfc-admin.*)
- [ ] **Rollen-Matrix UI** (User-Wunsch: grafische Matrix mit Checkboxen):
      Partner-Panel-Seite "Rollen & Rechte" — Zeilen = Permissions, Spalten = Rollen
- [ ] **Rollen-Zuweisung beim Onboarding**: Neuer Mitarbeiter bekommt automatisch
      Rolle "mitarbeiter" + Stations-Rolle; aktuell manuell/inkonsistent
- [ ] **station_role wirksam machen**: Pivot-Rolle wird gespeichert, aber nirgends
      fuer Rechte ausgewertet (weder Web noch App)
- [ ] **Stationsleiter-Rolle definieren**: Was darf er im Dashboard? (eigene Station
      sehen/bearbeiten, MA seiner Station, keine Finanzen?)
- [ ] Mitarbeiter-Portal /portal (Pflichtenheft) — rollenbasiert, noch ungebaut

---

## 2. MDE-Berechtigungen (App)

### Ist-Stand
- ✅ Geraete-Ebene: Device-Token, Freigabe-Queue (approval_status), Geraet deaktivierbar
- ✅ Login-Ebene: PIN / Scan-Code / NFC pro Mitarbeiter
- ⚠️ Modul-Sichtbarkeit nur **pro Geraet** (DataStore in den Einstellungen) —
      jeder eingeloggte MA sieht dieselben Kacheln
- ❌ Login-Response enthaelt **keine Rollen/Permissions** (nur id, name, email, type)
- ❌ Kein Modul ist nach Mitarbeiter-Rolle gesperrt (Kassierer kann z.B.
      Gutscheine generieren, Schichtabrechnungen einsehen)
- ❌ Admin-Bereich (NFC) nur durch Datum-Code geschuetzt — keine Personenbindung

### To-Do
- [ ] **API: Rollen + Permissions im Login mitgeben** (wie stationpilot4 es schon
      macht: permissions[] + roles[] in der Login-Response)
- [ ] **Berechtigungs-Matrix MDE definieren**, Vorschlag:
      | Modul              | Kassierer | Schichtleiter | Stationsleiter |
      |--------------------|-----------|---------------|----------------|
      | Artikelinfo, MHD   | ✅        | ✅            | ✅             |
      | Schichtabrechnung  | eigene    | alle          | alle           |
      | Tankbetrug melden  | ✅        | ✅            | ✅             |
      | Gutschein einloesen| ✅        | ✅            | ✅             |
      | Gutscheine ausgeben| ❌        | ✅            | ✅             |
      | Zeitungen/Kiosk    | ❌        | ✅            | ✅             |
      | Admin (NFC)        | ❌        | ❌            | ✅             |
- [ ] **App: Kacheln nach Permissions filtern** statt nur Geraete-Einstellung
      (Server liefert erlaubte Modul-Keys, HomeScreen filtert)
- [ ] **API-Endpunkte serverseitig absichern**: vouchers/generate, shift-settlements
      etc. pruefen aktuell nur Login, nicht die Rolle
- [ ] **NFC-Admin personalisieren**: Datum-Code ersetzen/ergaenzen durch
      Stationsleiter-Login (PIN), damit nachvollziehbar ist WER Chips beschreibt
- [ ] Audit-Log fuer MDE-Admin-Aktionen (wer hat wann welchen Chip beschrieben)

---

## 3. DYMO-Ueberarbeitung

### Ist-Stand — aktuell DREI parallele Druckwege:
1. ⚠️ **App direkt → DYMO am PC** (DymoClient, Ports 41951-41955, PrintLabel2/
   PrintLabel-Fallback) — scheitert oft an Firewall/HTTPS/Netz-Segmentierung
2. ⚠️ **Print-Queue**: App → Server (print_jobs) → Browser am PC pollt
   /dymo/pending-jobs → druckt — funktioniert, aber Browser-Tab muss offen sein
3. ⚠️ **Python-Proxy** (tools/dymo-proxy.py) — Workaround fuer Weg 1, manuelle
   Einrichtung (Firewall-Skript, Autostart fehlt)
- Dazu: PrintController-API (printers/test/raw/template), PrinterSettings-Page,
  LabelTemplate-Rendering serverseitig, Druck-Logik dupliziert in
  voucher-issue.blade + ShiftSettlement + FuelTheft (Code entfernt)

### To-Do
- [ ] **Architektur-Entscheidung: EIN Druckweg als Standard.**
      Empfehlung: Print-Queue (Weg 2) als einziger Weg —
      zuverlaessig, kein Firewall-Thema, Audit inklusive. Weg 1+3 entfernen
      oder als Fallback klar abgrenzen
- [ ] **Druck-Client am PC verbessern**: Statt offenem Browser-Tab eine
      kleine "ROSI Print"-Loesung (Browser-Tab mit Auto-Reconnect/Wake-Lock
      ODER Tray-App), Status-Anzeige "Drucker verbunden"
- [ ] **Print-Queue haerten**: failed-Status nutzen + Retry-Button,
      Anzeige offener Jobs im Dashboard (PrintLogResource erweitern),
      alte Jobs aufraeumen (Cleanup-Command)
- [ ] **Label-Rendering zentralisieren**: nur noch serverseitig via
      LabelTemplate::render(); doppelte XML-Bau-Logik in App/Blade entfernen
- [ ] **DymoClient in der App entschlacken**: wenn Queue Standard ist,
      braucht die App keinen direkten DYMO-Zugriff mehr (nur "Druckauftrag
      gesendet"-Feedback + Job-Status pollen)
- [ ] **Drucker-Zuordnung pro Station**: welcher DYMO haengt an welcher Station
      (printer_name in gas_stations oder PrinterSettings pro Station)
- [ ] **Testdruck-Funktion** im Dashboard (Vorlage + Testdaten → Queue)
- [ ] tools/dymo-proxy.py: entfernen oder dokumentieren (README), Autostart-Task

---

## Sonstiges offen (aus frueheren Sessions)
- [ ] rosi-Backend auf rosi.aral-welle.com deployen (Admin-API c03edc7)
- [ ] gopilot: Git-Repo anlegen + pushen
- [ ] Geraetetests: NFC-Beschreiben, Home-Redesign, Stations-QR vor Ort
- [ ] DSGVO: Klartext-Daten auf NFC-Chips nochmal bewerten
