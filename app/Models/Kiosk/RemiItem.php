<?php

namespace App\Models\Kiosk;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemiItem extends Model
{
    use HasUuids;

    protected $table = 'kiosk_remi_items';

    public $timestamps = false;

    protected $fillable = [
        'remi_package_id', 'article_id', 'ausgabe', 'menge',
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

    public function package(): BelongsTo
    {
        return $this->belongsTo(RemiPackage::class, 'remi_package_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
