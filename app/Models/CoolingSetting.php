<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Mobile-Alerts-Einstellungen (phoneid) pro Mandant, optional pro Station.
 */
class CoolingSetting extends Model
{
    use HasUuids, BelongsToTenant;

    public const DEFAULT_BASE_URL = 'https://www.data199.com/api/pv1';

    protected $fillable = [
        'tenant_id', 'station_id', 'phoneid', 'base_url', 'enabled', 'poll_interval_min',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'poll_interval_min' => 'integer',
        ];
    }

    public function baseUrlOrDefault(): string
    {
        return $this->base_url ?: self::DEFAULT_BASE_URL;
    }
}
