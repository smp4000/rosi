<?php

/*
|--------------------------------------------------------------------------
| Permission-Katalog — die EINZIGE Quelle fuer Rechte
|--------------------------------------------------------------------------
|
| Neues Modul? EIN Eintrag hier — alles andere passiert automatisch:
| - `php artisan rosi:permissions-sync` legt die Permissions an und verteilt
|   die Defaults an die System-Rollen (pro Mandant)
| - Die Rollen-Matrix im Partner-Panel rendert sich aus diesem Katalog
| - Policies und API-Middleware pruefen generisch "<key>.<aktion>"
|
| Namensschema: partner.<ressource>.<aktion> (Dashboard)
|               mde.<modul>.<aktion>         (POS-App)
| Die Keys der bestehenden 27 partner.*-Permissions sind 1:1 uebernommen.
|
| defaults: Welche Aktionen eine System-Rolle ab Werk bekommt.
| Die Rolle "partner" (Inhaber) bekommt IMMER alles und steht deshalb
| nicht in den defaults. Eigene Rollen der Partner bekommen neue
| Permissions grundsaetzlich NICHT automatisch (sicherer Standard).
|
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
            ],
            'defaults' => [
                'kassierer' => ['redeem'],
                'schichtleiter' => ['redeem', 'issue'],
                'stationsleiter' => ['redeem', 'issue'],
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
