<?php

namespace App\Http\Controllers;

use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmployeeAttendanceController extends Controller
{
    /**
     * Scan endpoint - za prijavu/odjavu radnika preko RFID terminala
     * Prima rfid_code i automatski detektuje da li je prijava ili odjava
     */
    public function scan(Request $request, AttendanceService $service)
    {
        $data = $request->validate([
            'rfid_code' => 'required|string',
            'terminal_id' => 'nullable|string',
        ]);

        $result = $service->processScan($data['rfid_code'], $data['terminal_id'] ?? null);

        // Add localized (app timezone) representations of timestamps for client clarity
        $tz = config('app.timezone');
        $timestampFields = ['entry_time', 'exit_time', 'effective_start', 'created_at', 'updated_at'];

        // Top-level timestamp fields (if any)
        foreach ($timestampFields as $field) {
            if (isset($result[$field]) && !empty($result[$field])) {
                try {
                    $dt = \Carbon\Carbon::parse($result[$field])->setTimezone($tz);
                    $result[$field . '_local'] = $dt->format('Y-m-d H:i:s');
                } catch (\Throwable $e) {}
            }
        }

        // Nested record timestamps
        if (isset($result['record']) && is_array($result['record'])) {
            foreach ($timestampFields as $field) {
                if (isset($result['record'][$field]) && !empty($result['record'][$field])) {
                    try {
                        $dt = \Carbon\Carbon::parse($result['record'][$field])->setTimezone($tz);
                        $result['record'][$field . '_local'] = $dt->format('Y-m-d H:i:s');
                    } catch (\Throwable $e) {}
                }
            }
        }

        $httpStatus = $result['status'] === 'checkin' ? 201 : ($result['status'] === 'checkout' ? 200 : 404);
        Log::info('RFID Scan Result', $result);

        return response()->json($result, $httpStatus);
    }

    /**
     * Offline scan endpoint - za sinhronizaciju offline zapisa sa terminala
     * Prima rfid_code, terminal_id i timestamp zapisa
     */
    public function offlineScan(Request $request, AttendanceService $service)
    {
        $data = $request->validate([
            'rfid_code' => 'required|string',
            'terminal_id' => 'required|string',
            'timestamp' => 'required|string',
        ]);

        $result = $service->processOfflineScan($data['rfid_code'], $data['terminal_id'], $data['timestamp']);

        Log::info('Offline Scan Result', $result);

        return response()->json($result, 201);
    }
}
