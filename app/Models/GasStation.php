<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tankstellen-Model mit UUID und Mandantenzuordnung.
 * Jede Tankstelle gehoert zu einem Mandanten (Partner).
 * Auditable: Alle Aenderungen werden im Audit-Log protokolliert.
 */
class GasStation extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, SoftDeletes, Auditable;

    protected $fillable = [
        'tenant_id',
        'name',
        'brand_id',
        'station_number',
        // Allgemein
        'sales_channel',
        'ownership_type',
        'district',
        'district_description',
        'region',
        'region_manager',
        'station_number_fuel',
        'station_number_shop',
        'has_toll_terminal',
        // Adresse
        'street',
        'house_number',
        'zip',
        'city',
        'district_part',
        'state',
        'country',
        // Geo
        'latitude',
        'longitude',
        // Kontakt
        'academic_title',
        'contact_first_name',
        'contact_last_name',
        'phone',
        'fax',
        'email',
        'website',
        // Geschaeftsdaten
        'tax_id',
        'trade_register',
        // Ausstattung
        'num_pumps',
        'has_shop',
        'has_car_wash',
        'opening_hours',
        'first_opening_ok',
        'first_opening_dk',
        'services',
        // Shop
        'shop_size',
        'shop_type',
        'shop_class',
        'shop_setup_date',
        'nielsen_area',
        'price_region',
        'assortment_level',
        'shop_partner',
        'shop_operation_number',
        // Medien
        'logo',
        'photos',
        // Wettbewerb
        'competitors',
        // Sonstiges
        'notes',
        'is_active',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'num_pumps' => 'integer',
            'has_shop' => 'boolean',
            'has_car_wash' => 'boolean',
            'has_toll_terminal' => 'boolean',
            'opening_hours' => 'array',
            'first_opening_ok' => 'date',
            'first_opening_dk' => 'date',
            'shop_setup_date' => 'date',
            'services' => 'array',
            'photos' => 'array',
            'competitors' => 'array',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    // --- Beziehungen ---

    /**
     * Marke der Tankstelle (Aral, Shell, etc.).
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Bankkonten dieser Tankstelle.
     */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(GasStationBankAccount::class);
    }

    /**
     * Zugewiesene Mitarbeiter (Many-to-Many).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'gas_station_user')
            ->withPivot(['station_role', 'is_primary', 'assigned_at'])
            ->withTimestamps();
    }

    /**
     * Zugewiesene Kunden (Many-to-Many).
     */
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_gas_station');
    }

    /**
     * Zugewiesene Lieferanten (Many-to-Many).
     */
    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'supplier_gas_station');
    }

    /**
     * Dokumente dieser Tankstelle.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Schichten dieser Tankstelle.
     */
    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    /**
     * Schichtvorlagen dieser Tankstelle.
     */
    public function shiftTemplates(): HasMany
    {
        return $this->hasMany(ShiftTemplate::class);
    }

    // --- Hilfsmethoden ---

    /**
     * Vollstaendige Adresse als String (mit Hausnummer).
     */
    public function getFullAddressAttribute(): string
    {
        $streetLine = trim("{$this->street} {$this->house_number}");

        return trim("{$streetLine}, {$this->zip} {$this->city}");
    }

    /**
     * Anrede-Anschrift zusammengesetzt:
     * Brand (ausser Sonstige) + Tankstelle + Vorname + Nachname
     */
    public function getSalutationAddressAttribute(): string
    {
        $parts = [];

        if ($this->brand && strtolower($this->brand->name) !== 'sonstige') {
            $parts[] = $this->brand->name;
        }

        $parts[] = 'Tankstelle';

        if ($this->contact_first_name) {
            $parts[] = $this->contact_first_name;
        }

        if ($this->contact_last_name) {
            $parts[] = $this->contact_last_name;
        }

        return implode(' ', $parts);
    }

    /**
     * Berechnet die woechentlichen Oeffnungsstunden aus dem opening_hours JSON.
     */
    public function getWeeklyOpeningHoursAttribute(): ?float
    {
        if (empty($this->opening_hours)) {
            return null;
        }

        $totalMinutes = 0;

        foreach ($this->opening_hours as $day => $hours) {
            if (! empty($hours['open']) && ! empty($hours['close'])) {
                try {
                    $open = Carbon::createFromFormat('H:i', $hours['open']);
                    $close = Carbon::createFromFormat('H:i', $hours['close']);
                    $totalMinutes += $open->diffInMinutes($close);
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return round($totalMinutes / 60, 1);
    }
}
