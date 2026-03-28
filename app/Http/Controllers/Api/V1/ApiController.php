<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

abstract class ApiController extends Controller
{
    /**
     * Erfolgsantwort zuruecksenden.
     */
    protected function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Fehlerantwort zuruecksenden.
     */
    protected function error(string $message = 'Fehler', int $status = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
