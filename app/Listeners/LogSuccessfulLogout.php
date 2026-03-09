<?php

namespace App\Listeners;

use App\Services\AuditService;
use Illuminate\Auth\Events\Logout;

/**
 * Listener: Protokolliert Logouts im Audit-Log.
 * Wird automatisch bei jedem Logout-Event ausgeloest.
 */
class LogSuccessfulLogout
{
    public function __construct(
        protected AuditService $auditService,
    ) {}

    public function handle(Logout $event): void
    {
        if ($event->user) {
            $this->auditService->logLogout($event->user);
        }
    }
}
