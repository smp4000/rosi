<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Versionshistorie der POS-App.
 * Wird zentral in Rosi gepflegt und per API an die App geliefert.
 */
class AppVersion extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'platform',
        'version',
        'release_date',
        'changes',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'changes' => 'array',
            'is_published' => 'boolean',
        ];
    }

    // ── Scopes ───────────────────────────────────────

    /** Nur veroeffentlichte Versionen */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /** Neueste zuerst */
    public function scopeLatestFirst($query)
    {
        return $query->orderBy('release_date', 'desc')->orderBy('version', 'desc');
    }
}
