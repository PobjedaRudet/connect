<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Funkcija;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeePagesController extends Controller
{
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
            'start_date',
            'status',
            'term_date',
            'term_reason',
            'home_street',
            'home_zip',
            'home_city',
            'home_county',
            'home_countr',
            'home_state',
            'mart_status',
            'n_children',
            'gov_id',
            'position',
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

            'start_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'term_date' => ['nullable', 'date'],
            'term_reason' => ['nullable', 'string', 'max:255'],

            'home_street' => ['nullable', 'string', 'max:255'],
            'home_zip' => ['nullable', 'string', 'max:20'],
            'home_city' => ['nullable', 'string', 'max:100'],
            'home_county' => ['nullable', 'string', 'max:100'],
            'home_countr' => ['nullable', 'string', 'max:100'],
            'home_state' => ['nullable', 'string', 'max:100'],

            'birthday' => ['nullable', 'date'],
            'brth_countr' => ['nullable', 'string', 'max:100'],
            'mart_status' => ['nullable', 'string', 'max:50'],
            'n_children' => ['nullable', 'integer', 'min:0'],
            'gov_id' => ['nullable', 'string', 'max:50'],

            'position' => ['nullable', 'string', 'max:100'],
            'active' => ['sometimes', 'boolean'],
            'profesionalno_oboljenje' => ['nullable', 'string', 'max:100'],
            'invalidnost_radnika' => ['nullable', 'string', 'max:100'],

            'nadlezne_osobe' => ['nullable', 'array'],
            'nadlezne_osobe.*' => ['integer', 'exists:users,id'],

            'picture' => ['nullable', 'image', 'max:4096'],
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

        $employee->startDate = $data['start_date'] ?? null;
        $employee->status = $data['status'] ?? null;
        $employee->termDate = $data['term_date'] ?? null;
        if (array_key_exists('term_reason', $data)) {
            $employee->termReason = $data['term_reason'] ?? null;
        }

        if (array_key_exists('home_street', $data)) {
            $employee->homeStreet = $data['home_street'] ?? null;
        }
        if (array_key_exists('home_zip', $data)) {
            $employee->homeZip = $data['home_zip'] ?? null;
        }
        if (array_key_exists('home_city', $data)) {
            $employee->homeCity = $data['home_city'] ?? null;
        }
        if (array_key_exists('home_county', $data)) {
            $employee->homeCounty = $data['home_county'] ?? null;
        }
        if (array_key_exists('home_countr', $data)) {
            $employee->homeCountr = $data['home_countr'] ?? null;
        }
        if (array_key_exists('home_state', $data)) {
            $employee->homeState = $data['home_state'] ?? null;
        }

        if (array_key_exists('birthday', $data)) {
            $employee->birthDate = $data['birthday'] ?? null;
        }
        if (array_key_exists('brth_countr', $data)) {
            $employee->brthCountr = $data['brth_countr'] ?? null;
        }
        if (array_key_exists('mart_status', $data)) {
            $employee->martStatus = $data['mart_status'] ?? null;
        }
        if (array_key_exists('n_children', $data)) {
            $employee->nChildren = $data['n_children'] ?? null;
        }
        if (array_key_exists('gov_id', $data)) {
            $employee->govID = $data['gov_id'] ?? null;
        }

        $employee->position = $data['position'] ?? null;
        if (array_key_exists('active', $data)) {
            $employee->Active = (bool) $data['active'];
        }

        $employee->profesionalno_oboljenje = $data['profesionalno_oboljenje'] ?? null;
        $employee->invalidnost_radnika = $data['invalidnost_radnika'] ?? null;
        $employee->nadlezne_osobe = $data['nadlezne_osobe'] ?? null;
    }

    public function form(?Employee $employee = null): Response
    {
        $departments = Department::orderBy('name')->get(['id', 'name']);
        $funkcije = Funkcija::orderBy('Redosljed')->get(['Funkcija', 'Opis'])
            ->map(fn ($f) => [
                'id' => $f->Funkcija,
                'name' => $f->Opis ?: $f->Funkcija,
            ])->values();
        $supervisors = User::orderBy('name')->get(['id', 'name']);

        $employeePayload = $employee ? [
            'id' => $employee->id,
            'empID' => $employee->empID,
            'rfid_code' => $employee->rfid_code,
            'firstName' => $employee->firstName,
            'middle_name' => $employee->middleName,
            'lastName' => $employee->lastName,
            'department_id' => $employee->dept !== null ? (int) $employee->dept : null,
            'funkcija_id' => $employee->jobTitle,
            'position' => $employee->position,
            'radno_mjesto' => $employee->radno_mjesto,
            'email' => $employee->email,
            'sex' => $employee->sex,
            'phone' => null,
            'risk' => $employee->rizik,
            'period' => $employee->period,
            'status' => $employee->status,
            'start_date' => $employee->startDate?->toDateString(),
            'term_date' => $employee->termDate?->toDateString(),
            'term_reason' => $employee->termReason,
            'birthday' => $employee->birthDate?->toDateString(),
            'brth_countr' => $employee->brthCountr,
            'mart_status' => $employee->martStatus,
            'n_children' => $employee->nChildren,
            'gov_id' => $employee->govID,
            'home_street' => $employee->homeStreet,
            'home_zip' => $employee->homeZip,
            'home_city' => $employee->homeCity,
            'home_county' => $employee->homeCounty,
            'home_countr' => $employee->homeCountr,
            'home_state' => $employee->homeState,
            'picture' => $employee->picture,
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
            'cancelUrl' => route('hr.uposlenici.pregled'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateEmployee($request);

        $employee = new Employee();
        $this->fillEmployee($employee, $data);

        if ($request->hasFile('picture')) {
            $employee->picture = $request->file('picture')->store('employees', 'public');
        }

        $employee->save();

        return redirect()->route('hr.uposlenici.pregled');
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $this->validateEmployee($request, $employee);

        $this->fillEmployee($employee, $data);

        if ($request->hasFile('picture')) {
            if ($employee->picture) {
                Storage::disk('public')->delete($employee->picture);
            }
            $employee->picture = $request->file('picture')->store('employees', 'public');
        }

        $employee->save();

        return redirect()->route('hr.uposlenici.pregled');
    }

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $departments = Department::pluck('name', 'id');
        $funkcije = Funkcija::pluck('Opis', 'Funkcija');

        $employeesQuery = Employee::query()
            ->orderBy('lastName')
            ->orderBy('firstName');

        if ($search !== '') {
            $employeesQuery->where(function ($q) use ($search) {
                $like = '%' . $search . '%';
                $q->where('empID', 'like', $like)
                    ->orWhere('firstName', 'like', $like)
                    ->orWhere('middleName', 'like', $like)
                    ->orWhere('lastName', 'like', $like)
                    ->orWhere('dept', 'like', $like)
                    ->orWhere('jobTitle', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        $employees = $employeesQuery
            ->paginate(20)
            ->through(function ($e) use ($departments, $funkcije) {
                return [
                    'id' => (int) $e->id,
                    'empID' => $e->empID,
                    'firstName' => $e->firstName,
                    'middle_name' => $e->middleName ?? null,
                    'lastName' => $e->lastName,
                    'full_name' => trim("{$e->firstName} {$e->lastName}"),
                    'department_name' => $departments[$e->dept] ?? ($e->dept ?? ''),
                    'funkcija_name' => $funkcije[$e->jobTitle] ?? ($e->jobTitle ?? ''),
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
        ]);
    }
}
