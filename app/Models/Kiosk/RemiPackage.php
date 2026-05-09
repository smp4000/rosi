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

class RemiPackage extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'kiosk_remi_packages';

    protected $fillable = [
        'tenant_id', 'gas_station_id', 'user_id',
        'paket', 'paket_datum', 'mitarbeiter',
        'status', 'notiz', 'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'paket_datum' => 'date',
            'scanned_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(RemiItem::class);
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
