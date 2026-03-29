<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AppVersion;
use Illuminate\Http\JsonResponse;

class AppVersionController extends ApiController
{
    /**
     * Versionshistorie fuer die POS-App.
     * GET /api/v1/app-versions
     */
    public function index(): JsonResponse
    {
        $versions = AppVersion::published()
            ->latestFirst()
            ->get()
            ->map(fn (AppVersion $v) => [
                'version' => $v->version,
                'date' => $v->release_date->format('d.m.Y'),
                'changes' => $v->changes,
            ]);

        return $this->success($versions);
    }
}
