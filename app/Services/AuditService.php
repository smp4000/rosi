<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Zentraler Service fuer DSGVO-konformes Audit-Logging.
 * Protokolliert alle datenschutzrelevanten Aktionen:
 * CRUD-Operationen, Login/Logout, Datenexport, Berechtigungsaenderungen.
 *
 * Sensible Felder (Bankdaten, SV-Nr etc.) werden automatisch maskiert,
 * damit sie nicht im Klartext im Audit-Log erscheinen.
 */
class AuditService
{
    /**
     * Felder, die im Audit-Log maskiert werden (DSGVO-sensibel).
     * Der Wert wird durch '***GESCHUETZT***' ersetzt.
     */
    protected array $sensitiveFields = [
        'password',
        'remember_token',
        'two_factor_secret',
        'iban',
        'bic',
        'bank_name',
        'account_holder',
        'social_security_number',
        'tax_id',
        'health_insurance_number',
        'salary',
        'date_of_birth',
        'children_data',
        'medical_restrictions',
    ];

    /**
     * Allgemeine Aktion protokollieren.
     */
    public function log(
        string $action,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
    ): AuditLog {
        return AuditLog::log(
            action: $action,
            auditable: $auditable,
            oldValues: $oldValues ? $this->maskSensitiveData($oldValues) : null,
            newValues: $newValues ? $this->maskSensitiveData($newValues) : null,
            reason: $reason,
        );
    }

    /**
     * Erstellung eines Datensatzes protokollieren.
     */
    public function logCreate(Model $model, ?string $reason = null): AuditLog
    {
        return $this->log(
            action: 'create',
            auditable: $model,
            newValues: $this->getLoggableAttributes($model),
            reason: $reason,
        );
    }

    /**
     * Aktualisierung eines Datensatzes protokollieren.
     * Vergleicht alte und neue Werte und loggt nur Aenderungen.
     */
    public function logUpdate(Model $model, array $oldValues, ?string $reason = null): AuditLog
    {
        // Nur geaenderte Felder protokollieren
        $changedFields = array_keys($model->getChanges());
        $filteredOld = array_intersect_key($oldValues, array_flip($changedFields));
        $filteredNew = array_intersect_key($model->getAttributes(), array_flip($changedFields));

        // Timestamps und technische Felder ausschliessen
        $exclude = ['updated_at', 'created_at', 'remember_token'];
        $filteredOld = array_diff_key($filteredOld, array_flip($exclude));
        $filteredNew = array_diff_key($filteredNew, array_flip($exclude));

        return $this->log(
            action: 'update',
            auditable: $model,
            oldValues: $filteredOld,
            newValues: $filteredNew,
            reason: $reason,
        );
    }

    /**
     * Loeschung eines Datensatzes protokollieren.
     */
    public function logDelete(Model $model, ?string $reason = null): AuditLog
    {
        return $this->log(
            action: 'delete',
            auditable: $model,
            oldValues: $this->getLoggableAttributes($model),
            reason: $reason,
        );
    }

    /**
     * Lesezugriff protokollieren (fuer sensible Daten, z.B. Super-Admin).
     */
    public function logRead(Model $model, ?string $reason = null): AuditLog
    {
        return $this->log(
            action: 'read',
            auditable: $model,
            reason: $reason,
        );
    }

    /**
     * Datenexport protokollieren (Art. 15 DSGVO).
     */
    public function logExport(Model $model, ?string $reason = null): AuditLog
    {
        return $this->log(
            action: 'export',
            auditable: $model,
            reason: $reason ?? 'Datenexport nach Art. 15 DSGVO',
        );
    }

    /**
     * Login-Ereignis protokollieren.
     */
    public function logLogin(?Model $user = null): AuditLog
    {
        return $this->log(
            action: 'login',
            auditable: $user,
        );
    }

    /**
     * Logout-Ereignis protokollieren.
     */
    public function logLogout(?Model $user = null): AuditLog
    {
        return $this->log(
            action: 'logout',
            auditable: $user,
        );
    }

    /**
     * Berechtigungsaenderung protokollieren.
     */
    public function logPermissionChange(
        Model $user,
        array $oldPermissions,
        array $newPermissions,
        ?string $reason = null,
    ): AuditLog {
        return $this->log(
            action: 'permission_change',
            auditable: $user,
            oldValues: ['permissions' => $oldPermissions],
            newValues: ['permissions' => $newPermissions],
            reason: $reason,
        );
    }

    /**
     * Sensible Daten in einem Array maskieren.
     * Ersetzt Werte der sensiblen Felder durch Platzhalter.
     */
    protected function maskSensitiveData(array $data): array
    {
        foreach ($this->sensitiveFields as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $data[$field] = '***GESCHUETZT***';
            }
        }

        return $data;
    }

    /**
     * Loggbare Attribute eines Models extrahieren.
     * Entfernt technische Felder und hidden Felder.
     */
    protected function getLoggableAttributes(Model $model): array
    {
        $attributes = $model->getAttributes();

        // Technische Felder entfernen
        $exclude = ['updated_at', 'created_at', 'deleted_at', 'remember_token', 'password'];

        return array_diff_key($attributes, array_flip($exclude));
    }
}
