<?php

namespace App\Http\Controllers;

use App\Models\ShiftSettlement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streamt Anhaenge einer Schichtabrechnung (Bon-Fotos, Kassenbericht-Foto)
 * direkt ueber den Server. Funktioniert auch ohne storage:link-Symlink.
 *
 * Nur fuer authentifizierte Partner-Panel-Nutzer (eigener Tenant).
 */
class ShiftSettlementAttachmentController extends Controller
{
    public function show(Request $request, string $settlement, string $type, ?string $returnId = null): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $record = ShiftSettlement::where('id', $settlement)
            ->where('tenant_id', $user->tenant_id)
            ->first();

        if (! $record) {
            abort(404);
        }

        $path = match ($type) {
            'cash_report' => $record->cash_report_photo,
            'return' => $returnId ? $record->returns()->where('id', $returnId)->value('photo') : null,
            default => null,
        };

        if (empty($path) || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return response(
            Storage::disk('public')->get($path),
            200,
        )
            ->header('Content-Type', Storage::disk('public')->mimeType($path) ?: 'image/jpeg')
            ->header('Cache-Control', 'private, max-age=300');
    }
}
