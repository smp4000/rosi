# ROSI Print Agent

Kleine Windows-Tray-App, die offene Druckjobs einer Tankstelle aus der ROSI-Queue
holt und lokal am DYMO druckt. Ersetzt den „offenen Browser-Tab" und den direkten
App→DYMO-Druck (kein Firewall-/Netz-Thema, weil nur an `localhost` gedruckt wird).

## Funktionsweise

1. Beim Start meldet sich der Agent am Server (`heartbeat`) und schickt die Namen
   der lokal sichtbaren DYMO-Drucker.
2. Im Sekundentakt holt er offene Jobs seiner Station (`jobs/claim`) und druckt sie
   über den DYMO-Connect-Webservice (`https://localhost:41951-41955/DYMO/DLS/Printing/PrintLabel2`).
3. Erfolg/Fehler wird zurückgemeldet (`jobs/{id}/ack`).
4. Tray-Icon zeigt den Status; Doppelklick öffnet die Einstellungen.

## Voraussetzungen

- Windows 10/11
- **DYMO Connect** (oder DYMO Label Web Service) installiert und laufend
- Zum **Bauen**: .NET 8 SDK (https://aka.ms/dotnet/download). Die fertige `.exe` ist
  self-contained und braucht auf dem Ziel-PC **kein** installiertes .NET.

## Bauen

```powershell
cd tools\RosiPrintAgent
dotnet publish -c Release
```

Ergebnis (Single-File-Exe):

```
tools\RosiPrintAgent\bin\Release\net8.0-windows\win-x64\publish\RosiPrintAgent.exe
```

Diese eine Datei auf den Stations-PC kopieren und starten.

## Einrichten

1. `RosiPrintAgent.exe` starten → Einstellungen öffnen sich beim ersten Mal.
2. **Server-URL**: `https://rosi.aral-welle.com`
3. **Agent-Token**: im ROSI-Dashboard erzeugen (Drucker → Agenten → „Neuer Agent").
   *Solange das Dashboard (Phase D) fehlt, Token per Tinker erzeugen:*

   ```php
   php artisan tinker
   $st = \App\Models\GasStation::where('name','like','%<Station>%')->first();
   $a = new \App\Models\PrintAgent(['tenant_id'=>$st->tenant_id,'station_id'=>$st->id,'name'=>'Kassen-PC']);
   echo $a->generateToken(); $a->save();   // Token EINMALIG anzeigen -> in den Agent eintragen
   ```

4. Speichern → der Tray-Status sollte auf „Verbunden — bereit" wechseln.
5. Optional **„Mit Windows starten"** im Tray-Menü aktivieren (Autostart, HKCU\…\Run).

## Drucker-Zuordnung

Welcher DYMO welchen Job-Typ druckt, wird serverseitig pro Station gesetzt
(`gas_stations.printer_map`, z. B. `{ "voucher_labels": "DYMO 550 Kasse" }`).
Hat ein Job keinen Drucker, nimmt der Agent den ersten gefundenen DYMO.

## Konfigurationsdatei

`%APPDATA%\RosiPrintAgent\config.json` — enthält Server-URL + Token.

## Status-Anzeige (Tray-Tooltip)

- **Verbunden — bereit** — Server + DYMO erreichbar, keine offenen Jobs
- **Gedruckt: …** — letzter Job erfolgreich
- **Server ok · DYMO nicht gefunden** — DYMO Connect läuft nicht / kein Drucker
- **Druckfehler / Server-Fehler …** — Details im Tooltip
