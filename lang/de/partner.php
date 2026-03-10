<?php

/**
 * Deutsche Uebersetzungen fuer das Partner-Dashboard (Filament Panel).
 */
return [

    // --- Navigation ---
    'nav' => [
        'tankstellen' => 'Tankstellen',
        'personal' => 'Personal',
    ],

    // --- Dashboard ---
    'dashboard' => [
        'stats' => [
            'gas_stations' => 'Tankstellen',
            'active' => 'Aktiv',
            'employees' => 'Mitarbeiter',
            'customers' => 'Kunden',
            'documents' => 'Dokumente',
        ],
        'trial_banner' => [
            'title' => 'Testphase aktiv',
            'message' => 'Ihre kostenlose Testphase laeuft noch :days Tage (bis :date).',
            'choose_plan' => 'Abo waehlen',
        ],
        'quick_actions' => [
            'title' => 'Schnellaktionen',
            'add_station' => 'Tankstelle anlegen',
            'invite_employee' => 'Mitarbeiter einladen',
        ],
    ],

    // --- Tankstellen ---
    'gas_station' => [
        'label' => 'Tankstelle',
        'plural' => 'Tankstellen',
        'tabs' => [
            'stammdaten' => 'Stammdaten',
            'adresse' => 'Adresse',
            'kontakt' => 'Kontakt',
            'betrieb' => 'Betrieb & Services',
            'fotos' => 'Fotos',
        ],
        'fields' => [
            'name' => 'Name',
            'brand' => 'Marke',
            'station_number' => 'Stationsnummer',
            'street' => 'Strasse',
            'zip' => 'PLZ',
            'city' => 'Ort',
            'state' => 'Bundesland',
            'country' => 'Land',
            'latitude' => 'Breitengrad',
            'longitude' => 'Laengengrad',
            'phone' => 'Telefon',
            'fax' => 'Fax',
            'email' => 'E-Mail',
            'tax_id' => 'Steuernummer',
            'trade_register' => 'Handelsregisternummer',
            'num_pumps' => 'Anzahl Zapfsaeulen',
            'has_shop' => 'Shop vorhanden',
            'has_car_wash' => 'Waschanlage vorhanden',
            'opening_hours' => 'Oeffnungszeiten',
            'services' => 'Angebotene Kraftstoffe & Services',
            'logo' => 'Logo / Titelfoto',
            'photos' => 'Weitere Fotos',
            'notes' => 'Interne Notizen',
            'is_active' => 'Aktiv',
            'address' => 'Adresse',
            'users_count' => 'Mitarbeiter',
            'created_at' => 'Erstellt am',
        ],
    ],

    // --- Mitarbeiter ---
    'employee' => [
        'label' => 'Mitarbeiter',
        'plural' => 'Mitarbeiter',
        'tabs' => [
            'stammdaten' => 'Stammdaten',
            'beschaeftigung' => 'Beschaeftigung',
        ],
        'fields' => [
            'first_name' => 'Vorname',
            'last_name' => 'Nachname',
            'name' => 'Name',
            'email' => 'E-Mail',
            'phone' => 'Telefon',
            'is_active' => 'Aktiv',
            'employment_type' => 'Beschaeftigungsart',
            'employment_start' => 'Eintrittsdatum',
            'employment_end' => 'Austrittsdatum',
            'weekly_hours' => 'Wochenstunden',
            'vacation_days' => 'Urlaubstage',
            'gas_stations' => 'Tankstellen',
            'created_at' => 'Erstellt am',
        ],
        'employment_types' => [
            'full_time' => 'Vollzeit',
            'part_time' => 'Teilzeit',
            'mini_job' => 'Minijob',
            'intern' => 'Praktikant',
            'trainee' => 'Auszubildender',
        ],
        'actions' => [
            'invite' => 'Mitarbeiter einladen',
            'invite_description' => 'Senden Sie eine Einladung per E-Mail.',
        ],
    ],
];
