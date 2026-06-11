<?php

/*
 * Einstellungen fuer die POS-App (MDE-Geraete).
 */
return [

    // Radius in Metern: So nah muss ein Geraet bei der Anmeldung an der
    // Station sein, damit es ohne manuelle Freigabe aktiviert wird.
    'gps_radius_m' => env('POS_GPS_RADIUS_M', 250),

    // GPS-Pruefung ueberspringen (lokale Entwicklung).
    // Bei APP_ENV=local wird sie automatisch uebersprungen.
    'skip_gps_check' => env('POS_SKIP_GPS_CHECK', false),

];
