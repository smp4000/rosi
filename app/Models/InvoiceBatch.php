<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Import-Batch fuer ZUGFeRD-Rechnungen.
 * Trackt Fortschritt und Statistik eines Imports.
 */
class InvoiceBatch extends \Illuminate\Database\Eloquent\Model
{
    use HasUuids;

    protected $fillable = [
        'gas_station_id',
        'name',
        'source',
        'total_files',
        'processed_count',
        'success_count',
        'error_count',
        'duplicate_count',
        'new_customers_count',
        'status',
        'started_at',
        'completed_at',
        'imported_by',
    ];

    protected function casts(): array
    {
        return [
            'total_files' => 'integer',
            'processed_count' => 'integer',
            'success_count' => 'integer',
            'error_count' => 'integer',
            'duplicate_count' => 'integer',
            'new_customers_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // ── Relationships ──

    public function gasStation(): BelongsTo
    {
        return $this->belongsTo(GasStation::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'batch_id');
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    // ── Accessors ──

    public function getSummaryAttribute(): string
    {
        $parts = ["{$this->total_files} gesamt"];
        if ($this->success_count > 0) {
            $parts[] = "{$this->success_count} erfolgreich";
        }
        if ($this->error_count > 0) {
            $parts[] = "{$this->error_count} Fehler";
        }
        if ($this->duplicate_count > 0) {
            $parts[] = "{$this->duplicate_count} Duplikate";
        }
        if ($this->new_customers_count > 0) {
            $parts[] = "{$this->new_customers_count} neue Kunden";
        }

        return implode(' | ', $parts);
    }

    public function getProgressPercentAttribute(): int
    {
        if ($this->total_files === 0) {
            return 0;
        }

        return (int) round(($this->processed_count / $this->total_files) * 100);
    }
}
