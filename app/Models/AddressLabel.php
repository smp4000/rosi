<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gespeichertes Adress-Etikett zum Nachdrucken (siehe Migration).
 */
class AddressLabel extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'sender_station_id',
        'sender_text',
        'recipient_name',
        'recipient_street',
        'recipient_city',
        'print_count',
        'last_printed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'print_count' => 'integer',
            'last_printed_at' => 'datetime',
        ];
    }

    public function senderStation(): BelongsTo
    {
        return $this->belongsTo(GasStation::class, 'sender_station_id');
    }
}
