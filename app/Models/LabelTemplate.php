<?php

namespace App\Models;

use App\Models\GasStation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * DYMO Label-Vorlage mit Platzhaltern.
 *
 * Globale Templates (tenant_id=null) gelten fuer alle Stationen.
 * Stationen koennen eigene Templates erstellen oder globale ueberschreiben.
 */
class LabelTemplate extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'slug',
        'category',
        'name',
        'xml_template',
        'placeholders',
        'width',
        'height',
        'orientation',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'placeholders' => 'array',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    // -- Relationships --

    public function tenant()
    {
        return $this->belongsTo(GasStation::class, 'tenant_id');
    }

    // -- Scopes --

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /** Templates die ein Tenant sehen darf: eigene + globale */
    public function scopeForTenant($query, ?string $tenantId)
    {
        return $query->where(function ($q) use ($tenantId) {
            $q->whereNull('tenant_id');
            if ($tenantId) {
                $q->orWhere('tenant_id', $tenantId);
            }
        });
    }

    // -- Methods --

    /**
     * Ersetzt {{platzhalter}} im XML-Template mit echten Daten.
     */
    public function render(array $data): string
    {
        $xml = $this->xml_template;

        foreach ($data as $key => $value) {
            $xml = str_replace('{{' . $key . '}}', htmlspecialchars((string) $value, ENT_XML1), $xml);
        }

        return $xml;
    }

    /**
     * Verfuegbare Modelle mit ihren Platzhaltern.
     */
    public const MODELS = [
        'tankbetrug' => [
            'label' => 'Tankbetrug',
            'placeholders' => [
                ['key' => 'id', 'label' => 'Tankbetrug-ID', 'example' => '019d-7a3b-...'],
                ['key' => 'datum', 'label' => 'Datum + Uhrzeit', 'example' => '11.04.2026 16:25'],
                ['key' => 'kennzeichen', 'label' => 'KFZ-Kennzeichen', 'example' => 'FD-AB 123'],
                ['key' => 'produkt', 'label' => 'Kraftstoff-Sorte', 'example' => 'Super E5'],
                ['key' => 'zapfpunkt', 'label' => 'Zapfpunkt-Nummer', 'example' => '7'],
                ['key' => 'menge', 'label' => 'Liter-Menge', 'example' => '45,20'],
                ['key' => 'betrag', 'label' => 'EUR-Betrag', 'example' => '82,36'],
                ['key' => 'station', 'label' => 'Tankstellen-Name', 'example' => 'Aral Tankstelle Welle Fulda'],
                ['key' => 'mitarbeiter', 'label' => 'Mitarbeiter-Name', 'example' => 'Christian Welle'],
            ],
        ],
        'testdruck' => [
            'label' => 'Testdruck',
            'placeholders' => [
                ['key' => 'datum', 'label' => 'Datum + Uhrzeit', 'example' => '11.04.2026 16:25'],
            ],
        ],
        'adresse' => [
            'label' => 'Adress-Etikett',
            'placeholders' => [
                ['key' => 'name', 'label' => 'Empfaenger-Name', 'example' => 'Max Mustermann'],
                ['key' => 'strasse', 'label' => 'Strasse + Hausnr.', 'example' => 'Musterstr. 1'],
                ['key' => 'ort', 'label' => 'PLZ + Ort', 'example' => '36100 Petersberg'],
                ['key' => 'absender', 'label' => 'Absender', 'example' => 'Aral Tankstelle Welle'],
            ],
        ],
    ];

    /**
     * Gibt die Platzhalter fuer ein Modell zurueck.
     */
    public static function getPlaceholdersForModel(string $model): array
    {
        return self::MODELS[$model]['placeholders'] ?? [];
    }

    /**
     * Gibt die Modell-Optionen fuer ein Select-Feld zurueck.
     */
    public static function getModelOptions(): array
    {
        $options = [];
        foreach (self::MODELS as $key => $config) {
            $options[$key] = $config['label'];
        }
        $options['custom'] = 'Benutzerdefiniert';
        return $options;
    }

    /**
     * Findet das passende Template fuer eine Kategorie.
     *
     * Reihenfolge:
     * 1. Station hat ein Template gewaehlt (in settings.label_templates)
     * 2. Erstes aktives Template der Kategorie (Tenant-spezifisch > global)
     *
     * $slugOrCategory kann ein slug ("tankbetrug-qr") oder eine category ("tankbetrug") sein.
     */
    public static function findForTenant(string $slugOrCategory, ?string $tenantId): ?self
    {
        // 1. Pruefen ob Station ein Template gewaehlt hat
        if ($tenantId) {
            $station = GasStation::find($tenantId);
            if ($station) {
                $selectedSlug = $station->getLabelTemplateSlug($slugOrCategory);
                if ($selectedSlug) {
                    $template = static::active()->bySlug($selectedSlug)->first();
                    if ($template) return $template;
                }
            }
        }

        // 2. Exakter Slug-Match (abwaertskompatibel)
        $template = static::active()->bySlug($slugOrCategory)
            ->where(fn ($q) => $q->whereNull('tenant_id')->when($tenantId, fn ($q2) => $q2->orWhere('tenant_id', $tenantId)))
            ->first();
        if ($template) return $template;

        // 3. Erstes Template der Kategorie
        return static::active()->byCategory($slugOrCategory)
            ->where(fn ($q) => $q->whereNull('tenant_id')->when($tenantId, fn ($q2) => $q2->orWhere('tenant_id', $tenantId)))
            ->orderByRaw('tenant_id IS NULL ASC') // Tenant-spezifisch zuerst
            ->first();
    }
}
