<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Preislisten-Model fuer Lieferanten.
 * Verwaltet Produktpreise mit Gueltigkeitszeitraum.
 */
class SupplierPriceList extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'supplier_price_lists';

    protected $fillable = [
        'supplier_id',
        'tenant_id',
        'product_name',
        'unit',
        'price',
        'valid_from',
        'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    // --- Beziehungen ---

    /**
     * Zugehoeriger Lieferant.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    // --- Hilfsmethoden ---

    /**
     * Pruefen ob der Preis aktuell gueltig ist.
     */
    public function isCurrentlyValid(): bool
    {
        $now = now()->toDateString();

        if ($this->valid_from > $now) {
            return false;
        }

        if ($this->valid_until && $this->valid_until < $now) {
            return false;
        }

        return true;
    }
}
