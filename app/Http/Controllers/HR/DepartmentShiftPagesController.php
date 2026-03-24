<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentShiftPagesController extends Controller
{
    public function index(): Response
    {
        $departments = Department::query()
            ->withCount('employees')
            ->with(['shifts' => function ($query) {
                $query
                    ->select(['id', 'name', 'start_time', 'end_time', 'department_id', 'attendance_credit_code'])
                    ->orderBy('name');
            }])
            ->orderBy('name')
            ->get(['id', 'name', 'description'])
            ->map(fn ($d) => [
                'id' => (int) $d->id,
                'name' => $d->name,
                'description' => $d->description,
                'employee_count' => (int) $d->employees_count,
                'shifts' => $d->shifts->map(fn ($s) => [
                    'id' => (int) $s->id,
                    'name' => $s->name,
                    'start_time' => $this->formatShiftTime($s, 'start_time'),
                    'end_time' => $this->formatShiftTime($s, 'end_time'),
                    'attendance_credit_code' => $s->attendance_credit_code,
                ])->values(),
            ])
            ->values();

        $shifts = Shift::query()
            ->with('department:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'start_time', 'end_time', 'department_id', 'attendance_credit_code'])
            ->map(fn ($s) => [
                'id' => (int) $s->id,
                'name' => $s->name,
                'start_time' => $this->formatShiftTime($s, 'start_time'),
                'end_time' => $this->formatShiftTime($s, 'end_time'),
                'department_id' => $s->department_id !== null ? (int) $s->department_id : null,
                'department_name' => $s->department?->name,
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
        $validated = $this->validateDepartment($request);

        $department = new Department();
        $department->name = $validated['name'];
        $department->description = $validated['description'] ?? null;
        $department->save();

        $this->syncShifts($department, $validated['shift_ids'] ?? []);

        return redirect()->route('hr.smjene.dodjela');
    }

    public function update(Request $request, Department $department)
    {
        $validated = $this->validateDepartment($request, $department);

        $department->name = $validated['name'];
        $department->description = $validated['description'] ?? null;
        $department->save();

        $this->syncShifts($department, $validated['shift_ids'] ?? []);

        return redirect()->route('hr.smjene.dodjela');
    }

    public function storeShift(Request $request)
    {
        $validated = $this->validateShift($request);

        $shift = new Shift();
        $shift->name = $validated['shift_name'];
        $shift->start_time = $validated['start_time'];
        $shift->end_time = $validated['end_time'] ?? null;
        $shift->attendance_credit_code = $validated['attendance_credit_code'] ?? null;
        $shift->department_id = $validated['shift_department_id'] ?? null;
        $shift->save();

        return redirect()->route('hr.smjene.dodjela');
    }

    private function validateDepartment(Request $request, ?Department $department = null): array
    {
        if ($request->has('description') && $request->input('description') === '') {
            $request->merge(['description' => null]);
        }

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')->ignore($department?->id),
            ],
            'description' => ['nullable', 'string'],
            'shift_ids' => ['nullable', 'array'],
            'shift_ids.*' => ['integer', 'exists:shifts,id'],
        ]);
    }

    private function validateShift(Request $request): array
    {
        foreach (['end_time', 'attendance_credit_code', 'shift_department_id'] as $field) {
            if ($request->has($field) && $request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }

        return $request->validate([
            'shift_name' => ['required', 'string', 'max:255'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'attendance_credit_code' => ['nullable', 'string', 'max:50'],
            'shift_department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);
    }

    private function syncShifts(Department $department, array $shiftIds): void
    {
        $shiftIds = collect($shiftIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        DB::transaction(function () use ($department, $shiftIds) {
            // Unassign previously assigned shifts not in the selection
            Shift::query()
                ->where('department_id', $department->id)
                ->whereNotIn('id', $shiftIds)
                ->update(['department_id' => null]);

            // Assign selected shifts to the department
            if (!empty($shiftIds)) {
                Shift::query()
                    ->whereIn('id', $shiftIds)
                    ->update(['department_id' => $department->id]);
            }
        });
    }

    private function formatShiftTime(Shift $shift, string $field): ?string
    {
        $raw = $shift->getRawOriginal($field);
        if ($raw === null || $raw === '') {
            return null;
        }

        return substr((string) $raw, 0, 8);
    }
}
