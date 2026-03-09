<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentShiftPagesController extends Controller
{
    public function index(): Response
    {
        $departments = Department::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($d) => ['id' => (int) $d->id, 'name' => $d->name])
            ->values();

        $shifts = Shift::query()
            ->orderBy('name')
            ->get(['id', 'name', 'start_time', 'end_time', 'department_id', 'attendance_credit_code'])
            ->map(fn ($s) => [
                'id' => (int) $s->id,
                'name' => $s->name,
                'start_time' => $s->start_time,
                'end_time' => $s->end_time,
                'department_id' => $s->department_id !== null ? (int) $s->department_id : null,
                'attendance_credit_code' => $s->attendance_credit_code,
            ])
            ->values();

        return Inertia::render('HR/DodjelaSmjenaOdjelu', [
            'departments' => $departments,
            'shifts' => $shifts,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'shift_ids' => ['nullable', 'array'],
            'shift_ids.*' => ['integer', 'exists:shifts,id'],
        ]);

        $departmentId = (int) $validated['department_id'];
        $shiftIds = collect($validated['shift_ids'] ?? [])
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        DB::transaction(function () use ($departmentId, $shiftIds) {
            // Unassign previously assigned shifts not in the selection
            Shift::query()
                ->where('department_id', $departmentId)
                ->whereNotIn('id', $shiftIds)
                ->update(['department_id' => null]);

            // Assign selected shifts to the department
            if (!empty($shiftIds)) {
                Shift::query()
                    ->whereIn('id', $shiftIds)
                    ->update(['department_id' => $departmentId]);
            }
        });

        return redirect()->route('hr.smjene.dodjela');
    }
}
