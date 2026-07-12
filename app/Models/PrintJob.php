<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintJob extends Model
{
    use HasUuids;
    use \App\Traits\BelongsToTenant; // T-2: automatischer Mandanten-Filter (TenantScope) + tenant_id-Autofill

    protected $fillable = [
        'tenant_id',
        'station_id',
        'job_type',
        'reference',
        'reference_type',
        'printer_name',
        'agent_id',
        'target_agent_id',
        'payload',
        'status',
        'expires_at',
        'error_message',
        'attempts',
        'created_by',
        'user_id',
        'printed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'printed_at' => 'datetime',
            'expires_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    // Status-Konstanten
    public const STATUS_PENDING = 'pending';
    public const STATUS_PRINTING = 'printing';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';

    // ── Beziehungen ──────────────────────────────────

    public function station(): BelongsTo
    {
        return $this->belongsTo(GasStation::class, 'station_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(PrintAgent::class, 'agent_id');
    }

    public function targetAgent(): BelongsTo
    {
        return $this->belongsTo(PrintAgent::class, 'target_agent_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ── Scopes ───────────────────────────────────────

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PENDING);
    }

    /** Offene Jobs einer Station, aelteste zuerst, nicht abgelaufen. */
    public function scopeQueuedForStation(Builder $q, string $stationId): Builder
    {
        return $q->where('station_id', $stationId)
            ->where('status', self::STATUS_PENDING)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderBy('created_at');
    }

    /**
     * Offene Jobs, die DIESER Agent drucken soll: entweder gezielt an ihn
     * adressiert ODER ohne Ziel, wenn er der Default-Agent der Station ist.
     */
    public function scopeQueuedForAgent(Builder $q, PrintAgent $agent): Builder
    {
        // Gibt es ueberhaupt einen Standard-Agenten an der Station? Wenn NICHT,
        // darf jeder Agent die ziellosen Jobs holen (sonst blieben Tresor-/
        // Tankbetrug-Etiketten ohne ausdruecklichen Drucker fuer immer haengen).
        $stationHasDefault = PrintAgent::where('station_id', $agent->station_id)
            ->where('is_active', true)
            ->where('is_default', true)
            ->exists();

        return $q->where('station_id', $agent->station_id)
            ->where('status', self::STATUS_PENDING)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(function ($q) use ($agent, $stationHasDefault) {
                $q->where('target_agent_id', $agent->id);
                if ($agent->is_default || ! $stationHasDefault) {
                    $q->orWhereNull('target_agent_id');
                }
            })
            ->orderBy('created_at');
    }

    // ── Hilfen ───────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
