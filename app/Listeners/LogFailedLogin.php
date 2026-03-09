<?php

namespace App\Listeners;

use App\Models\AuditLog;
use Illuminate\Auth\Events\Failed;

/**
 * Listener: Protokolliert fehlgeschlagene Login-Versuche.
 * Wichtig fuer Sicherheitsmonitoring und DSGVO-Anforderungen.
 */
class LogFailedLogin
{
    public function handle(Failed $event): void
    {
        AuditLog::create([
            'tenant_id' => null,
            'user_id' => $event->user?->id,
            'user_type' => $event->user?->type ?? 'unknown',
            'action' => 'login',
            'auditable_type' => null,
            'auditable_id' => null,
            'old_values' => null,
            'new_values' => [
                'status' => 'failed',
                'email' => $event->credentials['email'] ?? 'unbekannt',
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'reason' => 'Fehlgeschlagener Login-Versuch',
        ]);
    }
}
