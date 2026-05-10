<?php

namespace App\Models\Kiosk;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItem extends Model
{
    use HasUuids;

    protected $table = 'newspaper_inventory_items';

    public $timestamps = false;

    protected $fillable = [
        'inventory_run_id', 'article_id', 'ausgabe', 'menge',
        'einzelpreis_brutto', 'mwst_satz', 'scanned_ean', 'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'menge' => 'integer',
            'einzelpreis_brutto' => 'decimal:4',
            'mwst_satz' => 'decimal:2',
            'scanned_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(InventoryRun::class, 'inventory_run_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
