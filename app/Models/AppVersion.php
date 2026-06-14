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
        'version_code',
        'apk_path',
        'apk_size',
        'release_date',
        'changes',
        'is_published',
        'is_mandatory',
    ];

    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'changes' => 'array',
            'is_published' => 'boolean',
            'is_mandatory' => 'boolean',
            'version_code' => 'integer',
            'apk_size' => 'integer',
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

    // ── Hilfsmethoden ────────────────────────────────

    /** Hat diese Version eine installierbare APK? */
    public function hasApk(): bool
    {
        return ! empty($this->apk_path)
            && \Illuminate\Support\Facades\Storage::disk('public')->exists($this->apk_path);
    }

    /** Oeffentliche Download-URL fuer die APK (eigene Route mit korrektem MIME). */
    public function downloadUrl(): ?string
    {
        return $this->hasApk()
            ? route('api.v1.app-version.download', ['version' => $this->version])
            : null;
    }
}
