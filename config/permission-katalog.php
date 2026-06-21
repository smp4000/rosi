<?php

/*
|--------------------------------------------------------------------------
| Permission-Katalog — die EINZIGE Quelle fuer Rechte
|--------------------------------------------------------------------------
| Ausfuehrliche Doku: docs/ROLLENSYSTEM.md
|
| Jede Berechtigung im System heisst "<key>.<aktion>", z.B.
| "partner.gas-stations.create" oder "mde.vouchers.issue".
| Diese Namen entstehen NUR aus dieser Datei — nirgendwo sonst im Code
| werden Rechte definiert.
|
|--------------------------------------------------------------------------
| REZEPT: Neue Resource absichern (z.B. Lieferanten) — 3 Schritte
|--------------------------------------------------------------------------
| 1. Hier unter 'ressourcen' einen Block ergaenzen:
|
|        'partner.suppliers' => [
|            'label' => 'Lieferanten', 'bereich' => 'dashboard', 'emoji' => '🚚',
|            'aktionen' => [
|                'list'   => 'Liste ansehen',
|                'create' => 'Anlegen',
|                'edit'   => 'Bearbeiten',
|                'delete' => 'Loeschen',
|            ],
|            'defaults' => [ 'buero' => ['list', 'create', 'edit'] ],
|        ],
|
| 2. In der Filament-Resource GENAU ZWEI Zeilen:
|
|        use \App\Filament\Concerns\HasCatalogPermissions;
|        protected static ?string $permissionKey = 'partner.suppliers';
|
| 3. Deploy: php artisan rosi:permissions-sync
|
| Danach automatisch (NICHTS weiter zu tun):
| - Zeile in der Rollen-Matrix (Einstellungen -> Rollen & Rechte)
| - Menuepunkt/Buttons werden ohne Recht ausgeblendet (AppServiceProvider)
| - URL-Direktaufruf ohne Recht -> 403
| - Inhaber-Rolle "partner" darf alles, defaults gelten fuer System-Rollen
|
|--------------------------------------------------------------------------
| WELCHE AKTION STEUERT WAS IN DER OBERFLAECHE?
|--------------------------------------------------------------------------
| 'list'         -> MENUEPUNKT in der Navigation + Uebersichtstabelle.
|                   Ohne 'list' ist das Modul komplett unsichtbar.
| 'view'         -> "Ansehen"-Button pro Zeile + Detailseite
| 'create'       -> "Anlegen"-Button + Anlegen-Seite
| 'edit'         -> "Bearbeiten"-Button pro Zeile + Bearbeiten-Seite
| 'delete'       -> Loesch-/Archivieren-Button pro Zeile
| 'delete-any'   -> Sammel-Loeschen (Checkbox-Auswahl in der Tabelle)
| 'restore'      -> "Wiederherstellen"-Button bei archivierten Eintraegen
| 'force-delete' -> Endgueltig loeschen (falls die Resource das anbietet)
| frei waehlbar  -> Spezial-Buttons ('send', 'approve', 'import', ...) —
|                   im Code dann: ->visible(fn () => static::katalogPermission('send'))
|
| FALLBACKS (HasCatalogPermissions): Fehlt 'list', nimmt der Menuepunkt
| automatisch 'view'/'use'/'manage'/'logs'. Fehlen 'delete-any'/'restore'/
| 'force-delete', gilt jeweils 'delete'. Fehlt eine Aktion KOMPLETT im
| Katalog, ist sie NICHT erlaubt (z.B. kein 'create' bei Gutscheinen).
|
|--------------------------------------------------------------------------
| SONDERFAELLE
|--------------------------------------------------------------------------
| Custom-Page (kein Resource):
|     use \App\Filament\Concerns\HasPageCatalogPermission;
|     protected static string $accessPermission = 'partner.settings.manage';
|
| Dashboard-Widget:
|     use \App\Filament\Concerns\HasWidgetCatalogPermission;
|     protected static string $accessPermission = 'partner.dashboard.stats';
|
| MDE-Modul (POS-App): Block mit 'bereich' => 'mde' anlegen, dann
| - API-Route schuetzen:  ->middleware('mde.permission:mde.xyz.aktion')
| - App-Kachel mappen:    rosi_app HomeScreen.kt -> modulePermissions
| Die App blendet Kacheln nur aus — die SICHERHEIT ist die Middleware.
| Rechte kommen beim Login mit (permissions[]), gelten bis zum naechsten Login.
|
|--------------------------------------------------------------------------
| SYNC-VERHALTEN (rosi:permissions-sync)
|--------------------------------------------------------------------------
| - Idempotent: mehrfaches Ausfuehren aendert nichts.
| - NEUE Permissions bekommen ihre defaults automatisch — auch bei
|   bestehenden Mandanten. Bestehende Haekchen der Partner werden NIE
|   ueberschrieben (Anpassungen in der Matrix bleiben erhalten).
| - 'partner' (Inhaber) bekommt IMMER alle Katalog-Permissions und steht
|   deshalb nicht in den defaults.
| - Eigene Rollen der Partner bekommen neue Permissions NIE automatisch
|   (sicherer Standard — der Partner schaltet bewusst frei).
| - --apply-defaults: Werkseinstellungen erneut ADDITIV anwenden (nur
|   fuer Erst-Rollout gedacht; stellt entzogene Default-Rechte wieder her!)
| - --prune: verwaiste Permissions loeschen (sonst nur Warnung im Log).
|
|--------------------------------------------------------------------------
| STOLPERFALLEN (haben uns echte Bugs gekostet!)
|--------------------------------------------------------------------------
| 1. Die Keys hier enthalten PUNKTE ('partner.gas-stations'). Deshalb NIE
|    config('permission-katalog.ressourcen.partner.gas-stations') per
|    Dot-Notation lesen — immer erst config('permission-katalog.ressourcen')
|    als ganzes Array holen und dann mit [$key] zugreifen.
| 2. NIEMALS canAccess()/canViewAny() { return true; } in eine Resource
|    schreiben — Klassen-Methoden uebersteuern den Trait und hebeln die
|    Matrix aus (so waren Artikel/Artikelgruppen fuer jeden sichtbar).
| 3. Rolle 'mitarbeiter' = Legacy "nur Web-Ansicht", hat KEINE MDE-Module.
|    Normales Stationspersonal bekommt die Rolle 'kassierer'.
| 4. Namensschema: partner.<ressource>.<aktion> (Dashboard),
|    mde.<modul>.<aktion> (POS-App). Die 27 alten partner.*-Namen aus dem
|    RolesAndPermissionsSeeder sind 1:1 uebernommen — nicht umbenennen,
|    sonst verlieren bestehende Rollen ihre Haekchen.
*/

