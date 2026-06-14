<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Archiviertes PDF (Abschriften-/MHD-Bericht). Die Datei liegt auf disk 'local';
 * dieser Datensatz haelt Metadaten und Kennzahlen fuer die Listendarstellung.
 */
class GeneratedReport extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, Auditable;

    protected $fillable = [
        'tenant_id',
        'station_id',
        'user_id',
        'type',
        'title',
        'file_path',
        'file_size',
        'period_from',
        'period_to',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'meta' => 'array',
        ];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(GasStation::class, 'station_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
