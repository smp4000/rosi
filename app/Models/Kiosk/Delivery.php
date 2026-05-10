<?php

namespace App\Models\Kiosk;

use App\Models\GasStation;
use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'newspaper_deliveries';

    protected $fillable = [
        'tenant_id', 'gas_station_id', 'user_id',
        'lieferschein_nr', 'lieferschein_datum',
        'mitarbeiter', 'status', 'notiz', 'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'lieferschein_datum' => 'date',
            'scanned_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryItem::class);
    }

    public function gasStation(): BelongsTo
    {
        return $this->belongsTo(GasStation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
