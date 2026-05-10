<?php

namespace App\Models\Kiosk;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\GasStation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'newspaper_invoices';

    protected $fillable = [
        'tenant_id', 'supplier_id', 'gas_station_id', 'supplier',
        'rechnungsnummer', 'rechnungsdatum',
        'lieferdatum_von', 'lieferdatum_bis', 'kundennummer',
        'gesamtbetrag', 'filename', 'file_hash',
    ];

    protected function casts(): array
    {
        return [
            'rechnungsdatum' => 'date',
            'lieferdatum_von' => 'date',
            'lieferdatum_bis' => 'date',
            'gesamtbetrag' => 'decimal:4',
        ];
    }

    public function orderLines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    public function supplierRel(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function gasStation(): BelongsTo
    {
        return $this->belongsTo(GasStation::class);
    }
}
