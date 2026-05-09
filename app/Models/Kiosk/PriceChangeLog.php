<?php

namespace App\Models\Kiosk;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceChangeLog extends Model
{
    use HasUuids;

    protected $table = 'kiosk_price_change_log';

    public $timestamps = false;

    protected $fillable = [
        'article_id', 'change_type',
        'old_preis_netto', 'new_preis_netto',
        'old_preis_brutto', 'new_preis_brutto',
        'old_mwst', 'new_mwst',
        'old_ek', 'new_ek',
        'source', 'invoice_id', 'changed_at', 'note',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
            'old_preis_netto' => 'decimal:4',
            'new_preis_netto' => 'decimal:4',
            'old_preis_brutto' => 'decimal:4',
            'new_preis_brutto' => 'decimal:4',
            'old_mwst' => 'decimal:2',
            'new_mwst' => 'decimal:2',
            'old_ek' => 'decimal:4',
            'new_ek' => 'decimal:4',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
