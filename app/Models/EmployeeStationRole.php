<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

/**
 * Rollen-Zuweisung: Mitarbeiter x Rolle x Tankstelle.
 * gas_station_id NULL bedeutet: Rolle gilt im ganzen Betrieb.
 *
 * Beispiel: Alexandra ist Kassierer in Fulda und Schichtleiter in Petersberg
 * → zwei Zeilen mit unterschiedlicher gas_station_id.
 */
class EmployeeStationRole extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'role_id',
        'gas_station_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function gasStation(): BelongsTo
    {
        return $this->belongsTo(GasStation::class);
    }
}
