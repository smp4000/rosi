<?php

namespace App\Console\Commands;

use App\Models\AppVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Eine per SFTP/SSH hochgeladene APK als App-Version veroeffentlichen.
 *
 * Umgeht das Browser-Upload-Limit (Livewire/PHP) bei grossen APKs:
 * 1. APK per SFTP nach storage/app/public/apks/ hochladen
 * 2. php artisan rosi:publish-apk 2.5.0 14
 *
 * Erwartet die Datei unter apks/rosi-pos-{version}.apk (Standard), oder
 * per --file=<pfad relativ zu storage/app/public>.
 */
class PublishApk extends Command
{
    protected $signature = 'rosi:publish-apk
        {version : Anzeige-Version, z.B. 2.5.0}
        {code : Android versionCode (Ganzzahl, muss steigen), z.B. 14}
        {--file= : APK-Pfad relativ zu storage/app/public (Standard: apks/rosi-pos-{version}.apk)}
        {--mandatory : Pflicht-Update (kein "Spaeter"-Button)}
        {--changes= : Changelog, mehrere mit | trennen}';

    protected $description = 'Hochgeladene APK als Android-App-Version veroeffentlichen (latest/Updater)';

    public function handle(): int
    {
        $version = $this->argument('version');
        $code = (int) $this->argument('code');
        $apkPath = $this->option('file') ?: "apks/rosi-pos-{$version}.apk";

        if (! Storage::disk('public')->exists($apkPath)) {
            $this->error("APK nicht gefunden: storage/app/public/{$apkPath}");
            $this->warn('Zuerst per SFTP nach storage/app/public/apks/ hochladen.');
            return self::FAILURE;
        }

        $size = Storage::disk('public')->size($apkPath);

        // Bestehende Changes behalten (z.B. aus dem Seeder), sonst aus --changes
        $existing = AppVersion::where('platform', 'android')->where('version', $version)->first();
        $changes = $existing?->changes ?? [];
        if ($this->option('changes')) {
            $changes = array_values(array_filter(array_map('trim', explode('|', $this->option('changes')))));
        }

        $entry = AppVersion::updateOrCreate(
            ['platform' => 'android', 'version' => $version],
            [
                'version_code' => $code,
                'apk_path' => $apkPath,
                'apk_size' => $size,
                'release_date' => $existing?->release_date ?? now(),
                'changes' => $changes,
                'is_published' => true,
                'is_mandatory' => $this->option('mandatory'),
            ],
        );

        $this->info("Veroeffentlicht: v{$entry->version} (Code {$entry->version_code}), "
            . round($size / 1024 / 1024, 1) . ' MB'
            . ($entry->is_mandatory ? ', Pflicht-Update' : ''));
        $this->line('Download-URL: ' . $entry->downloadUrl());

        return self::SUCCESS;
    }
}
