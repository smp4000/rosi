<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Import-Protokoll fuer CSV-Artikel-Imports.
 */
class ArticleImport extends \Illuminate\Database\Eloquent\Model
{
    use HasUuids;

    protected $fillable = [
        'gas_station_id',
        'station_number',
        'csv_printed_at',
        'filename',
        'articles_total',
        'articles_created',
        'articles_updated',
        'articles_unchanged',
        'status',
        'error_message',
        'imported_by',
    ];

    protected function casts(): array
    {
        return [
            'csv_printed_at' => 'datetime',
            'articles_total' => 'integer',
            'articles_created' => 'integer',
            'articles_updated' => 'integer',
            'articles_unchanged' => 'integer',
        ];
    }

    // ── Relationships ──

    public function gasStation(): BelongsTo
    {
        return $this->belongsTo(GasStation::class);
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(ArticleChange::class);
    }

    // ── Helpers ──

    /**
     * Zusammenfassung als lesbarer String.
     */
    public function getSummaryAttribute(): string
    {
        return sprintf(
            '%d gesamt | %d neu | %d aktualisiert | %d unverändert',
            $this->articles_total,
            $this->articles_created,
            $this->articles_updated,
            $this->articles_unchanged
        );
    }
}
