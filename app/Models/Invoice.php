<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Rechnung aus ZUGFeRD-PDF-Import.
 * Verknuepft mit Firmenkunde und Tankstelle.
 */
class Invoice extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory, HasUuids, SoftDeletes, Auditable;
    use \App\Traits\BelongsToTenant; // T-2: automatischer Mandanten-Filter (TenantScope) + tenant_id-Autofill

    protected $fillable = [
        'gas_station_id',
        'batch_id',
        'corporate_customer_id',
        'invoice_number',
        'invoice_date',
        'amount',
        'net_amount',
        'tax_amount',
        'pdf_path',
        'zugferd_data',
        'status',
        'import_status',
        'import_error_message',
        'email_status',
        'email_error',
        'print_status',
        'sent_at',
        'printed_at',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'zugferd_data' => 'array',
            'sent_at' => 'datetime',
            'printed_at' => 'datetime',
        ];
    }

    // ── Relationships ──

    public function gasStation(): BelongsTo
    {
        return $this->belongsTo(GasStation::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InvoiceBatch::class, 'batch_id');
    }

    public function corporateCustomer(): BelongsTo
    {
        return $this->belongsTo(CorporateCustomer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(InvoiceLog::class);
    }

    // ── Scopes ──

    public function scopeForStation($query, string $gasStationId)
    {
        return $query->where('gas_station_id', $gasStationId);
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    // ── Helpers ──

    /**
     * Ist die Rechnung bereits versendet?
     */
    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }

    /**
     * Ist die Rechnung gedruckt?
     */
    public function isPrinted(): bool
    {
        return $this->printed_at !== null;
    }
}
