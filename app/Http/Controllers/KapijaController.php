<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Pass;
use Carbon\Carbon;
use Inertia\Inertia;

class KapijaController extends Controller
{
    public function index()
    {
        return Inertia::render('Kapija');
    }

    public function data()
    {
        $today = Carbon::today();

        // Statistics
        $totalEmployees = Employee::where(function ($q) {
            $q->whereNull('Active')->orWhere('Active', 1);
        })->count();

        $currentlyWorking = AttendanceRecord::where('status', 'working')
            ->whereDate('entry_time', $today)
            ->count();

        $checkedOutToday = AttendanceRecord::where('status', 'left')
            ->whereDate('entry_time', $today)
            ->count();

        $totalCheckedInToday = $currentlyWorking + $checkedOutToday;

        $activePasses = Pass::where('status', 'open')
            ->whereDate('start_time', $today)
            ->count();

        // Recent 10 check-ins/check-outs
        $recentRecords = AttendanceRecord::with('employee:id,firstName,lastName')
            ->where('entry_time', '>=', $today)
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                'id'        => $r->id,
                'employee'  => $r->employee ? $r->employee->firstName . ' ' . $r->employee->lastName : '—',
                'type'      => $r->exit_time ? 'odjava' : 'prijava',
                'time'      => $r->exit_time
                    ? $r->exit_time->format('H:i')
                    : ($r->entry_time ? $r->entry_time->format('H:i') : '—'),
                'timestamp' => $r->updated_at->toISOString(),
            ]);

        // Today's passes
        $passes = Pass::with('employee:id,firstName,lastName')
            ->whereDate('start_time', $today)
            ->orderByDesc('start_time')
            ->get()
            ->map(fn($p) => [
                'id'         => $p->id,
                'employee'   => $p->employee ? $p->employee->firstName . ' ' . $p->employee->lastName : '—',
                'type'       => $p->type,
                'start_time' => $p->start_time->format('H:i'),
                'end_time'   => $p->end_time ? $p->end_time->format('H:i') : null,
                'status'     => $p->status,
            ]);

        return response()->json([
            'stats' => [
                'totalEmployees'     => $totalEmployees,
                'currentlyWorking'   => $currentlyWorking,
                'checkedOutToday'    => $checkedOutToday,
                'totalCheckedInToday'=> $totalCheckedInToday,
                'activePasses'       => $activePasses,
                'percentage'         => $totalEmployees > 0
                    ? round(($currentlyWorking / $totalEmployees) * 100, 1)
                    : 0,
            ],
            'recentRecords' => $recentRecords,
            'passes'        => $passes,
        ]);
    }
}
