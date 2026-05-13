<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Funkcija;
use App\Models\RadnoMjesto;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeePagesController extends Controller
{
    use Concerns\ScopesEmployeesByUser;

    private function validateEmployee(Request $request, ?Employee $employee = null): array
    {
        $nullableFields = [
            'rfid_code',
            'middle_name',
            'period',
            'risk',
            'radno_mjesto',
            'sex',
            'funkcija_id',
            'department_id',
            'email',
            'status',
            'home_county',
            'profesionalno_oboljenje',
            'invalidnost_radnika',
        ];

        foreach ($nullableFields as $field) {
            if ($request->has($field) && $request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }

        $employeeId = $employee?->id;

        return $request->validate([
            'empID' => ['required', 'integer', Rule::unique('employees', 'empID')->ignore($employeeId)],
            'rfid_code' => ['nullable', 'string', 'max:50', Rule::unique('employees', 'rfid_code')->ignore($employeeId)],

            'firstName' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],

            'period' => ['nullable', 'integer'],
            'risk' => ['nullable', 'boolean'],
            'radno_mjesto' => ['nullable', 'string', 'max:255'],
            'sex' => ['nullable', 'string', 'max:10'],
            'funkcija_id' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'email' => ['nullable', 'email', 'max:100', Rule::unique('employees', 'email')->ignore($employeeId)],

            'status' => ['nullable', 'string', 'max:50'],
            'home_county' => ['nullable', 'string', 'max:100'],
            'active' => ['sometimes', 'boolean'],
            'profesionalno_oboljenje' => ['nullable', 'string', 'max:100'],
            'invalidnost_radnika' => ['nullable', 'string', 'max:100'],

            'nadlezne_osobe' => ['nullable', 'array'],
            'nadlezne_osobe.*' => ['integer', 'exists:users,id'],
        ]);
    }

    private function fillEmployee(Employee $employee, array $data): void
    {
        $employee->empID = $data['empID'];
        $employee->rfid_code = $data['rfid_code'] ?? null;

        $employee->firstName = $data['firstName'];
        $employee->middleName = $data['middle_name'] ?? null;
        $employee->lastName = $data['lastName'];

        $employee->period = $data['period'] ?? null;
        $employee->rizik = array_key_exists('risk', $data) ? (bool) $data['risk'] : null;
        $employee->radno_mjesto = $data['radno_mjesto'] ?? null;
        $employee->sex = $data['sex'] ?? null;
        if (array_key_exists('funkcija_id', $data)) {
            $employee->jobTitle = $data['funkcija_id'] ?? null;
        }
        $employee->dept = array_key_exists('department_id', $data) && $data['department_id'] !== null
            ? (string) $data['department_id']
            : null;
        $employee->email = $data['email'] ?? null;

        $employee->status = $data['status'] ?? null;
        if (array_key_exists('home_county', $data)) {
            $employee->homeCounty = $data['home_county'] ?? null;
        }
        if (array_key_exists('active', $data)) {
            $employee->Active = (bool) $data['active'];
        }

        $employee->profesionalno_oboljenje = $data['profesionalno_oboljenje'] ?? null;
        $employee->invalidnost_radnika = $data['invalidnost_radnika'] ?? null;
        $employee->nadlezne_osobe = $data['nadlezne_osobe'] ?? null;
    }

    public function form(Request $request, ?Employee $employee = null): Response
    {
        if ($employee && !$this->canAccessEmployee($request->user(), $employee->id)) {
            abort(403, 'Nemate pristup ovom uposleniku.');
        }

        $departments = Department::orderBy('name')->get(['id', 'name']);
        $funkcije = Funkcija::orderBy('Redosljed')->get(['Funkcija', 'Opis'])
            ->map(fn ($f) => [
                'id' => $f->Funkcija,
                'name' => $f->Opis ?: $f->Funkcija,
            ])->values();
        $supervisors = User::orderBy('name')->get(['id', 'name']);
        $radnaMjesta = RadnoMjesto::orderBy('radno_mjesto')->pluck('radno_mjesto');

        $employeePayload = $employee ? [
            'id' => $employee->id,
            'empID' => $employee->empID,
            'rfid_code' => $employee->rfid_code,
            'firstName' => $employee->firstName,
            'middle_name' => $employee->middleName,
            'lastName' => $employee->lastName,
            'department_id' => $employee->dept !== null ? (int) $employee->dept : null,
            'funkcija_id' => $employee->jobTitle,
            'radno_mjesto' => $employee->radno_mjesto,
            'email' => $employee->email,
            'sex' => $employee->sex,
            'phone' => null,
            'risk' => $employee->rizik,
            'period' => $employee->period,
            'status' => $employee->status,
            'home_county' => $employee->homeCounty,
            'Active' => $employee->Active,
            'profesionalno_oboljenje' => $employee->profesionalno_oboljenje,
            'invalidnost_radnika' => $employee->invalidnost_radnika,
            'nadlezne_osobe' => $employee->nadlezne_osobe ?? [],
        ] : null;

        return Inertia::render('HR/UposleniciForma', [
            'employee' => $employeePayload,
            'departments' => $departments,
            'funkcije' => $funkcije,
            'supervisors' => $supervisors,
            'radnaMjesta' => $radnaMjesta,
            'cancelUrl' => route('hr.uposlenici.pregled'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateEmployee($request);

        $employee = new Employee();
        $this->fillEmployee($employee, $data);

        $employee->save();

        return redirect()->route('hr.uposlenici.pregled');
    }

    public function update(Request $request, Employee $employee)
    {
        if (!$this->canAccessEmployee($request->user(), $employee->id)) {
            abort(403, 'Nemate pristup ovom uposleniku.');
        }

        $data = $this->validateEmployee($request, $employee);

        $this->fillEmployee($employee, $data);

        $employee->save();

        return redirect()->route('hr.uposlenici.pregled');
    }

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $departments = Department::pluck('name', 'id');

        $employeesQuery = $this->scopedEmployeeQuery($request->user());

        if ($search !== '') {
            $employeesQuery->where(function ($q) use ($search) {
                $like = '%' . $search . '%';
                $q->where('empID', 'like', $like)
                    ->orWhere('firstName', 'like', $like)
                    ->orWhere('middleName', 'like', $like)
                    ->orWhere('lastName', 'like', $like)
                    ->orWhere('dept', 'like', $like)
                    ->orWhere('radno_mjesto', 'like', $like)
                    ->orWhere('jobTitle', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        $employees = $employeesQuery
            ->paginate(20)
            ->through(function ($e) use ($departments) {
                return [
                    'id' => (int) $e->id,
                    'empID' => $e->empID,
                    'firstName' => $e->firstName,
                    'middle_name' => $e->middleName ?? null,
                    'lastName' => $e->lastName,
                    'full_name' => trim("{$e->firstName} {$e->lastName}"),
                    'department_name' => $departments[$e->dept] ?? ($e->dept ?? ''),
                    'radno_mjesto' => $e->radno_mjesto,
                    'email' => $e->email,
                    'phone' => null,
                    'status' => $e->status,
                    'active' => (bool) $e->Active,
                ];
            })
            ->appends(['search' => $search]);

        return Inertia::render('HR/UposleniciPregled', [
            'employees' => $employees,
            'search' => $search,
            'radnaMjesta' => RadnoMjesto::orderBy('radno_mjesto')->pluck('radno_mjesto')->toArray(),
        ]);
    }

    public function updateRadnoMjesto(Request $request, Employee $employee)
    {
        if (!$this->canAccessEmployee($request->user(), $employee->id)) {
            abort(403, 'Nemate pristup ovom uposleniku.');
        }

        $data = $request->validate([
            'radno_mjesto' => ['nullable', 'string', 'max:255'],
        ]);

        $employee->radno_mjesto = $data['radno_mjesto'] ?? null;
        $employee->save();

        return response()->json([
            'success' => true,
            'radno_mjesto' => $employee->radno_mjesto,
        ]);
    }
}
