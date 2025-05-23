<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function showEmployee($id)
    {
        // Simulate fetching employee data from a database
        $employee = Employee::find($id);
        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }
        $employee = [
            'id' => $id,
            'name' => $employee->first_name,
            'position' => $employee->function,
            'department' => $employee->department
        ];
        return response()->json($employee);
    }

}
