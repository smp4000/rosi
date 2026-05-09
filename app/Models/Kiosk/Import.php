<?php

namespace App\Models\Kiosk;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Import extends Model
{
    use HasUuids, BelongsToTenant;

    protected $table = 'kiosk_imports';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'invoice_id', 'filename', 'file_hash',
        'status', 'articles_inserted', 'articles_updated',
        'articles_skipped', 'error_message', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
