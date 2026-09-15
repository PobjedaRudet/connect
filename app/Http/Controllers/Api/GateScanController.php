<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GateService;
use Illuminate\Http\Request;

/**
 * Endpoint za terminal na kapiji (okretna vrata na ulazu u firmu).
 * Odvojen od /api/scan (terminal kod objekta) — vidi GateService.
 */
class GateScanController extends Controller
{
    public function scan(Request $request, GateService $service)
    {
        $data = $request->validate([
            'rfid_code' => 'required|string',
            'terminal_id' => 'nullable|string',
        ]);

        $result = $service->processScan($data['rfid_code'], $data['terminal_id'] ?? null);

        $status = match ($result['status'] ?? '') {
            'gate-in', 'gate-out' => 201,
            default => 404,
        };

        return response()->json($result, $status);
    }
}
