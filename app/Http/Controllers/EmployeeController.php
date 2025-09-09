<?php
namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    // API: Ažuriranje kolone Active zaposlenika
    public function updateActive(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);
        $data = $request->validate([
            'active' => 'required|in:0,1',
        ]);
        $employee->active = $data['active'];
        $employee->save();
        return response()->json(['success' => true, 'active' => $employee->active]);
    }
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

    // API: Ažuriranje invalidnosti zaposlenika
    public function updateInvalidnost(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);
        $data = $request->validate([
            'invalidnost_radnika' => 'nullable|string',
        ]);
        $employee->invalidnost_radnika = $data['invalidnost_radnika'];
        $employee->save();
        return response()->json(['success' => true, 'invalidnost_radnika' => $employee->invalidnost_radnika]);
    }


}
