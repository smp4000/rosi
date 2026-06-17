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

    // ── Boot ─────────────────────────────────────────

    protected static function booted(): void
    {
        // APK-Groesse + version_code IMMER automatisch aus der Datei ableiten
        // (gilt fuer Admin-Upload, rosi:publish-apk und Seeder gleichermassen).
        // Der echte versionCode aus der APK ist massgeblich und ueberschreibt das
        // manuelle Feld — so kann nie ein falscher Code zur APK gespeichert werden
        // (sonst Update-Endlosschleife im Updater).
        static::saving(function (AppVersion $v) {
            $disk = \Illuminate\Support\Facades\Storage::disk('public');
            if ($v->apk_path && $disk->exists($v->apk_path)) {
                $v->apk_size = $disk->size($v->apk_path);

                // Nur echte APKs parsen; Print-Agent-EXE behaelt den manuell
                // gesetzten version_code (ApkParser wuerde auf .exe fehlschlagen).
                if (! str_ends_with(strtolower($v->apk_path), '.apk')) {
                    return;
                }

                $parsed = \App\Support\ApkParser::readVersion($disk->path($v->apk_path));
                if ($parsed['versionCode'] !== null) {
                    $v->version_code = $parsed['versionCode'];
                }
                // versionName uebernehmen, falls im Formular keiner gesetzt wurde
                if (empty($v->version) && ! empty($parsed['versionName'])) {
                    $v->version = $parsed['versionName'];
                }
            }
        });
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
