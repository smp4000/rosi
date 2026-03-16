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
            'adresse' => 'Adresse',
            'allgemein' => 'Allgemein',
            'finanzen' => 'Finanzen',
            'oeffnungszeiten' => 'Oeffnungszeiten',
            'shop' => 'Shop & Betrieb',
            'fotos' => 'Fotos',
            'wettbewerb' => 'Wettbewerb',
            'karte' => 'Karte',
        ],
        'sections' => [
            'tank_details' => 'Tankdetails',
            'car_wash' => 'Waschanlage',
            'shop_details' => 'Shop-Details',
            'additional_businesses' => 'Zusatzgeschaefte',
            'competitor_search' => 'Wettbewerber suchen',
        ],
        'competitor_search_hint' => 'Geben Sie eine PLZ ein, um Tankstellen im Umkreis zu finden. Waehlen Sie eine Station aus, um sie als Wettbewerber hinzuzufuegen (max. 8).',
        'fields' => [
            // Allgemein
            'name' => 'Name',
            'brand' => 'Marke',
            'station_number' => 'Stationsnummer',
            'sales_channel' => 'Vertriebskanal',
            'ownership_type' => 'Eigentumsverhaeltnis',
            'district' => 'Distrikt',
            'district_description' => 'Distrikt-Beschreibung',
            'region' => 'Bezirk',
            'region_manager' => 'Bezirksleitung',
            'station_number_fuel' => 'Tst.-Nr. Kraftstoff',
            'station_number_shop' => 'Tst.-Nr. Shop',
            'has_toll_terminal' => 'Mautstellenterminal',
            'tax_id' => 'Steuernummer',
            'trade_register' => 'Handelsregisternummer',
            'num_pumps' => 'Anzahl Zapfsaeulen',
            'has_shop' => 'Shop vorhanden',
            'has_car_wash' => 'Waschanlage vorhanden',
            'services' => 'Angebotene Kraftstoffe & Services',
            'fuel_types' => 'Kraftstoffsorten',
            'shop_services' => 'Shop-Angebot',
            'additional_businesses' => 'Zusatzgeschaefte',
            // Waschanlage
            'cw_has_drive_through' => 'Durchfahrtshalle',
            'cw_brand' => 'Marke',
            'cw_type' => 'Anlagentyp',
            'cw_height' => 'Durchfahrtshoehe',
            'cw_width' => 'Durchfahrtsbreite',
            'cw_has_underbody_wash' => 'Unterbodenwaesche',
            'cw_has_ticket_system' => 'Ticketsystem',
            'cw_has_easy_carwash_pro' => 'Easy Carwash Pro',
            'cw_notes' => 'Interne Notizen (Waschanlage)',
            'notes' => 'Interne Notizen',
            'is_active' => 'Aktiv',
            // Adresse
            'academic_title' => 'Akad. Grad',
            'contact_first_name' => 'Vorname',
            'contact_last_name' => 'Name',
            'street' => 'Strasse',
            'house_number' => 'Hausnummer',
            'zip' => 'PLZ',
            'city' => 'Stadt',
            'district_part' => 'Ortsteil',
            'state' => 'Bundesland',
            'country' => 'Land',
            'phone' => 'Telefon',
            'fax' => 'Fax',
            'email' => 'E-Mail',
            'website' => 'Webseite',
            'salutation_address' => 'Anrede Anschrift',
            'address' => 'Adresse',
            // Finanzen
            'bank_accounts' => 'Bankkonten',
            'iban' => 'IBAN',
            'bank_name' => 'Bankname',
            'bic' => 'BIC',
            'bank_description' => 'Beschreibung',
            'account_type' => 'Kontotyp',
            // Oeffnungszeiten
            'opening_hours' => 'Oeffnungszeiten',
            'open_time' => 'von',
            'close_time' => 'bis',
            'weekly_opening_hours' => 'Oeffnungsstunden pro Woche',
            'first_opening_ok' => 'Erstoeffnung OK',
            'first_opening_dk' => 'Erstoeffnung DK',
            // Shop
            'shop_size' => 'Shopgroesse',
            'shop_type' => 'Shoptyp',
            'shop_class' => 'Shop Klasse',
            'shop_setup_date' => 'Einrichtungsdatum Shop',
            'nielsen_area' => 'Nielsen-Gebiet',
            'price_region' => 'Preisregion',
            'assortment_level' => 'Sortimentsstufe',
            'shop_partner' => 'Shop Partner',
            'shop_operation_number' => 'Shop-Betriebsnummer',
            // Medien
            'logo' => 'Logo / Titelfoto',
            'photos' => 'Weitere Fotos',
            // Wettbewerb
            'competitor_search_plz' => 'PLZ',
            'competitor_stations' => 'Gefundene Tankstellen',
            'competitors' => 'Wettbewerbstankstellen',
            // Karte / Geo
            'latitude' => 'Breitengrad',
            'longitude' => 'Laengengrad',
            // Tabelle
            'users_count' => 'Mitarbeiter',
            'created_at' => 'Erstellt am',
        ],
        // --- Auswahl-Optionen ---
        'ownership_types' => [
            'DOFO' => 'DOFO - Dealer Owned, Franchise Operated',
            'COFO' => 'COFO - Company Owned, Franchise Operated',
            'DODO' => 'DODO - Dealer Owned, Dealer Operated',
            'CODO' => 'CODO - Company Owned, Dealer Operated',
            'COCO' => 'COCO - Company Owned, Company Operated',
        ],
        'academic_titles' => [
            'Dr.' => 'Dr.',
            'Prof.' => 'Prof.',
            'Prof. Dr.' => 'Prof. Dr.',
            'Dipl.-Ing.' => 'Dipl.-Ing.',
        ],
        'account_types' => [
            'Geschaeftskonto' => 'Geschaeftskonto',
            'Agenturkonto' => 'Agenturkonto',
            'Lottokonto' => 'Lottokonto',
        ],
        'nielsen_areas' => [
            '1' => 'Nielsen I (SH, HH, HB, NI)',
            '2' => 'Nielsen II (NRW)',
            '3a' => 'Nielsen IIIa (HE, RP, SL)',
            '3b' => 'Nielsen IIIb (BW)',
            '4' => 'Nielsen IV (BY)',
            '5' => 'Nielsen V (Berlin)',
            '6' => 'Nielsen VI (MV, BB, ST)',
            '7' => 'Nielsen VII (TH, SN)',
        ],
        'shop_types' => [
            'rewe_to_go' => 'REWE To Go',
            'lekkerland' => 'Lekkerland',
            'convenience' => 'Convenience',
            'bistro' => 'Bistro',
            'sonstige' => 'Sonstige',
        ],
        'shop_classes' => [
            'A' => 'Klasse A',
            'B' => 'Klasse B',
            'C' => 'Klasse C',
        ],
        'assortment_levels' => [
            'basis' => 'Basis',
            'standard' => 'Standard',
            'premium' => 'Premium',
        ],
        'days' => [
            'monday' => 'Montags',
            'tuesday' => 'Dienstags',
            'wednesday' => 'Mittwochs',
            'thursday' => 'Donnerstags',
            'friday' => 'Freitags',
            'saturday' => 'Samstags',
            'sunday' => 'Sonntags',
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
