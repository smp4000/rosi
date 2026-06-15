<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Geplante Aufgaben (DSGVO-konform)
|--------------------------------------------------------------------------
| Diese Aufgaben werden automatisch nach Zeitplan ausgefuehrt.
| Voraussetzung: Cron-Job auf dem Server:
| * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
|
*/

// DSGVO: Abgelaufene Trial-Mandanten nach 30 Tagen loeschen (taeglich)
Schedule::command('rosi:cleanup-trials')->daily()->at('03:00');

// DSGVO: Audit-Logs nach 3 Jahren anonymisieren (monatlich, am 1.)
Schedule::command('rosi:anonymize-audit-logs')->monthlyOn(1, '04:00');

// DSGVO: KI-Chat-Verlaeufe nach 12 Monaten loeschen (monatlich, am 1.)
Schedule::command('rosi:cleanup-ai-chats')->monthlyOn(1, '04:30');

// Druck-Warteschlange aufraeumen (TTL/Expiry, haengende Jobs, alte loeschen)
Schedule::command('print:cleanup')->everyFiveMinutes();
