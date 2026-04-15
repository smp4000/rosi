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
     * Standard-DYMO-Etiketten (PaperName aus DYMO Connect).
     * width/height in cm, orientation Portrait/Landscape empfohlen.
     * paper_name muss exakt zum DYMO-PaperName im XML passen.
     */
    public const DYMO_PAPERS = [
        '30252'   => ['label' => '30252 — Adresse (89 x 28 mm)',            'paper_name' => 'Address',                'width' => 8.89,  'height' => 2.84,  'orientation' => 'Landscape'],
        '30320'   => ['label' => '30320 — Adresse gross (89 x 36 mm)',      'paper_name' => 'Large Address',          'width' => 8.89,  'height' => 3.56,  'orientation' => 'Landscape'],
        '30321'   => ['label' => '30321 — Adresse gross (89 x 36 mm)',      'paper_name' => 'Large Address',          'width' => 8.89,  'height' => 3.56,  'orientation' => 'Landscape'],
        '30323'   => ['label' => '30323 — Versand (54 x 101 mm)',           'paper_name' => 'Shipping',               'width' => 5.38,  'height' => 10.16, 'orientation' => 'Portrait'],
        '30256'   => ['label' => '30256 — Versand gross (59 x 101 mm)',     'paper_name' => 'Large Shipping',         'width' => 5.87,  'height' => 10.16, 'orientation' => 'Portrait'],
        '30270'   => ['label' => '30270 — Mehrzweck (19 x 54 mm)',          'paper_name' => 'Multi Purpose',          'width' => 5.38,  'height' => 1.91,  'orientation' => 'Landscape'],
        '30330'   => ['label' => '30330 — Rueckadresse (19 x 51 mm)',       'paper_name' => 'Return Address',         'width' => 5.08,  'height' => 1.91,  'orientation' => 'Landscape'],
        '30332'   => ['label' => '30332 — Mehrzweck klein (25 x 25 mm)',    'paper_name' => '1 in x 1 in',            'width' => 2.54,  'height' => 2.54,  'orientation' => 'Portrait'],
        '30334'   => ['label' => '30334 — Mehrzweck mittel (57 x 32 mm)',   'paper_name' => '2-1/4 in x 1-1/4 in',    'width' => 5.72,  'height' => 3.18,  'orientation' => 'Landscape'],
        '30336'   => ['label' => '30336 — Mehrzweck (25 x 54 mm)',          'paper_name' => '1 in x 2-1/8 in',        'width' => 5.38,  'height' => 2.54,  'orientation' => 'Landscape'],
        '30346'   => ['label' => '30346 — Bibliothek (13 x 48 mm)',         'paper_name' => '1/2 in x 1-7/8 in',      'width' => 4.75,  'height' => 1.27,  'orientation' => 'Landscape'],
        '30370'   => ['label' => '30370 — Mehrzweck abloesbar (54 x 25 mm)','paper_name' => 'Multipurpose Removable', 'width' => 5.38,  'height' => 2.54,  'orientation' => 'Landscape'],
        '30373'   => ['label' => '30373 — Preisschild (24 x 22 mm)',        'paper_name' => 'Price Tag Label',        'width' => 2.36,  'height' => 2.21,  'orientation' => 'Portrait'],
        '30374'   => ['label' => '30374 — Terminkarte (51 x 89 mm)',        'paper_name' => 'Appointment Card',       'width' => 8.89,  'height' => 5.08,  'orientation' => 'Landscape'],
        '30383'   => ['label' => '30383 — Internet-Porto 3-teilig',         'paper_name' => 'Internet Postage 3-Part','width' => 19.05, 'height' => 5.87,  'orientation' => 'Landscape'],
        '30384'   => ['label' => '30384 — Internet-Porto 2-teilig',         'paper_name' => 'Internet Postage 2-Part','width' => 19.05, 'height' => 5.87,  'orientation' => 'Landscape'],
        '30387'   => ['label' => '30387 — Internet-Porto',                  'paper_name' => 'Internet Postage',       'width' => 19.05, 'height' => 5.87,  'orientation' => 'Landscape'],
        '30857'   => ['label' => '30857 — Namensschild (57 x 101 mm)',      'paper_name' => 'Name Badge Label',       'width' => 10.16, 'height' => 5.72,  'orientation' => 'Landscape'],
        '30911'   => ['label' => '30911 — Namensschild ablaufend',          'paper_name' => 'Time Expiring Name Badge','width' => 10.62,'height' => 6.17,  'orientation' => 'Landscape'],
        '30915'   => ['label' => '30915 — Internet-Porto (40 x 48 mm)',     'paper_name' => 'Internet Postage Label', 'width' => 3.96,  'height' => 4.75,  'orientation' => 'Portrait'],
        '30299'   => ['label' => '30299 — Schmuck-Etikett (10 x 64 mm)',    'paper_name' => 'Barbell Label',          'width' => 6.35,  'height' => 0.94,  'orientation' => 'Landscape'],
        '1744907' => ['label' => '1744907 — Versand XL (102 x 152 mm)',     'paper_name' => 'Extra Large Shipping',   'width' => 10.16, 'height' => 15.24, 'orientation' => 'Portrait'],
        '14681'   => ['label' => '14681 — Runde Etiketten (Ø 57 mm)',       'paper_name' => '2-1/4 in Round',         'width' => 5.72,  'height' => 5.72,  'orientation' => 'Portrait'],
        '30324'   => ['label' => '30324 — Diskette (54 x 70 mm)',           'paper_name' => 'Diskette',               'width' => 6.99,  'height' => 5.38,  'orientation' => 'Landscape'],
        '30277'   => ['label' => '30277 — Haengeordner 2-spalt.',           'paper_name' => 'File Folder 2-up',       'width' => 8.71,  'height' => 1.42,  'orientation' => 'Landscape'],
        '30327'   => ['label' => '30327 — Ordner (19 x 73 mm)',             'paper_name' => 'File Folder',            'width' => 8.71,  'height' => 1.91,  'orientation' => 'Landscape'],
        '30578'   => ['label' => '30578 — VHS-Kassette oben',               'paper_name' => 'VHS Top',                'width' => 7.92,  'height' => 4.75,  'orientation' => 'Landscape'],
    ];

    /**
     * Gibt die DYMO-Etiketten als Select-Options zurueck (code => label).
     */
    public static function getDymoPaperOptions(): array
    {
        $options = ['custom' => 'Eigene Groesse (nicht aus DYMO-Liste)'];
        foreach (self::DYMO_PAPERS as $code => $config) {
            $options[$code] = $config['label'];
        }
        return $options;
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
