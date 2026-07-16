<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SihtericaAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SihtericaAuditController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'action' => 'nullable|in:created,updated,deleted',
            'user_id' => 'nullable|integer|exists:users,id',
            'employee_id' => 'nullable|integer|exists:employees,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'q' => 'nullable|string|max:100',
        ]);

        $query = SihtericaAuditLog::query()
            ->with([
                'user:id,name,email',
                'employee:id,empID,firstName,lastName',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (!empty($validated['action'])) {
            $query->where('action', $validated['action']);
        }

        if (!empty($validated['user_id'])) {
            $query->where('user_id', (int) $validated['user_id']);
        }

        if (!empty($validated['employee_id'])) {
            $query->where('employee_id', (int) $validated['employee_id']);
        }

        if (!empty($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if (!empty($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        if (!empty($validated['q'])) {
            $term = trim($validated['q']);
            $query->where(function ($q) use ($term) {
                $q->whereHas('user', function ($uq) use ($term) {
                    $uq->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                })->orWhereHas('employee', function ($eq) use ($term) {
                    $eq->where('firstName', 'like', "%{$term}%")
                        ->orWhere('lastName', 'like', "%{$term}%")
                        ->orWhere('empID', 'like', "%{$term}%");
                });
            });
        }

        $logs = $query->paginate(30)->withQueryString();

        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn ($u) => [
                'id' => (int) $u->id,
                'label' => trim($u->name . ($u->email ? " ({$u->email})" : '')),
            ])
            ->values();

        $employees = Employee::query()
            ->where(function ($q) {
                $q->whereNull('Active')->orWhere('Active', 1);
            })
            ->orderBy('lastName')
            ->orderBy('firstName')
            ->get(['id', 'empID', 'firstName', 'lastName'])
            ->map(fn ($e) => [
                'id' => (int) $e->id,
                'label' => trim($e->lastName . ' ' . $e->firstName) . ' (#' . $e->empID . ')',
            ])
            ->values();

        return Inertia::render('Admin/SihtericaAudit', [
            'filters' => [
                'action' => $validated['action'] ?? '',
                'user_id' => isset($validated['user_id']) ? (string) $validated['user_id'] : '',
                'employee_id' => isset($validated['employee_id']) ? (string) $validated['employee_id'] : '',
                'date_from' => $validated['date_from'] ?? '',
                'date_to' => $validated['date_to'] ?? '',
                'q' => $validated['q'] ?? '',
            ],
            'users' => $users,
            'employees' => $employees,
            'logs' => $logs->through(fn (SihtericaAuditLog $log) => [
                'id' => (int) $log->id,
                'action' => $log->action,
                'action_label' => match ($log->action) {
                    'created' => 'Dodano',
                    'updated' => 'Izmijenjeno',
                    'deleted' => 'Obrisano',
                    default => $log->action,
                },
                'created_at' => optional($log->created_at)?->timezone(config('app.timezone'))->format('d.m.Y H:i:s'),
                'work_date' => optional($log->work_date)?->format('d.m.Y'),
                'attendance_record_id' => $log->attendance_record_id,
                'user' => $log->user ? [
                    'id' => (int) $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
                'employee' => $log->employee ? [
                    'id' => (int) $log->employee->id,
                    'empID' => $log->employee->empID,
                    'full_name' => trim($log->employee->lastName . ' ' . $log->employee->firstName),
                ] : null,
                'before' => $log->before,
                'after' => $log->after,
                'changes' => $this->diffChanges($log->before, $log->after),
                'ip_address' => $log->ip_address,
            ]),
        ]);
    }

    private function diffChanges(?array $before, ?array $after): array
    {
        $before = $before ?? [];
        $after = $after ?? [];
        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
        $tracked = [
            'entry_time' => 'Prijava',
            'exit_time' => 'Odjava',
            'effective_start' => 'Efektivni start',
            'shift_id' => 'Smjena',
            'status' => 'Status',
            'late_flag' => 'Kašnjenje',
            'duration_minutes' => 'Trajanje (min)',
            'terminal_in' => 'Terminal IN',
            'terminal_out' => 'Terminal OUT',
        ];

        $changes = [];
        foreach ($tracked as $key => $label) {
            if (!in_array($key, $keys, true)) {
                continue;
            }
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;
            if ($old == $new) {
                continue;
            }
            $changes[] = [
                'field' => $key,
                'label' => $label,
                'from' => $old === null || $old === '' ? '—' : (string) $old,
                'to' => $new === null || $new === '' ? '—' : (string) $new,
            ];
        }

        return $changes;
    }
}
