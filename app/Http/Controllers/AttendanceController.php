<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller
{
    //
    public function store(Request $request)
    {
       Log::info('Attendance request received', [
            'employee_id' => $request->input('employee_id'),
            'date' => now()->toDateString(),
            'in' => now(),
            'out' => null

        ]);

        $attendance = new Attendance();
        $attendance->employee_id = $request->input('employee_id');
        $attendance->date = now()->toDateString();
        $attendance->in = now();
        $attendance->out = null;
        $attendance->save();
        $zadnji = Attendance::where('employee_id', $request->input('employee_id'))
            ->whereDate('date', now()->toDateString())
            ->orderBy('in', 'desc')
            ->first();

        return response()->json(['message' => "Attendance recorded successfully {$zadnji}"], 201);
    }
}
