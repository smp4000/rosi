<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AppVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppVersionController extends ApiController
{
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
            // "app" als Alias fuer "android" akzeptieren
            $platform = $request->platform === 'app' ? 'android' : $request->platform;
            $query->where('platform', $platform);
        }

        $versions = $query->get()->map(fn (AppVersion $v) => [
            'platform' => $v->platform,
            'version' => $v->version,
            'date' => $v->release_date->format('d.m.Y'),
            'changes' => $v->changes,
        ]);

        return $this->success($versions);
    }
}
