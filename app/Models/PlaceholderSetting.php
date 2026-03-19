<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mandanten-spezifische Platzhalter-Einstellungen fuer Dokumente.
 * Werden in Dokumentvorlagen als {{key}} verwendet und automatisch ersetzt.
 *
 * Gruppen:
 * - company: Firmendaten (Geschaeftsfuehrer, Handelsregister etc.)
 * - legal: Rechtliche Angaben (Datenschutzbeauftragter etc.)
 * - custom: Benutzerdefinierte Platzhalter
 */
class PlaceholderSetting extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'key',
        'label',
        'value',
        'group',
        'type',
        'description',
        'sort_order',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // --- Beziehungen ---

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // --- Scopes ---

    public function scopeOfGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    // --- Statische Hilfsmethoden ---

    /**
     * Alle Platzhalter als Key-Value-Array fuer einen Mandanten laden.
     */
    public static function getVariableMap(?string $tenantId = null): array
    {
        $tenantId = $tenantId ?? session('tenant_id');

        if (! $tenantId) {
            return [];
        }

        return static::where('tenant_id', $tenantId)
            ->whereNotNull('value')
            ->pluck('value', 'key')
            ->toArray();
    }

    /**
     * System-Platzhalter fuer einen Mandanten initialisieren.
     * Wird beim Onboarding/Ersteinrichtung aufgerufen.
     */
    public static function initializeForTenant(string $tenantId): void
    {
        $defaults = [
            // Firmendaten
            ['key' => 'geschaeftsfuehrer_name', 'label' => 'Geschaeftsfuehrer', 'group' => 'company', 'type' => 'text', 'description' => 'Name des Geschaeftsfuehrers / Inhabers', 'sort_order' => 1],
            ['key' => 'firma_name', 'label' => 'Firmenname', 'group' => 'company', 'type' => 'text', 'description' => 'Offizieller Firmenname (z.B. Mustermann Tankstellen GmbH)', 'sort_order' => 2],
            ['key' => 'firma_rechtsform', 'label' => 'Rechtsform', 'group' => 'company', 'type' => 'text', 'description' => 'z.B. GmbH, GmbH & Co. KG, Einzelunternehmen', 'sort_order' => 3],
            ['key' => 'firma_strasse', 'label' => 'Strasse + Hausnummer', 'group' => 'company', 'type' => 'text', 'sort_order' => 4],
            ['key' => 'firma_plz', 'label' => 'PLZ', 'group' => 'company', 'type' => 'text', 'sort_order' => 5],
            ['key' => 'firma_ort', 'label' => 'Ort', 'group' => 'company', 'type' => 'text', 'sort_order' => 6],
            ['key' => 'firma_telefon', 'label' => 'Telefon', 'group' => 'company', 'type' => 'text', 'sort_order' => 7],
            ['key' => 'firma_email', 'label' => 'E-Mail', 'group' => 'company', 'type' => 'text', 'sort_order' => 8],
            ['key' => 'firma_webseite', 'label' => 'Webseite', 'group' => 'company', 'type' => 'text', 'sort_order' => 9],

            // Rechtliche Angaben
            ['key' => 'handelsregister', 'label' => 'Handelsregister', 'group' => 'legal', 'type' => 'text', 'description' => 'z.B. HRB 12345, Amtsgericht Fulda', 'sort_order' => 10],
            ['key' => 'steuer_id_firma', 'label' => 'Steuernummer Firma', 'group' => 'legal', 'type' => 'text', 'sort_order' => 11],
            ['key' => 'ust_id', 'label' => 'USt-IdNr.', 'group' => 'legal', 'type' => 'text', 'description' => 'Umsatzsteuer-Identifikationsnummer', 'sort_order' => 12],
            ['key' => 'datenschutzbeauftragter', 'label' => 'Datenschutzbeauftragter', 'group' => 'legal', 'type' => 'text', 'sort_order' => 13],
            ['key' => 'betriebsarzt', 'label' => 'Betriebsarzt', 'group' => 'legal', 'type' => 'text', 'sort_order' => 14],
            ['key' => 'sicherheitsbeauftragter', 'label' => 'Sicherheitsbeauftragter', 'group' => 'legal', 'type' => 'text', 'sort_order' => 15],
        ];

        foreach ($defaults as $default) {
            static::firstOrCreate(
                ['tenant_id' => $tenantId, 'key' => $default['key']],
                array_merge($default, [
                    'tenant_id' => $tenantId,
                    'is_system' => true,
                ]),
            );
        }
    }

    /**
     * Gruppen-Labels fuer die UI.
     */
    public static function getGroupOptions(): array
    {
        return [
            'company' => 'Firmendaten',
            'legal' => 'Rechtliches',
            'custom' => 'Benutzerdefiniert',
        ];
    }

    /**
     * Typ-Labels fuer die UI.
     */
    public static function getTypeOptions(): array
    {
        return [
            'text' => 'Einzeilig',
            'textarea' => 'Mehrzeilig',
            'date' => 'Datum',
            'number' => 'Zahl',
        ];
    }
}
