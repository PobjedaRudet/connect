<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\AttendanceService;
use Illuminate\Http\Request;

class EmployeeAttendanceController extends Controller
{
    private function appendFullNameToRecord(array $result): array
    {
        $fullName = null;

        if (!empty($result['employee_full_name']) && is_string($result['employee_full_name'])) {
            $fullName = trim($result['employee_full_name']);
        }

        $employeeId = null;

        if (isset($result['employee_id'])) {
            $employeeId = (int) $result['employee_id'];
        }

        if (!$employeeId && isset($result['record'])) {
            $record = $result['record'];
            if (is_array($record) && isset($record['employee_id'])) {
                $employeeId = (int) $record['employee_id'];
            } elseif (is_object($record) && isset($record->employee_id)) {
                $employeeId = (int) $record->employee_id;
            }
        }

        if (!$employeeId && isset($result['pass'])) {
            $pass = $result['pass'];
            if (is_array($pass) && isset($pass['employee_id'])) {
                $employeeId = (int) $pass['employee_id'];
            } elseif (is_object($pass) && isset($pass->employee_id)) {
                $employeeId = (int) $pass->employee_id;
            }
        }

        if ($fullName === null || $fullName === '') {
            if (!$employeeId) {
                return $result;
            }

            $employee = Employee::query()->select(['id', 'firstName', 'lastName'])->find($employeeId);
            if (!$employee) {
                return $result;
            }

            $fullName = trim((string) $employee->firstName . ' ' . (string) $employee->lastName);
        }

        if ($fullName === '') {
            return $result;
        }

        if (isset($result['record'])) {
            if (is_array($result['record'])) {
                $result['record']['full_name'] = $fullName;
            } elseif (is_object($result['record'])) {
                $result['record']->full_name = $fullName;
            }
        }

        if (isset($result['pass'])) {
            if (is_array($result['pass'])) {
                $result['pass']['full_name'] = $fullName;
            } elseif (is_object($result['pass'])) {
                $result['pass']->full_name = $fullName;
            }
        }

        return $result;
    }

    private function resolveScanHttpStatus(array $result): int
    {
        return match ($result['status'] ?? '') {
            'checkin' => 201,
            'checkout', 'pass-open', 'pass-closed' => 200,
            default => 404,
        };
    }

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
        $result = $this->appendFullNameToRecord($result);

        if (isset($result['record']) && is_array($result['record'])) {
            $tz = config('app.timezone');
            $timestampFields = ['entry_time', 'exit_time', 'effective_start', 'created_at', 'updated_at'];

            foreach ($timestampFields as $field) {
                if (isset($result['record'][$field]) && !empty($result['record'][$field])) {
                    try {
                        $dt = \Carbon\Carbon::parse($result['record'][$field])->setTimezone($tz);
                        $result['record'][$field . '_local'] = $dt->format('Y-m-d H:i:s');
                    } catch (\Throwable $e) {}
                }
            }
        }

        return response()->json($result, $this->resolveScanHttpStatus($result));
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
        $result = $this->appendFullNameToRecord($result);

        return response()->json($result, 201);
    }
}
