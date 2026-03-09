<?php

/**
 * DSGVO-Konfiguration fuer ROSI.
 * Zentrale Einstellungen fuer Datenschutz, Aufbewahrungsfristen
 * und Einwilligungsmanagement.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Aufbewahrungsfristen (in Tagen)
    |--------------------------------------------------------------------------
    | Konfigurierbare Fristen fuer automatische Datenloesch ung.
    | Gesetzliche Mindestfristen beachten (z.B. 10 Jahre fuer Lohndaten).
    */
    'retention' => [
        // Trial-Mandanten: Loesch ung nach X Tagen Inaktivitaet
        'trial_accounts_days' => env('DSGVO_TRIAL_RETENTION_DAYS', 30),

        // Gekuendigte Mandanten: Loesch ung nach X Tagen
        'cancelled_accounts_days' => env('DSGVO_CANCELLED_RETENTION_DAYS', 90),

        // Audit-Logs: Anonymisierung nach X Jahren
        'audit_logs_years' => env('DSGVO_AUDIT_RETENTION_YEARS', 3),

        // KI-Chat-Verlaeufe: Loesch ung nach X Monaten
        'ai_chat_months' => env('DSGVO_AI_CHAT_RETENTION_MONTHS', 12),

        // Lohndaten/Steuerunterlagen: Aufbewahrung (gesetzlich 10 Jahre)
        'payroll_data_years' => 10,

        // Geschaeftsunterlagen: Aufbewahrung (gesetzlich 6 Jahre)
        'business_records_years' => 6,
    ],

    /*
    |--------------------------------------------------------------------------
    | Verschluesselung
    |--------------------------------------------------------------------------
    | Welche Felder in welchen Models verschluesselt werden.
    | Wird fuer Dokumentation und Audit-Zwecke verwendet.
    */
    'encrypted_fields' => [
        'employee_profiles' => [
            'date_of_birth',
            'tax_id',
            'social_security_number',
            'health_insurance_number',
            'bank_name',
            'iban',
            'bic',
            'account_holder',
            'children_data',
            'medical_restrictions',
            'salary',
        ],
        'users' => [
            'two_factor_secret',
        ],
        'audit_logs' => [
            'old_values',
            'new_values',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Einwilligungen (Consent)
    |--------------------------------------------------------------------------
    | Versionen und Pflicht-Einwilligungen.
    */
    'consent' => [
        // Aktuelle Versionen der Erklaerungen
        'versions' => [
            'privacy_policy' => '1.0',
            'terms' => '1.0',
            'data_processing' => '1.0',
            'cookies' => '1.0',
            'marketing' => '1.0',
        ],

        // Pflicht-Einwilligungen bei Registrierung
        'required' => [
            'privacy_policy',
            'terms',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Super-Admin Datenschutz
    |--------------------------------------------------------------------------
    | Zugriffsstufen fuer Super-Admin (minimales Zugriffsprinzip).
    */
    'super_admin' => [
        // Standard-Level bei Login
        'default_level' => 1,

        // Level 1: Uebersicht + Statistiken (keine personenbezogenen Daten)
        // Level 2: Support - Stammdaten einsehen (Name, E-Mail)
        // Level 3: Notfall - Voller Zugriff (2FA + Begruendung erforderlich)

        // Felder die bei Level 1 und 2 gefiltert werden
        'restricted_fields' => [
            'iban', 'bic', 'bank_name', 'account_holder',
            'social_security_number', 'tax_id', 'date_of_birth',
            'health_insurance_number', 'salary', 'children_data',
            'medical_restrictions',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit-Log
    |--------------------------------------------------------------------------
    */
    'audit' => [
        // Automatisches Audit-Logging aktivieren
        'enabled' => env('DSGVO_AUDIT_ENABLED', true),

        // Felder die im Audit-Log maskiert werden
        'masked_fields' => [
            'password', 'remember_token', 'two_factor_secret',
            'iban', 'bic', 'bank_name', 'account_holder',
            'social_security_number', 'tax_id',
            'health_insurance_number', 'salary',
            'date_of_birth', 'children_data', 'medical_restrictions',
        ],
    ],
];
