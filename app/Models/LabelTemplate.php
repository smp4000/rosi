<?php

namespace App\Models;

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
     * Findet das passende Template: Tenant-spezifisch hat Vorrang vor global.
     */
    public static function findForTenant(string $slug, ?string $tenantId): ?self
    {
        // Zuerst Tenant-spezifisches Template suchen
        if ($tenantId) {
            $template = static::active()->bySlug($slug)->where('tenant_id', $tenantId)->first();
            if ($template) {
                return $template;
            }
        }

        // Fallback: globales Template
        return static::active()->bySlug($slug)->whereNull('tenant_id')->first();
    }
}
