<?php

namespace App\Http\Controllers\HR\Concerns;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait ScopesEmployeesByUser
{
    /**
     * Admins and users with funkcija=Šef HR can access all employees.
     */
    protected function hasGlobalEmployeeAccess(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->isadmin) {
            return true;
        }

        return trim((string) ($user->funkcija ?? '')) === 'Šef HR';
    }

    /**
     * Only admins get the department dropdown filter.
     */
    protected function canFilterEmployeesByDepartment(?User $user): bool
    {
        return (bool) ($user?->isadmin);
    }

    /**
     * Resolve department filter for admin pages.
     * - Missing query param → default "Služba informatike"
     * - "all" / empty → all departments
     * - numeric id → that department
     */
    protected function resolveAdminDepartmentFilter(Request $request): ?int
    {
        if (!$request->query->has('department_id')) {
            return $this->resolveDefaultAdminDepartmentId();
        }

        $rawDept = trim((string) $request->query('department_id', ''));
        if ($rawDept === '' || strtolower($rawDept) === 'all') {
            return null;
        }

        if (!ctype_digit($rawDept)) {
            return null;
        }

        $candidate = (int) $rawDept;
        if (!Department::whereKey($candidate)->exists()) {
            return null;
        }

        return $candidate;
    }

    protected function resolveDefaultAdminDepartmentId(): ?int
    {
        $exact = Department::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower('Služba informatike')])
            ->value('id');

        if ($exact) {
            return (int) $exact;
        }

        $fuzzy = Department::query()
            ->where('name', 'like', '%informatike%')
            ->orderBy('name')
            ->value('id');

        return $fuzzy ? (int) $fuzzy : null;
    }

    /**
     * Departments list for admin filter dropdown.
     *
     * @return array<int, array{id: int, name: string}>
     */
    protected function departmentFilterOptions(): array
    {
        return Department::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($d) => [
                'id' => (int) $d->id,
                'name' => (string) $d->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Return an Employee query scoped to the given user.
     * - Admins / Šef HR: all active employees
     * - Others (incl. HR): only employees whose nadlezne_osobe contains the user's ID
     */
    protected function scopedEmployeeQuery(?User $user): Builder
    {
        $query = Employee::query()
            ->where(function ($q) {
                $q->whereNull('Active')->orWhere('Active', 1);
            })
            ->orderBy('lastName')
            ->orderBy('firstName');

        if ($this->hasGlobalEmployeeAccess($user)) {
            return $query;
        }

        if ($user) {
            $uid = $user->id;
            $query->where(function ($q) use ($uid) {
                $q->whereJsonContains('nadlezne_osobe', (int) $uid)
                  ->orWhereJsonContains('nadlezne_osobe', (string) $uid);
            });
        }

        return $query;
    }

    /**
     * Check whether the given user may access the specified employee.
     */
    protected function canAccessEmployee(?User $user, int $employeeId): bool
    {
        if (!$user) {
            return false;
        }

        if ($this->hasGlobalEmployeeAccess($user)) {
            return true;
        }

        return Employee::where('id', $employeeId)
            ->where(function ($q) use ($user) {
                $q->whereJsonContains('nadlezne_osobe', (int) $user->id)
                  ->orWhereJsonContains('nadlezne_osobe', (string) $user->id);
            })
            ->exists();
    }
}
