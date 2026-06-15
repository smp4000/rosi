<?php

return [

    /*
    |--------------------------------------------------------------------------
    | TTL der Druckjobs (Minuten)
    |--------------------------------------------------------------------------
    | Wird ein Job nicht innerhalb dieser Zeit gedruckt (PC aus, Agent offline),
    | laeuft er ab (-> expired) und wird NICHT nachgedruckt. Gutscheine sind nur
    | frisch sinnvoll -> kurze TTL. Pro job_type ueberschreibbar.
    */
    'default_ttl_minutes' => 15,

    'ttl_minutes' => [
        'voucher_labels' => 10,    // Gutscheine: sehr zeitnah, sonst wertlos
        'mhd_labels' => 120,
        'address_labels' => 1440,  // Adress-Etiketten duerfen laenger warten
        'fuel_theft' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Aufbewahrung (Tage)
    |--------------------------------------------------------------------------
    | Erledigte/abgelaufene/fehlgeschlagene Jobs aelter als X Tage werden vom
    | Cleanup-Command geloescht (Audit bleibt im Druckprotokoll).
    */
    'retention_days' => 7,

];
