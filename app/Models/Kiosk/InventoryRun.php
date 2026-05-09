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

class InventoryRun extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'kiosk_inventory_runs';

    protected $fillable = [
        'tenant_id', 'gas_station_id', 'user_id',
        'bezeichnung', 'modus', 'stufe',
        'mitarbeiter', 'status', 'notiz', 'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
            'stufe' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'inventory_run_id');
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
