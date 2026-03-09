<?php

namespace App\Listeners;

use App\Services\AuditService;
use Illuminate\Auth\Events\Login;

/**
 * Listener: Protokolliert erfolgreiche Logins im Audit-Log.
 * Wird automatisch bei jedem Login-Event ausgeloest.
 */
class LogSuccessfulLogin
{
    public function __construct(
        protected AuditService $auditService,
    ) {}

    public function handle(Login $event): void
    {
        $this->auditService->logLogin($event->user);
    }
}
