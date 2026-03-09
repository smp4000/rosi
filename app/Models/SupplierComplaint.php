<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reklamationen-Model fuer Lieferanten.
 * Verwaltet Beschwerden und deren Bearbeitungsstatus.
 */
class SupplierComplaint extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'supplier_complaints';

    protected $fillable = [
        'supplier_id',
        'tenant_id',
        'gas_station_id',
        'subject',
        'description',
        'status',
        'priority',
        'resolved_at',
        'resolved_by',
        'attachments',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'attachments' => 'array',
        ];
    }

    // --- Beziehungen ---

    /**
     * Zugehoeriger Lieferant.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Betroffene Tankstelle (optional).
     */
    public function gasStation(): BelongsTo
    {
        return $this->belongsTo(GasStation::class);
    }

    /**
     * Bearbeiter der Reklamation.
     */
    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Ersteller der Reklamation.
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // --- Hilfsmethoden ---

    /**
     * Ist die Reklamation offen?
     */
    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'in_progress']);
    }

    /**
     * Ist die Reklamation abgeschlossen?
     */
    public function isResolved(): bool
    {
        return in_array($this->status, ['resolved', 'closed']);
    }
}
