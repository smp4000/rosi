<?php

namespace App\Models\Kiosk;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Article extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'kiosk_articles';

    protected $fillable = [
        'tenant_id',
        'supplier',
        'objekt',
        'ean',
        'weekday',
        'bezeichnung',
        'aktueller_preis_netto',
        'aktueller_preis_brutto',
        'mwst_satz',
        'ek',
        'is_pending',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'aktueller_preis_netto' => 'decimal:4',
            'aktueller_preis_brutto' => 'decimal:4',
            'mwst_satz' => 'decimal:2',
            'ek' => 'decimal:4',
            'is_pending' => 'boolean',
            'last_seen_at' => 'datetime',
            'weekday' => 'integer',
        ];
    }

    public function issues(): HasMany
    {
        return $this->hasMany(ArticleIssue::class);
    }

    public function priceChangeLog(): HasMany
    {
        return $this->hasMany(PriceChangeLog::class);
    }

    public function getMargeAttribute(): ?float
    {
        if ($this->ek === null) {
            return null;
        }
        return (float) $this->aktueller_preis_netto - (float) $this->ek;
    }
}
