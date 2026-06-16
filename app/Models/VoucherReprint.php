<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Protokoll eines Gutschein-Etikett-Nachdrucks (wer, welche Nummer, wohin, wann).
 */
class VoucherReprint extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'station_id',
        'voucher_id',
        'voucher_number',
        'user_id',
        'print_job_id',
        'target_agent_id',
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(GasStation::class, 'station_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function targetAgent(): BelongsTo
    {
        return $this->belongsTo(PrintAgent::class, 'target_agent_id');
    }
}
