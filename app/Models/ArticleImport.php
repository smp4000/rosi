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
        'articles_deleted',
        'articles_not_found',
        'articles_locked',
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
            'articles_deleted' => 'integer',
            'articles_not_found' => 'integer',
            'articles_locked' => 'integer',
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
        // Sperrlisten-Import
        if ($this->articles_locked > 0) {
            $parts = ["{$this->articles_total} gesamt"];
            $parts[] = "{$this->articles_locked} gesperrt";
            if ($this->articles_not_found > 0) {
                $parts[] = "{$this->articles_not_found} nicht gefunden";
            }
            if ($this->articles_unchanged > 0) {
                $parts[] = "{$this->articles_unchanged} bereits gesperrt";
            }
            return implode(' | ', $parts);
        }

        // Loeschlisten-Import
        if ($this->articles_deleted > 0) {
            $parts = ["{$this->articles_total} gesamt"];
            $parts[] = "{$this->articles_deleted} gelöscht";
            if ($this->articles_not_found > 0) {
                $parts[] = "{$this->articles_not_found} nicht gefunden";
            }
            if ($this->articles_unchanged > 0) {
                $parts[] = "{$this->articles_unchanged} bereits gelöscht";
            }
            return implode(' | ', $parts);
        }

        return sprintf(
            '%d gesamt | %d neu | %d aktualisiert | %d unverändert',
            $this->articles_total,
            $this->articles_created,
            $this->articles_updated,
            $this->articles_unchanged
        );
    }
}
