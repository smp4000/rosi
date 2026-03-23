<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rechnungsposition aus ZUGFeRD-XML.
 */
class InvoiceItem extends \Illuminate\Database\Eloquent\Model
{
    use HasUuids;

    protected $fillable = [
        'invoice_id',
        'article',
        'quantity',
        'unit_price',
        'total_price',
        'date',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'total_price' => 'decimal:2',
            'date' => 'date',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
