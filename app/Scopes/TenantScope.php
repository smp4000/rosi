<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Globaler Scope fuer Mandanten-Isolation.
 * Filtert automatisch alle Queries nach der aktuellen tenant_id.
 * Super-Admins (ohne tenant_id) sehen ALLE Daten - wird durch Middleware gesteuert.
 */
class TenantScope implements Scope
{
    /**
     * Scope auf den Builder anwenden.
     * Fuegt automatisch WHERE tenant_id = ? zu allen Queries hinzu.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = session('tenant_id');

        if ($tenantId) {
            $builder->where($model->getTable() . '.tenant_id', $tenantId);
        }
    }
}
