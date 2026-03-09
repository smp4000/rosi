<?php

namespace App\Traits;

use App\Services\AuditService;

/**
 * Trait fuer automatisches Audit-Logging auf Model-Events.
 * Protokolliert automatisch: Erstellen, Aktualisieren, Loeschen.
 *
 * Verwendung:
 *   use Auditable;
 *
 * Optional: $auditExclude Array definieren um Felder vom Logging auszuschliessen:
 *   protected array $auditExclude = ['cached_data', 'temp_field'];
 */
trait Auditable
{
    /**
     * Statischer Speicher fuer alte Werte vor Update.
     * Wird NICHT als Model-Attribut gespeichert um DB-Fehler zu vermeiden.
     *
     * @var array<string, array>
     */
    protected static array $auditOldValues = [];

    /**
     * Boot-Methode: Registriert Model-Event-Listener fuer Audit-Logging.
     */
    public static function bootAuditable(): void
    {
        // Nach Erstellung: Neuen Datensatz loggen
        static::created(function ($model) {
            try {
                app(AuditService::class)->logCreate($model);
            } catch (\Throwable $e) {
                // Audit-Fehler duerfen die Anwendung nicht blockieren
                report($e);
            }
        });

        // Vor Aktualisierung: Alte Werte in statischem Cache merken
        static::updating(function ($model) {
            $key = get_class($model) . ':' . $model->getKey();
            static::$auditOldValues[$key] = $model->getOriginal();
        });

        // Nach Aktualisierung: Aenderungen loggen
        static::updated(function ($model) {
            try {
                $key = get_class($model) . ':' . $model->getKey();
                $oldValues = static::$auditOldValues[$key] ?? $model->getOriginal();

                // Nur loggen wenn sich tatsaechlich etwas geaendert hat
                $changes = $model->getChanges();
                $excludeFields = array_merge(
                    ['updated_at', 'created_at'],
                    $model->getAuditExclude()
                );

                // Ausgeschlossene Felder entfernen
                $relevantChanges = array_diff_key($changes, array_flip($excludeFields));

                if (! empty($relevantChanges)) {
                    app(AuditService::class)->logUpdate($model, $oldValues);
                }
            } catch (\Throwable $e) {
                report($e);
            } finally {
                // Zwischenspeicher bereinigen
                $key = get_class($model) . ':' . $model->getKey();
                unset(static::$auditOldValues[$key]);
            }
        });

        // Nach Loeschung: Geloeschten Datensatz loggen
        static::deleted(function ($model) {
            try {
                app(AuditService::class)->logDelete($model);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    /**
     * Felder die vom Audit-Logging ausgeschlossen werden sollen.
     * Kann im Model ueberschrieben werden.
     */
    public function getAuditExclude(): array
    {
        return property_exists($this, 'auditExclude') ? $this->auditExclude : [];
    }
}
