<?php

namespace App\Http\Controllers\HR\Concerns;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;

trait ScopesEmployeesByUser
{
    /**
     * Return an Employee query scoped to the given user.
     * Admins see all active employees; other users see only those
     * whose nadlezne_osobe JSON column contains the user's ID.
     */
    protected function scopedEmployeeQuery(?\App\Models\User $user): Builder
    {
        $query = Employee::query()
            ->where(function ($q) {
                $q->whereNull('Active')->orWhere('Active', 1);
            })
            ->orderBy('lastName')
            ->orderBy('firstName');

        if ($user && !$user->isadmin) {
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
    protected function canAccessEmployee(?\App\Models\User $user, int $employeeId): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->isadmin) {
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
