<?php

return [

    // --- Navigationsgruppen ---
    'nav' => [
        'uebersicht' => 'Uebersicht',
        'mandanten' => 'Mandanten-Verwaltung',
        'benutzer' => 'Benutzerverwaltung',
        'dsgvo' => 'DSGVO & Audit',
        'system' => 'System',
    ],

    // --- TenantResource ---
    'tenant' => [
        'label' => 'Mandant',
        'plural' => 'Mandanten',
        'fields' => [
            'name' => 'Firmenname',
            'slug' => 'URL-Kuerzel',
            'email' => 'E-Mail',
            'phone' => 'Telefon',
            'owner' => 'Inhaber',
            'street' => 'Strasse',
            'zip' => 'PLZ',
            'city' => 'Ort',
            'country' => 'Land',
            'tax_id' => 'Steuernummer',
            'trade_register' => 'Handelsregister',
            'subscription_status' => 'Abo-Status',
            'subscription_plan' => 'Tarif',
            'trial_ends_at' => 'Testphase endet',
            'is_active' => 'Aktiv',
            'logo' => 'Logo',
            'settings' => 'Einstellungen',
            'created_at' => 'Erstellt am',
            'updated_at' => 'Aktualisiert am',
            'users_count' => 'Benutzer',
            'gas_stations_count' => 'Tankstellen',
        ],
        'tabs' => [
            'stammdaten' => 'Stammdaten',
            'adresse' => 'Adresse',
            'abonnement' => 'Abonnement',
            'geschaeftsdaten' => 'Geschaeftsdaten',
        ],
        'subscription_statuses' => [
            'trial' => 'Testphase',
            'active' => 'Aktiv',
            'expired' => 'Abgelaufen',
            'cancelled' => 'Gekuendigt',
        ],
        'actions' => [
            'lock' => 'Sperren',
            'unlock' => 'Entsperren',
        ],
    ],

    // --- UserResource ---
    'user' => [
        'label' => 'Benutzer',
        'plural' => 'Benutzer',
        'fields' => [
            'first_name' => 'Vorname',
            'last_name' => 'Nachname',
            'name' => 'Name',
            'email' => 'E-Mail',
            'type' => 'Typ',
            'tenant' => 'Mandant',
            'is_active' => 'Aktiv',
            'last_login_at' => 'Letzter Login',
            'created_at' => 'Erstellt am',
            'phone' => 'Telefon',
            'locale' => 'Sprache',
        ],
        'types' => [
            'super_admin' => 'Super-Admin',
            'partner' => 'Partner',
            'employee' => 'Mitarbeiter',
            'customer' => 'Kunde',
        ],
    ],

    // --- AuditLogResource ---
    'audit_log' => [
        'label' => 'Audit-Eintrag',
        'plural' => 'Audit-Log',
        'fields' => [
            'user' => 'Benutzer',
            'action' => 'Aktion',
            'auditable_type' => 'Betroffenes Objekt',
            'auditable_id' => 'Objekt-ID',
            'ip_address' => 'IP-Adresse',
            'user_agent' => 'Browser',
            'reason' => 'Begruendung',
            'created_at' => 'Zeitpunkt',
            'old_values' => 'Alte Werte',
            'new_values' => 'Neue Werte',
        ],
        'actions' => [
            'create' => 'Erstellt',
            'update' => 'Aktualisiert',
            'delete' => 'Geloescht',
            'read' => 'Eingesehen',
            'export' => 'Exportiert',
            'login' => 'Login',
            'logout' => 'Logout',
            'failed_login' => 'Login fehlgeschlagen',
        ],
        'restricted_notice' => 'Verschluesselte Werte nur mit Level-3-Berechtigung einsehbar.',
    ],

    // --- Dashboard ---
    'dashboard' => [
        'heading' => 'ROSI Administration',
        'stats' => [
            'active_tenants' => 'Aktive Mandanten',
            'total_tenants' => 'Gesamt',
            'total_gas_stations' => 'Tankstellen',
            'active_gas_stations' => 'Aktiv',
            'active_users' => 'Aktive Benutzer',
            'partners' => 'Partner',
            'trial_tenants' => 'Testphasen',
            'paid_tenants' => 'Bezahlte Abos',
        ],
        'subscription_chart' => 'Abo-Verteilung',
        'latest_activity' => 'Letzte Aktivitaeten',
    ],
];
