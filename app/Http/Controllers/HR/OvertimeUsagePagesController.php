<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\OvertimeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OvertimeUsagePagesController extends Controller
{
    public function index(Request $request): Response
    {
        $employees = $this->employeeQuery($request->user()?->id)
            ->get(['id', 'empID', 'firstName', 'lastName'])
            ->map(fn ($employee) => [
                'id' => (int) $employee->id,
                'empID' => (int) $employee->empID,
                'name' => trim((string) $employee->lastName . ' ' . (string) $employee->firstName),
            ])
            ->values();

        return Inertia::render('HR/PrekovremeniIskoristenje', [
            'employees' => $employees,
            'defaultDate' => now(config('app.timezone'))->toDateString(),
            'usageTypes' => collect(OvertimeService::USAGE_TYPES)
                ->map(fn (string $type) => [
                    'value' => $type,
                    'label' => $this->usageTypeLabel($type),
                ])
                ->values(),
        ]);
    }

    public function store(Request $request, OvertimeService $service): RedirectResponse
    {
        if ($request->has('note') && $request->input('note') === '') {
            $request->merge(['note' => null]);
        }

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'usage_date' => ['required', 'date'],
            'minutes_used' => ['required', 'integer', 'min:1'],
            'usage_type' => ['required', 'string', 'in:' . implode(',', OvertimeService::USAGE_TYPES)],
            'note' => ['nullable', 'string'],
        ]);

        $allowed = $this->employeeQuery($request->user()?->id)
            ->whereKey((int) $validated['employee_id'])
            ->exists();

        if (!$allowed) {
            return back()->withErrors([
                'employee_id' => 'Nemate pristup odabranom radniku.',
            ]);
        }

        $service->createUsage(
            (int) $validated['employee_id'],
            (string) $validated['usage_date'],
            (int) $validated['minutes_used'],
            (string) $validated['usage_type'],
            $validated['note'] ?? null,
            $request->user()?->id,
        );

        return redirect()
            ->route('hr.prekovremeni.iskoristenje')
            ->banner('Iskorištenje prekovremenih sati je uspješno sačuvano.');
    }

    private function employeeQuery(?int $userId)
    {
        return Employee::query()
            ->where('Active', true)
            ->when($userId, function ($query) use ($userId) {
                $query->where(function ($q) use ($userId) {
                    $q->whereJsonContains('nadlezne_osobe', (int) $userId)
                        ->orWhereJsonContains('nadlezne_osobe', (string) $userId);
                });
            })
            ->orderBy('lastName')
            ->orderBy('firstName');
    }

    private function usageTypeLabel(string $type): string
    {
        return match ($type) {
            'slobodni_sati' => 'Slobodni sati',
            'raniji_izlazak' => 'Raniji izlazak',
            'kasniji_dolazak' => 'Kasniji dolazak',
            'slobodan_dan' => 'Slobodan dan',
            default => 'Manualno',
        };
    }
}
