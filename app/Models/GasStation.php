<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
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
        'brand',
        'station_number',
        // Adresse
        'street',
        'zip',
        'city',
        'state',
        'country',
        // Geo
        'latitude',
        'longitude',
        // Kontakt
        'phone',
        'fax',
        'email',
        // Geschaeftsdaten
        'tax_id',
        'trade_register',
        // Ausstattung
        'num_pumps',
        'has_shop',
        'has_car_wash',
        'opening_hours',
        'services',
        // Medien
        'logo',
        'photos',
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
            'opening_hours' => 'array',
            'services' => 'array',
            'photos' => 'array',
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
     * Vollstaendige Adresse als String.
     */
    public function getFullAddressAttribute(): string
    {
        return trim("{$this->street}, {$this->zip} {$this->city}");
    }
}
