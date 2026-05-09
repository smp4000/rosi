<?php

namespace App\Models\Kiosk;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderLine extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'kiosk_order_lines';

    protected $fillable = [
        'invoice_id', 'article_id', 'typ', 'lieferschein_nr', 'lieferschein_datum',
        'paket', 'ausgabe', 'menge', 'einzelpreis_netto', 'einzelpreis_brutto',
        'mwst_satz', 'gesamt_netto', 'gesamt_brutto',
    ];

    protected function casts(): array
    {
        return [
            'lieferschein_datum' => 'date',
            'menge' => 'integer',
            'einzelpreis_netto' => 'decimal:4',
            'einzelpreis_brutto' => 'decimal:4',
            'mwst_satz' => 'decimal:2',
            'gesamt_netto' => 'decimal:4',
            'gesamt_brutto' => 'decimal:4',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
