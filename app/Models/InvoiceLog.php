<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Versand-Protokoll fuer E-Mails und Druck.
 * Jeder Versand-Versuch wird als eigener Eintrag gespeichert (unveraenderbar).
 */
class InvoiceLog extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'batch_id',
        'channel',
        'status',
        'recipient',
        'error_message',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    // ── Beziehungen ──

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InvoiceBatch::class, 'batch_id');
    }

    // ── Static Helpers ──

    /**
     * E-Mail-Versand protokollieren.
     */
    public static function logEmail(
        Invoice $invoice,
        string $status,
        string $recipient,
        ?string $error = null,
        ?string $batchId = null
    ): self {
        return static::create([
            'tenant_id' => $invoice->gasStation?->tenant_id ?? session('tenant_id'),
            'invoice_id' => $invoice->id,
            'batch_id' => $batchId,
            'channel' => 'email',
            'status' => $status,
            'recipient' => $recipient,
            'error_message' => $error ? substr($error, 0, 2000) : null,
        ]);
    }

    /**
     * Druck protokollieren.
     */
    public static function logPrint(Invoice $invoice, ?string $batchId = null): self
    {
        return static::create([
            'tenant_id' => $invoice->gasStation?->tenant_id ?? session('tenant_id'),
            'invoice_id' => $invoice->id,
            'batch_id' => $batchId,
            'channel' => 'print',
            'status' => 'printed',
        ]);
    }
}
