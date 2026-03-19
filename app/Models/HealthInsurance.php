<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * Krankenkassen-Stammdaten.
 * System-Kassen (tenant_id=null) + mandantenspezifische Kassen.
 */
class HealthInsurance extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'insurance_number',
        'is_system',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Alle Kassen fuer den aktuellen Mandanten (eigene + System-Kassen).
     */
    public function scopeForTenant(Builder $query, ?string $tenantId = null): Builder
    {
        $tenantId = $tenantId ?? session('tenant_id');

        return $query->where(function ($q) use ($tenantId) {
            $q->whereNull('tenant_id')           // System-Kassen
              ->orWhere('tenant_id', $tenantId);  // Eigene Kassen
        })->where('is_active', true);
    }

    /**
     * Options-Array fuer Select-Felder.
     */
    public static function getOptions(?string $tenantId = null): array
    {
        return static::forTenant($tenantId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * System-Kassen initial anlegen (einmalig via Seeder).
     */
    public static function seedSystemInsurances(): void
    {
        $insurances = [
            // Gesetzliche Krankenkassen (groesste in DE)
            ['name' => 'AOK', 'type' => 'gesetzlich', 'sort_order' => 1],
            ['name' => 'TK (Techniker Krankenkasse)', 'type' => 'gesetzlich', 'sort_order' => 2],
            ['name' => 'Barmer', 'type' => 'gesetzlich', 'sort_order' => 3],
            ['name' => 'DAK-Gesundheit', 'type' => 'gesetzlich', 'sort_order' => 4],
            ['name' => 'IKK classic', 'type' => 'gesetzlich', 'sort_order' => 5],
            ['name' => 'KKH (Kaufmännische Krankenkasse)', 'type' => 'gesetzlich', 'sort_order' => 6],
            ['name' => 'HEK (Hanseatische Krankenkasse)', 'type' => 'gesetzlich', 'sort_order' => 7],
            ['name' => 'hkk Krankenkasse', 'type' => 'gesetzlich', 'sort_order' => 8],
            ['name' => 'Knappschaft', 'type' => 'gesetzlich', 'sort_order' => 9],
            ['name' => 'BKK (Betriebskrankenkasse)', 'type' => 'gesetzlich', 'sort_order' => 10],
            ['name' => 'Mobil Krankenkasse', 'type' => 'gesetzlich', 'sort_order' => 11],
            ['name' => 'SBK (Siemens BKK)', 'type' => 'gesetzlich', 'sort_order' => 12],
            ['name' => 'Viactiv Krankenkasse', 'type' => 'gesetzlich', 'sort_order' => 13],
            ['name' => 'Audi BKK', 'type' => 'gesetzlich', 'sort_order' => 14],
            ['name' => 'Pronova BKK', 'type' => 'gesetzlich', 'sort_order' => 15],
            ['name' => 'BIG direkt gesund', 'type' => 'gesetzlich', 'sort_order' => 16],
            ['name' => 'IKK Suedwest', 'type' => 'gesetzlich', 'sort_order' => 17],
            ['name' => 'IKK gesund plus', 'type' => 'gesetzlich', 'sort_order' => 18],
            ['name' => 'mhplus Krankenkasse', 'type' => 'gesetzlich', 'sort_order' => 19],
            ['name' => 'Debeka BKK', 'type' => 'gesetzlich', 'sort_order' => 20],
            // Private Krankenkassen (groesste in DE)
            ['name' => 'Debeka (privat)', 'type' => 'privat', 'sort_order' => 50],
            ['name' => 'DKV (Deutsche Krankenversicherung)', 'type' => 'privat', 'sort_order' => 51],
            ['name' => 'Allianz Private KV', 'type' => 'privat', 'sort_order' => 52],
            ['name' => 'AXA Krankenversicherung', 'type' => 'privat', 'sort_order' => 53],
            ['name' => 'Signal Iduna', 'type' => 'privat', 'sort_order' => 54],
            ['name' => 'Barmenia', 'type' => 'privat', 'sort_order' => 55],
            ['name' => 'Continentale', 'type' => 'privat', 'sort_order' => 56],
            ['name' => 'Hallesche', 'type' => 'privat', 'sort_order' => 57],
            ['name' => 'Gothaer', 'type' => 'privat', 'sort_order' => 58],
            ['name' => 'HUK-Coburg', 'type' => 'privat', 'sort_order' => 59],
        ];

        foreach ($insurances as $data) {
            static::firstOrCreate(
                ['name' => $data['name'], 'tenant_id' => null],
                array_merge($data, ['is_system' => true])
            );
        }
    }
}