return [

    // Anzeige-Reihenfolge und Labels der Bereiche in der Matrix
    'bereiche' => [
        'dashboard' => 'Dashboard',
        'mde' => 'MDE-App',
    ],

    // System-Rollen: werden pro Mandant angelegt, sind anpassbar aber nicht loeschbar.
    // 'partner' und 'mitarbeiter' existieren bereits (createTenantRoles) und werden
    // vom Sync als System-Rollen markiert.
    'system_rollen' => [
        'partner' => 'Inhaber / Partner',
        'stationsleiter' => 'Stationsleiter',
        'schichtleiter' => 'Schichtleiter',
        'kassierer' => 'Kassierer',
        'buero' => 'Buero',
        'mitarbeiter' => 'Mitarbeiter (nur Web-Ansicht)',
    ],

    'ressourcen' => [

        // ── Dashboard ────────────────────────────────────────────────

        'partner.dashboard' => [
            'label' => 'Dashboard', 'bereich' => 'dashboard', 'emoji' => '🏠',
            'aktionen' => [
                'view' => 'Dashboard ansehen',
                'stats' => 'Statistik-Karten (Tankstellen, Mitarbeiter, ...)',
                'alerts' => 'Warnungen (Tankbetrug, MHD)',
                'quick-actions' => 'Schnellaktionen (Anlegen, Import)',
            ],
            'defaults' => [
                'stationsleiter' => ['view', 'stats', 'alerts', 'quick-actions'],
                'buero' => ['view', 'stats'],
            ],
        ],

        'partner.gas-stations' => [
            'label' => 'Tankstellen', 'bereich' => 'dashboard', 'emoji' => '⛽',
            'aktionen' => [
                'list' => 'Liste ansehen', 'view' => 'Details ansehen',
                'create' => 'Anlegen', 'edit' => 'Bearbeiten', 'delete' => 'Loeschen',
                'delete-any' => 'Mehrere loeschen (Auswahl)',
            ],
            'defaults' => [
                'stationsleiter' => ['list', 'view'],
                'buero' => ['list', 'view'],
            ],
        ],

        'partner.employees' => [
            'label' => 'Mitarbeiter', 'bereich' => 'dashboard', 'emoji' => '👥',
            'aktionen' => [
                'list' => 'Liste ansehen', 'view' => 'Details ansehen',
                'create' => 'Anlegen', 'edit' => 'Bearbeiten', 'delete' => 'Loeschen / Archivieren',
                'restore' => 'Wiederherstellen (aus Archiv)',
                'invite' => 'Einladen (Onboarding)',
            ],
            'defaults' => [
                'stationsleiter' => ['list', 'view', 'create', 'edit', 'invite'],
            ],
        ],

        'partner.invitations' => [
            'label' => 'Einladungen', 'bereich' => 'dashboard', 'emoji' => '✉️',
            'aktionen' => [
                'list' => 'Liste ansehen', 'create' => 'Erstellen', 'delete' => 'Loeschen',
                'delete-any' => 'Mehrere loeschen (Auswahl)',
            ],
            'defaults' => [
                'stationsleiter' => ['list', 'create'],
            ],
        ],

        'partner.customers' => [
            'label' => 'Kunden', 'bereich' => 'dashboard', 'emoji' => '🧑‍🤝‍🧑',
            'aktionen' => [
                'list' => 'Liste ansehen', 'view' => 'Details ansehen',
                'create' => 'Anlegen', 'edit' => 'Bearbeiten',
            ],
            'defaults' => [
                'stationsleiter' => ['list', 'view', 'create', 'edit'],
                'buero' => ['list', 'view', 'create', 'edit'],
            ],
        ],

        'partner.documents' => [
            'label' => 'Dokumente', 'bereich' => 'dashboard', 'emoji' => '📄',
            'aktionen' => [
                'list' => 'Liste ansehen', 'view' => 'Details ansehen',
                'create' => 'Erstellen', 'edit' => 'Bearbeiten', 'delete' => 'Loeschen',
                'sign' => 'Unterschreiben', 'send' => 'Versenden',
            ],
            'defaults' => [
                'stationsleiter' => ['list', 'view', 'create', 'send'],
                'buero' => ['list', 'view', 'create', 'edit', 'send'],
            ],
        ],

        'partner.templates' => [
            'label' => 'Dokumentvorlagen', 'bereich' => 'dashboard', 'emoji' => '📋',
            'aktionen' => [
                'list' => 'Liste ansehen', 'view' => 'Details ansehen',
                'create' => 'Erstellen', 'edit' => 'Bearbeiten', 'delete' => 'Loeschen',
                'delete-any' => 'Mehrere loeschen (Auswahl)',
            ],
            'defaults' => [
                'stationsleiter' => ['list', 'view'],
                'buero' => ['list', 'view', 'create', 'edit'],
            ],
        ],

        'partner.articles' => [
            'label' => 'Artikel & Gruppen', 'bereich' => 'dashboard', 'emoji' => '📦',
            'aktionen' => [
                'list' => 'Liste ansehen', 'create' => 'Anlegen',
                'edit' => 'Bearbeiten', 'delete' => 'Loeschen',
                'delete-any' => 'Mehrere loeschen (Auswahl)', 'import' => 'CSV-Import',
            ],
            'defaults' => [
                'stationsleiter' => ['list'],
                'buero' => ['list', 'create', 'edit', 'import'],
            ],
        ],

        'partner.invoices' => [
            'label' => 'Rechnungen', 'bereich' => 'dashboard', 'emoji' => '🧾',
            'aktionen' => [
                'list' => 'Liste ansehen', 'view' => 'Details ansehen',
                'import' => 'Importieren (ZUGFeRD)', 'send' => 'Versenden',
                'delete' => 'Loeschen', 'delete-any' => 'Mehrere loeschen (Auswahl)',
                'settings' => 'Versand-Einstellungen',
            ],
            'defaults' => [
                'buero' => ['list', 'view', 'import', 'send'],
            ],
        ],

        'partner.corporate-customers' => [
            'label' => 'Firmenkunden & Tankkarten', 'bereich' => 'dashboard', 'emoji' => '🏢',
            'aktionen' => [
                'list' => 'Liste ansehen', 'view' => 'Details ansehen',
                'create' => 'Anlegen', 'edit' => 'Bearbeiten', 'delete' => 'Loeschen',
                'delete-any' => 'Mehrere loeschen (Auswahl)',
            ],
            'defaults' => [
                'buero' => ['list', 'view', 'create', 'edit'],
            ],
        ],

        'partner.vouchers' => [
            'label' => 'Gutscheine', 'bereich' => 'dashboard', 'emoji' => '🎟️',
            'aktionen' => [
                'list' => 'Liste ansehen',
                'issue' => 'Ausgeben (Web-Druck)',
            ],
            'defaults' => [
                'stationsleiter' => ['list', 'issue'],
                'buero' => ['list'],
            ],
        ],

        'partner.devices' => [
            'label' => 'POS-Geraete', 'bereich' => 'dashboard', 'emoji' => '📱',
            'aktionen' => [
                'list' => 'Liste ansehen', 'approve' => 'Freigeben / Ablehnen',
                'edit' => 'Aktivieren / Deaktivieren', 'delete' => 'Loeschen',
                'delete-any' => 'Mehrere loeschen (Auswahl)',
            ],
            'defaults' => [
                'stationsleiter' => ['list', 'approve', 'edit'],
            ],
        ],

        'partner.suppliers' => [
            'label' => 'Lieferanten', 'bereich' => 'dashboard', 'emoji' => '🚚',
            'aktionen' => [
                'list' => 'Liste ansehen', 'create' => 'Anlegen',
                'edit' => 'Bearbeiten', 'delete' => 'Loeschen',
            ],
            'defaults' => [
                'stationsleiter' => ['list'],
                'buero' => ['list', 'create', 'edit'],
            ],
        ],

        'partner.newspapers' => [
            'label' => 'Zeitungen / Kiosk', 'bereich' => 'dashboard', 'emoji' => '📰',
            'aktionen' => [
                'list' => 'Ansehen', 'import' => 'Importieren', 'edit' => 'Bearbeiten',
            ],
            'defaults' => [
                'stationsleiter' => ['list', 'import', 'edit'],
                'buero' => ['list', 'import', 'edit'],
            ],
        ],

        'partner.shift-settlements' => [
            'label' => 'Schichtabrechnungen', 'bereich' => 'dashboard', 'emoji' => '💶',
            'aktionen' => [
                'list' => 'Liste ansehen', 'view' => 'Details ansehen',
                'edit' => 'Bearbeiten', 'delete' => 'Loeschen',
            ],
            'defaults' => [
                'stationsleiter' => ['list', 'view'],
                'buero' => ['list', 'view'],
            ],
        ],

        'partner.fuel-thefts' => [
            'label' => 'Tankbetrug', 'bereich' => 'dashboard', 'emoji' => '🚨',
            'aktionen' => [
                'list' => 'Liste ansehen', 'edit' => 'Bearbeiten', 'delete' => 'Loeschen',
            ],
            'defaults' => [
                'stationsleiter' => ['list', 'edit'],
                'buero' => ['list'],
            ],
        ],

        'partner.mhd' => [
            'label' => 'MHD-Kontrolle', 'bereich' => 'dashboard', 'emoji' => '📅',
            'aktionen' => ['list' => 'Liste ansehen', 'edit' => 'Bearbeiten'],
            'defaults' => [
                'stationsleiter' => ['list', 'edit'],
            ],
        ],

        'partner.temperature' => [
            'label' => 'Temperatur / Kühlmöbel', 'bereich' => 'dashboard', 'emoji' => '🌡️',
            'aktionen' => [
                'list' => 'Anzeigen',
                'manage' => 'Kühlmöbel verwalten',
                'ack' => 'Störungen quittieren',
                'settings' => 'Einstellungen',
            ],
            'defaults' => [
                'stationsleiter' => ['list', 'manage', 'ack'],
                'buero' => ['list'],
            ],
        ],

        'partner.depreciations' => [
            'label' => 'Abschriften', 'bereich' => 'dashboard', 'emoji' => '📉',
            'aktionen' => [
                'list' => 'Liste ansehen',
                'report' => 'Bericht erstellen',
                'delete' => 'Loeschen',
            ],
            'defaults' => [
                'stationsleiter' => ['list', 'report'],
                'buero' => ['list'],
            ],
        ],

        'partner.reports' => [
            'label' => 'Berichte & Archiv', 'bereich' => 'dashboard', 'emoji' => '🗂️',
            'aktionen' => [
                'list' => 'Liste ansehen',
                'download' => 'Herunterladen',
                'delete' => 'Loeschen',
            ],
            'defaults' => [
                'stationsleiter' => ['list', 'download'],
                'buero' => ['list', 'download'],
            ],
        ],

        'partner.print' => [
            'label' => 'Drucken & Etiketten', 'bereich' => 'dashboard', 'emoji' => '🖨️',
            'aktionen' => [
                'logs' => 'Druckprotokoll ansehen', 'settings' => 'Drucker-Einstellungen',
            ],
            'defaults' => [
                'stationsleiter' => ['logs', 'settings'],
            ],
        ],

        'partner.chat' => [
            'label' => 'Chat / Nachrichten', 'bereich' => 'dashboard', 'emoji' => '💬',
            'aktionen' => ['use' => 'Nutzen'],
            'defaults' => [
                'stationsleiter' => ['use'],
                'buero' => ['use'],
            ],
        ],

        'partner.settings' => [
            'label' => 'Einstellungen (Mail, Messaging)', 'bereich' => 'dashboard', 'emoji' => '⚙️',
            'aktionen' => ['manage' => 'Verwalten'],
            'defaults' => [],
        ],

        'partner.roles' => [
            'label' => 'Rollen & Rechte', 'bereich' => 'dashboard', 'emoji' => '🛡️',
            'aktionen' => ['manage' => 'Matrix verwalten'],
            'defaults' => [],
        ],

        // ── MDE-App ──────────────────────────────────────────────────

        'mde.article-info' => [
            'label' => 'Artikelinfo', 'bereich' => 'mde', 'emoji' => '🔎',
            'aktionen' => ['use' => 'Nutzen (Scannen & Suchen)'],
            'defaults' => [
                'kassierer' => ['use'],
                'schichtleiter' => ['use'],
                'stationsleiter' => ['use'],
            ],
        ],

        'mde.mhd' => [
            'label' => 'MHD-Kontrolle', 'bereich' => 'mde', 'emoji' => '📅',
            'aktionen' => ['record' => 'Erfassen', 'list' => 'Liste ansehen'],
            'defaults' => [
                'kassierer' => ['record', 'list'],
                'schichtleiter' => ['record', 'list'],
                'stationsleiter' => ['record', 'list'],
            ],
        ],

        'mde.writeoffs' => [
            'label' => 'Abschriften', 'bereich' => 'mde', 'emoji' => '🗑️',
            'aktionen' => ['record' => 'Erfassen'],
            'defaults' => [
                'kassierer' => ['record'],
                'schichtleiter' => ['record'],
                'stationsleiter' => ['record'],
            ],
        ],

        'mde.temperatures' => [
            'label' => 'Temperaturen', 'bereich' => 'mde', 'emoji' => '🌡️',
            'aktionen' => ['record' => 'Erfassen'],
            'defaults' => [
                'kassierer' => ['record'],
                'schichtleiter' => ['record'],
                'stationsleiter' => ['record'],
            ],
        ],

        'mde.fuel-theft' => [
            'label' => 'Tankbetrug', 'bereich' => 'mde', 'emoji' => '🚨',
            'aktionen' => ['report' => 'Melden'],
            'defaults' => [
                'kassierer' => ['report'],
                'schichtleiter' => ['report'],
                'stationsleiter' => ['report'],
            ],
        ],

        'mde.shift-settlement' => [
            'label' => 'Schichtabrechnung', 'bereich' => 'mde', 'emoji' => '💶',
            'aktionen' => [
                'own' => 'Eigene durchfuehren',
                'view-all' => 'Alle ansehen',
            ],
            'defaults' => [
                'kassierer' => ['own'],
                'schichtleiter' => ['own', 'view-all'],
                'stationsleiter' => ['own', 'view-all'],
            ],
        ],

        'mde.my-shifts' => [
            'label' => 'Meine Schichten', 'bereich' => 'mde', 'emoji' => '🕐',
            'aktionen' => ['view' => 'Ansehen'],
            'defaults' => [
                'kassierer' => ['view'],
                'schichtleiter' => ['view'],
                'stationsleiter' => ['view'],
            ],
        ],

        'mde.newspapers' => [
            'label' => 'Zeitungen', 'bereich' => 'mde', 'emoji' => '📰',
            'aktionen' => [
                'delivery' => 'Lieferung erfassen',
                'remission' => 'Remission erfassen',
                'inventory' => 'Inventur durchfuehren',
            ],
            'defaults' => [
                'schichtleiter' => ['delivery', 'remission', 'inventory'],
                'stationsleiter' => ['delivery', 'remission', 'inventory'],
            ],
        ],

        'mde.vouchers' => [
            'label' => 'Gutscheine', 'bereich' => 'mde', 'emoji' => '🎟️',
            'aktionen' => [
                'redeem' => 'Einloesen',
                'issue' => 'Ausgeben (drucken)',
                'reprint' => 'Etikett nachdrucken',
            ],
            'defaults' => [
                'kassierer' => ['redeem'],
                'schichtleiter' => ['redeem', 'issue', 'reprint'],
                'stationsleiter' => ['redeem', 'issue', 'reprint'],
            ],
        ],

        'mde.issues' => [
            'label' => 'Stoerungen', 'bereich' => 'mde', 'emoji' => '🔧',
            'aktionen' => [
                'report' => 'Melden',
                'edit' => 'Bearbeiten',
                'close' => 'Schliessen',
            ],
            'defaults' => [
                'kassierer' => ['report'],
                'schichtleiter' => ['report', 'edit'],
                'stationsleiter' => ['report', 'edit', 'close'],
            ],
        ],

        'mde.admin' => [
            'label' => 'Admin-Bereich', 'bereich' => 'mde', 'emoji' => '📡',
            'aktionen' => ['nfc-write' => 'NFC-Chips beschreiben'],
            'defaults' => [
                'stationsleiter' => ['nfc-write'],
            ],
        ],

    ],
];
