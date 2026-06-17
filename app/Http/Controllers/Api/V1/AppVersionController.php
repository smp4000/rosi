<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AppVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AppVersionController extends ApiController
{
    /** Plattform-Aliasse fuer die POS-App ("app" historisch, "android" neuer). */
    private const APP_PLATFORMS = ['android', 'app'];

    /**
     * Versionshistorie.
     * GET /api/v1/app-versions?platform=app|web
     *
     * Ohne platform-Parameter werden alle Versionen zurueckgegeben.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'platform' => 'nullable|in:android,web,app',
        ]);

        $query = AppVersion::published()->latestFirst();

        if ($request->filled('platform')) {
            // "app" und "android" meinen dieselbe Plattform
            if (in_array($request->platform, self::APP_PLATFORMS, true)) {
                $query->whereIn('platform', self::APP_PLATFORMS);
            } else {
                $query->where('platform', $request->platform);
            }
        }

        $versions = $query->get()->map(fn (AppVersion $v) => [
            'platform' => $v->platform,
            'version' => $v->version,
            'date' => $v->release_date->format('d.m.Y'),
            'changes' => $v->changes,
        ]);

        return $this->success($versions);
    }

    /**
     * Neueste installierbare App-Version (fuer den In-App-Updater).
     * GET /api/v1/app-version/latest?platform=android
     *
     * Liefert nur Versionen MIT version_code UND hochgeladener APK —
     * sonst koennte die App kein Update durchfuehren. Sortiert nach
     * version_code (technische Nummer), nicht nach dem Anzeige-String.
     */
    public function latest(Request $request): JsonResponse
    {
        $latest = AppVersion::published()
            ->whereIn('platform', self::APP_PLATFORMS)
            ->whereNotNull('version_code')
            ->whereNotNull('apk_path')
            ->orderByDesc('version_code')
            ->get()
            ->first(fn (AppVersion $v) => $v->hasApk()); // Datei muss wirklich existieren

        if (! $latest) {
            return $this->success([
                'available' => false,
            ], 'Keine installierbare Version vorhanden.');
        }

        return $this->success([
            'available' => true,
            'version' => $latest->version,
            'version_code' => $latest->version_code,
            'release_date' => $latest->release_date->format('d.m.Y'),
            'mandatory' => $latest->is_mandatory,
            'size' => $latest->apk_size,
            'changes' => $latest->changes ?? [],
            'download_url' => $latest->downloadUrl(),
        ]);
    }

    /**
     * APK-Download mit korrektem MIME-Type.
     * GET /api/v1/app-version/download/{version}
     *
     * Eigene Route statt direktem storage-Link: garantiert den
     * Android-MIME (application/vnd.android.package-archive) und umgeht
     * Server-Konfigurationen, die .apk-Dateien blockieren.
     */
    public function download(string $version): StreamedResponse|JsonResponse
    {
        // Es kann mehrere Zeilen mit gleicher Version geben (Historie-Eintrag aus
        // dem Seeder OHNE APK + hochgeladene Zeile MIT APK). Gezielt die Zeile
        // nehmen, die wirklich eine installierbare APK hat.
        $entry = AppVersion::published()
            ->whereIn('platform', self::APP_PLATFORMS)
            ->where('version', $version)
            ->whereNotNull('apk_path')
            ->orderByDesc('version_code')
            ->get()
            ->first(fn (AppVersion $v) => $v->hasApk());

        if (! $entry) {
            return $this->error('Diese Version steht nicht zum Download bereit.', 404);
        }

        return Storage::disk('public')->download(
            $entry->apk_path,
            "rosi-pos-{$entry->version}.apk",
            ['Content-Type' => 'application/vnd.android.package-archive'],
        );
    }
}
