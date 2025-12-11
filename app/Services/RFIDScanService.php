<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use Carbon\Carbon;

class RFIDScanService
{
    public function __construct(private ShiftResolver $resolver)
    {
    }

    /**
     * Handle a single RFID scan. If employee currently has an active (working) record,
     * perform checkout; otherwise perform checkin with nearest shift and rounding.
     * Returns array with 'action' => 'check_in'|'check_out', 'record' and optional 'shift'.
     */
    public function handleScan(Employee $employee, ?Carbon $at = null): array
    {
        $now = $at ?? Carbon::now();

        $active = AttendanceRecord::where('employee_id', $employee->id)
            ->where('status', 'working')
            ->latest('id')
            ->first();

        if ($active) {
            // Checkout
            $active->update([
                'exit_time' => $now,
                'status' => 'left',
            ]);

            return [
                'action' => 'check_out',
                'record' => $active->refresh(),
            ];
        }

        // Checkin
        $shift = $this->resolver->resolveNearest($employee, $now);
        $entryTime = $now;
        if ($shift) {
            $shiftStart = Carbon::parse($now->format('Y-m-d') . ' ' . $shift->start_time);
            $entryTime = $this->resolver->roundEffectiveStart($now, $shiftStart);
        }

        $record = AttendanceRecord::create([
            'employee_id' => $employee->id,
            'shift_id' => $shift?->id,
            'entry_time' => $entryTime,
            'status' => 'working',
        ]);

        return [
            'action' => 'check_in',
            'record' => $record,
            'shift' => $shift,
        ];
    }
}
