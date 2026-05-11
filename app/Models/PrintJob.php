<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PrintJob extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'job_type',
        'reference',
        'payload',
        'status',
        'created_by',
        'printed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'printed_at' => 'datetime',
        ];
    }

    // Status-Konstanten
    public const STATUS_PENDING = 'pending';
    public const STATUS_PRINTING = 'printing';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';
}
