<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\HR\AnnualLeaveDecisionPagesController;
use App\Http\Controllers\KapijaController;
use App\Http\Controllers\HR\AnnualLeaveUsagePagesController;
use App\Http\Controllers\HR\BulkDayStatusPagesController;
use App\Http\Controllers\HR\DepartmentShiftPagesController;
use App\Http\Controllers\HR\EmployeePagesController;
use App\Http\Controllers\HR\OvertimePagesController;
use App\Http\Controllers\HR\OvertimeUsagePagesController;
use App\Http\Controllers\HR\PassSummaryPagesController;
use App\Http\Controllers\HR\SihtericaController;
use App\Http\Controllers\HR\SickLeavePagesController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\PassController;
use App\Http\Controllers\PreglediController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductionOrderController;
use App\Http\Controllers\ProductionPlanningController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\UpcomingExamsController;
use App\Services\AnnualLeaveService;
use App\Services\OvertimeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Sekcije:
|   1. Općenito (dashboard, profil, privatnost)
|   2. Admin
|   3. PPZ / ZNR
|   4. Proizvodnja (planiranje)
|   5. Prodaja (dashboard, izvještaji, proizvodi, kupci, nalozi, odobrenja)
|   6. Resursi, izlaznice, HR
|   7. API endpointi (liste proizvoda, CE oznaka)
|   8. Signed email linkovi
|
*/

// ╔═══════════════════════════════════════════════════════════════════════╗
// ║  1. OPĆENITO — dashboard, profil, privatnost                       ║
// ╚═══════════════════════════════════════════════════════════════════════╝

Route::get('/', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth']);

Route::get('/dashboard', function () {
    return redirect('/');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/private', function () {
    return Inertia::render('PrivacyPolicy');
})->middleware(['auth'])->name('private');

Route::middleware(['auth'])->group(function () {
    Route::get('/kapija', [KapijaController::class, 'index'])
    ->middleware(['adminOrFunkcije:HR, Šef HR, Kapija, Šef PPZ'])
    ->name('kapija');
    Route::get('/kapija/data', [KapijaController::class, 'data'])->name('kapija.data');
});

// ╔═══════════════════════════════════════════════════════════════════════╗
// ║  2. ADMIN                                                          ║
// ╚═══════════════════════════════════════════════════════════════════════╝

Route::get('/admin', function () {
    return Inertia::render('Admin/Dashboard');
})->middleware(['auth', 'adminOnly'])->name('admin.dashboard');

// ╔═══════════════════════════════════════════════════════════════════════╗
// ║  3. PPZ / ZNR                                                      ║
// ╚═══════════════════════════════════════════════════════════════════════╝

Route::middleware(['auth', 'adminOrFunkcije:Šef PPZ,PPZ'])->group(function () {
    Route::get('/ppz/dashboard', function () {
        return Inertia::render('PPZ/Dashboard');
    })->name('ppz.dashboard');

    Route::get('/pregledi/index', [PreglediController::class, 'index'])->name('pregledi.index');
    Route::get('/pregledi/upcoming', [PreglediController::class, 'reportUpcoming'])->name('pregledi.upcoming');
    Route::get('/pregledi/nextMonth', [PreglediController::class, 'reportUpcomingNextMonth'])->name('pregledi.nextMonth');
    Route::get('/pregledi/kontrolni', function () {
        return Inertia::render('Pregledi/KontrolniPregledi');
    })->name('pregledi.kontrolni');
    Route::get('/pregledi/za-sedam-dana', [PreglediController::class, 'zaSedamDana']);

    Route::get('/ppz/izvjestaj-pregledi', function () {
        return Inertia::render('PPZ/IzvjestajPregledi');
    })->name('ppz.izvjestajPregledi');

    Route::get('/send-pregledi-email', [UpcomingExamsController::class, 'send']);
});

// ╔═══════════════════════════════════════════════════════════════════════╗
// ║  4. PROIZVODNJA — planiranje                                       ║
// ╚═══════════════════════════════════════════════════════════════════════╝

Route::middleware(['auth', 'adminOrFunkcije:Proizvodnja, Direktor Proizvodnje'])->group(function () {
    Route::get('/planiranje/proizvodnja', [ProductionPlanningController::class, 'index'])->name('planning.index');
    Route::post('/planiranje/proizvodnja', [ProductionPlanningController::class, 'store'])->name('planning.store');
    Route::get('/planiranje/gantt', [ProductionPlanningController::class, 'gantt'])->name('planning.gantt');
    Route::post('/planiranje/gantt/insert', [ProductionPlanningController::class, 'insert'])->name('planning.insert');
    Route::post('/planiranje/gantt/bulk-insert', [ProductionPlanningController::class, 'bulkInsert'])->name('planning.bulkInsert');
    Route::post('/planiranje/vezivanje-naloga', [ProductionPlanningController::class, 'linkOrder'])->name('planning.linkOrder');
    Route::get('/planiranje/praznici', [HolidayController::class, 'index'])->name('planning.holidays.index');
    Route::post('/planiranje/praznici', [HolidayController::class, 'store'])->name('planning.holidays.store');
    Route::delete('/planiranje/praznici/{holiday}', [HolidayController::class, 'destroy'])->name('planning.holidays.destroy');
});

// ╔═══════════════════════════════════════════════════════════════════════╗
// ║  5. PRODAJA — dashboard, izvještaji, proizvodi, kupci              ║
// ╚═══════════════════════════════════════════════════════════════════════╝

Route::middleware(['auth', 'adminOrFunkcije:Prodaja,Direktor Proizvodnje'])->group(function () {
    // Dashboard
    Route::get('/prodaja/dashboard', function () {
        return Inertia::render('Nalozi/ProdajaDashboard');
    })->name('prodaja.dashboard');

    // Proizvodi
    Route::get('/proizvodi', [ProductsController::class, 'index'])->name('products.ui.index');
    Route::get('/proizvodi/novi', [ProductsController::class, 'create'])->name('products.ui.create');
    Route::post('/proizvodi', [ProductsController::class, 'store'])->name('products.ui.store');
    Route::get('/proizvodi/{product}/edit', [ProductsController::class, 'edit'])->name('products.ui.edit');
    Route::put('/proizvodi/{product}', [ProductsController::class, 'update'])->name('products.ui.update');

    // Izvještaji
    Route::get('/izvjestaji/kupci', [ReportsController::class, 'byCustomers'])->name('reports.customers');
    Route::get('/izvjestaji/proizvodi', [ReportsController::class, 'byProducts'])->name('reports.products');
    Route::get('/izvjestaji/mjesecni', [ReportsController::class, 'monthly'])->name('reports.monthly');
    Route::get('/izvjestaji/godisnji', [ReportsController::class, 'yearly'])->name('reports.yearly');

    // Izvještaji — JSON za grafove
    Route::get('/api/izvjestaji/kupci', [ReportsController::class, 'byCustomersJson'])->name('api.reports.customers');
    Route::get('/api/izvjestaji/proizvodi', [ReportsController::class, 'byProductsJson'])->name('api.reports.products');
    Route::get('/api/izvjestaji/mjesecni', [ReportsController::class, 'monthlyJson'])->name('api.reports.monthly');
    Route::get('/api/izvjestaji/godisnji', [ReportsController::class, 'yearlyJson'])->name('api.reports.yearly');

    // Kupci (partneri)
    Route::get('/kupci', [PartnerController::class, 'index'])->name('partners.index');
    Route::get('/kupci/novi', [PartnerController::class, 'create'])->name('partners.create');
    Route::post('/kupci', [PartnerController::class, 'store'])->name('partners.store');
    Route::get('/kupci/{partner}/edit', [PartnerController::class, 'edit'])->name('partners.edit');
    Route::put('/kupci/{partner}', [PartnerController::class, 'update'])->name('partners.update');
});

// ── Nalozi — kreiranje (Radnik, Šef Komercijale) ──
Route::get('/nalozi/nalozi-za-proizvodnju', [ProductionOrderController::class, 'showForm'])
    ->middleware(['auth', 'adminOrFunkcije:Radnik,Šef Komercijale'])
    ->name('nalozi.za-proizvodnju');

Route::get('/nalozi/kreirani', function () {
    return Inertia::render('Nalozi/KreiraniNalozi');
})->middleware(['auth', 'verified', 'adminOrFunkcije:Radnik,Šef Komercijale'])->name('nalozi.kreirani');

Route::get('/nalozi/radnik/odobreni', function () {
    return Inertia::render('Nalozi/OdobreniNaloziRadnik');
})->middleware(['auth', 'verified', 'adminOrFunkcije:Radnik'])->name('nalozi.radnik.odobreni');

// ── Nalozi — CRUD, pregled, export ──
Route::middleware('auth')->group(function () {
    Route::get('/productionorders/createorder', [ProductionOrderController::class, 'create'])->name('productionorders.createorder');
    Route::get('/productionorders/mine/created', [ProductionOrderController::class, 'myCreated'])->name('productionorders.mine.created');
    Route::get('/productionorders/mine/for-sending', [ProductionOrderController::class, 'myForSending'])->name('productionorders.mine.forSending');
    Route::get('/productionorders/radnik/approved', [ProductionOrderController::class, 'radnikApproved'])->name('productionorders.radnik.approved');
    Route::get('/productionorders/created', [ProductionOrderController::class, 'created'])->name('productionorders.created');
    Route::post('/productionorders', [ProductionOrderController::class, 'store'])->name('productionorders.store');
    Route::get('/productionorders/{order}', [ProductionOrderController::class, 'details'])->name('productionorders.show');
    Route::get('/api/productionorders/{order}', [ProductionOrderController::class, 'detailsJson'])->name('productionorders.show.json');
    Route::get('/productionorders/{order}/export-word', [ProductionOrderController::class, 'exportWordSimple'])->name('productionorders.export-word');
    Route::put('/productionorders/{order}', [ProductionOrderController::class, 'update'])->name('productionorders.update');
    Route::post('/productionorders/{order}/duplicate', [ProductionOrderController::class, 'duplicate'])->name('productionorders.duplicate');
    Route::delete('/productionorders/{order}', [ProductionOrderController::class, 'destroy'])->name('productionorders.destroy');
});

// ── Odobrenja ──
Route::middleware('auth')->group(function () {
    Route::post('/approvals/send', [ApprovalController::class, 'sendForApproval'])->name('approvals.send');
    Route::get('/approvals/pending', [ApprovalController::class, 'pending'])->name('approvals.pending');
    Route::post('/approvals/{approval}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::post('/approvals/bulk-approve', [ApprovalController::class, 'bulkApprove'])->name('approvals.bulkApprove');
    Route::post('/approvals/{approval}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');
    Route::post('/approvals/order/{order}/approve-one-up', [ApprovalController::class, 'approveOneUp'])->name('approvals.approveOneUp');

    Route::get('/odobrenja/moja', function () { return Inertia::render('Nalozi/OdobrenjaZaSefaKomercijale'); })
        ->middleware(['adminOrFunkcije:Šef Komercijale,Direktor Komercijale,Direktor Proizvodnje,Zamjenik1,Zamjenik2,Šef Operative'])
        ->name('approvals.mine');
    Route::get('/nalozi/status', function () { return Inertia::render('Nalozi/StatusNaloga'); })
        ->middleware(['adminOrFunkcije:Šef Komercijale,Direktor Komercijale,Direktor Proizvodnje,Zamjenik1,Zamjenik2,Šef Operative'])
        ->name('orders.status');
    Route::get('/odobrenja/direktor-komercijale', function () { return Inertia::render('Nalozi/OdobrenjaDirektoraKomercijale'); })
        ->middleware(['adminOrFunkcije:Direktor Komercijale'])
        ->name('approvals.director.sales');
    Route::get('/odobrenja/direktor-proizvodnje', function () { return Inertia::render('Nalozi/OdobrenjaDirektoraProizvodnje'); })
        ->middleware(['adminOrFunkcije:Direktor Proizvodnje,Zamjenik1,Zamjenik2'])
        ->name('approvals.director.production');
    Route::get('/odobrenja/sef-operative', function () { return Inertia::render('Nalozi/OdobrenjaSefaOperative'); })
        ->middleware(['adminOrFunkcije:Šef Operative'])
        ->name('approvals.chief.operations');
});

// ╔═══════════════════════════════════════════════════════════════════════╗
// ║  6. RESURSI, IZLAZNICE, HR                                        ║
// ╚═══════════════════════════════════════════════════════════════════════╝

Route::middleware('auth', 'adminOrFunkcije:HR,Šef HR,IT,Radnik, Šef PPZ')->group(function () {

    // ── Profil ──
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ── Resursi (uposlenici, prisustvo, odsustvo) ──
    Route::resource('employees', EmployeeController::class);
    Route::resource('attendances', AttendanceController::class);
    Route::resource('leaves', LeaveController::class);

    // ── Izlaznice ──
    Route::get('/passes/active', [PassController::class, 'active'])
        ->name('passes.active');
    Route::get('/passes/approved', [PassController::class, 'approved'])
        ->name('passes.approved');
    Route::get('/hr/izlaznice-sumarno', [PassSummaryPagesController::class, 'index'])
        ->name('hr.izlaznice.sumarno');
    Route::patch('/passes/{pass}/type', [PassController::class, 'updateType'])->name('passes.updateType');
    Route::post('/passes/{pass}/confirm', [PassController::class, 'confirm'])->name('passes.confirm');
    Route::resource('passes', PassController::class);

    // ┌───────────────────────────────────────────────────────────────────┐
    // │  HR — samo HR korisnik ili admin                                │
    // └───────────────────────────────────────────────────────────────────┘
    Route::middleware('adminOrFunkcije:HR, Šef HR, IT, Šef PPZ')->group(function () {

        Route::get('/sector/hr', function () {
            return Inertia::render('Sector/Hr');
        })->name('sector.hr');

        // Godišnji odmori
        Route::get('/hr/godisnji-saldo', function () {
            return Inertia::render('HR/GodisnjiSaldo');
        })->name('hr.godisnji.saldo');

        Route::get('/hr/godisnji-rjesenja', [AnnualLeaveDecisionPagesController::class, 'index'])->name('hr.godisnji.rjesenja');
        Route::post('/hr/godisnji-rjesenja', [AnnualLeaveDecisionPagesController::class, 'store'])->name('hr.godisnji.rjesenja.store');
        Route::get('/hr/godisnji-iskoristenje', [AnnualLeaveUsagePagesController::class, 'index'])->name('hr.godisnji.iskoristenje');
        Route::post('/hr/godisnji-iskoristenje', [AnnualLeaveUsagePagesController::class, 'store'])->name('hr.godisnji.iskoristenje.store');

        // Bolovanja
        Route::get('/hr/bolovanja', [SickLeavePagesController::class, 'index'])->name('hr.bolovanja');
        Route::post('/hr/bolovanja', [SickLeavePagesController::class, 'store'])->name('hr.bolovanja.store');

        // API — godišnji odmori
        Route::get('/api/godisnji/balance', function (Request $request, AnnualLeaveService $service) {
            $validated = $request->validate([
                'employee_id' => ['required', 'integer'],
                'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            ]);
            $employeeId = (int) $validated['employee_id'];
            $year = (int) ($validated['year'] ?? now()->year);
            return response()->json($service->getEmployeeYearBalance($employeeId, $year));
        })->name('api.godisnji.balance');

        Route::get('/api/godisnji/working-days', function (Request $request, AnnualLeaveService $service) {
            $validated = $request->validate([
                'from' => ['required', 'date'],
                'to' => ['required', 'date'],
            ]);
            $days = $service->calculateWorkingDays($validated['from'], $validated['to']);
            return response()->json([
                'from' => $validated['from'],
                'to' => $validated['to'],
                'working_days' => $days,
            ]);
        })->name('api.godisnji.workingDays');

        Route::get('/api/godisnji/decisions', function (Request $request, AnnualLeaveService $service) {
            $validated = $request->validate([
                'employee_id' => ['required', 'integer'],
                'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            ]);
            $employeeId = (int) $validated['employee_id'];
            $year = (int) ($validated['year'] ?? now()->year);

            $manualCarrySum = (float) DB::table('annual_leave_decisions')
                ->where('employee_id', $employeeId)
                ->where('year', $year)
                ->sum('carried_over_days');

            $prev = $service->getEmployeeYearBalance($employeeId, $year - 1);
            $autoCarry = (int) max(0, (int) ($prev['remaining_days'] ?? 0));

            $usageAgg = DB::table('annual_leave_usages')
                ->select('annual_leave_decision_id', DB::raw('CAST(ROUND(SUM(COALESCE(days,0)),0) AS SIGNED) as used_days'))
                ->groupBy('annual_leave_decision_id');

            $decisions = DB::table('annual_leave_decisions as d')
                ->leftJoinSub($usageAgg, 'u', function ($join) {
                    $join->on('u.annual_leave_decision_id', '=', 'd.id');
                })
                ->where('d.employee_id', $employeeId)
                ->where('d.year', $year)
                ->orderBy('d.part')
                ->orderBy('d.id')
                ->get([
                    'd.id',
                    'd.part',
                    'd.decision_number',
                    'd.valid_from',
                    'd.valid_to',
                    DB::raw('CAST(ROUND(COALESCE(d.granted_days,0) + COALESCE(d.carried_over_days,0),0) AS SIGNED) as total_days'),
                    DB::raw('CAST(COALESCE(u.used_days,0) AS SIGNED) as used_days'),
                    DB::raw('CAST((ROUND(COALESCE(d.granted_days,0) + COALESCE(d.carried_over_days,0),0) - COALESCE(u.used_days,0)) AS SIGNED) as remaining_days'),
                ])
                ->map(function ($d) {
                    $part = $d->part ?? 'ostalo';
                    $num = $d->decision_number ? (' #' . $d->decision_number) : '';
                    $range = ($d->valid_from || $d->valid_to)
                        ? (' (' . ($d->valid_from ?? '?') . ' - ' . ($d->valid_to ?? '?') . ')')
                        : '';
                    $label = strtoupper((string) $part) . $num . $range . ' | Preostalo: ' . ((int) $d->remaining_days);

                    return [
                        'id' => (int) $d->id,
                        'part' => (string) $part,
                        'decision_number' => $d->decision_number,
                        'valid_from' => $d->valid_from,
                        'valid_to' => $d->valid_to,
                        'total_days' => (int) $d->total_days,
                        'used_days' => (int) $d->used_days,
                        'remaining_days' => (int) $d->remaining_days,
                        'label' => $label,
                    ];
                })
                ->values();

            if ($decisions->isNotEmpty() && $manualCarrySum <= 0 && $autoCarry > 0) {
                $first = $decisions->first();
                $first['total_days'] = (int) $first['total_days'] + $autoCarry;
                $first['remaining_days'] = (int) $first['remaining_days'] + $autoCarry;

                $num = $first['decision_number'] ? (' #' . $first['decision_number']) : '';
                $range = ($first['valid_from'] || $first['valid_to'])
                    ? (' (' . ($first['valid_from'] ?? '?') . ' - ' . ($first['valid_to'] ?? '?') . ')')
                    : '';
                $first['label'] = strtoupper((string) ($first['part'] ?? 'ostalo')) . $num . $range . ' | Preostalo: ' . ((int) $first['remaining_days']);

                $decisions = $decisions->values();
                $decisions->put(0, $first);
            }

            return response()->json([
                'employee_id' => $employeeId,
                'year' => $year,
                'decisions' => $decisions,
            ]);
        })->name('api.godisnji.decisions');

        Route::get('/api/godisnji/balance-details', function (Request $request, AnnualLeaveService $service) {
            $validated = $request->validate([
                'employee_id' => ['required', 'integer'],
                'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            ]);
            $employeeId = (int) $validated['employee_id'];
            $year = (int) ($validated['year'] ?? now()->year);

            $user = $request->user();
            $isAdmin = (bool) (($user?->isadmin ?? false) || ($user?->is_admin ?? false));
            if (!$isAdmin) {
                $canAccess = \App\Models\Employee::where('id', $employeeId)
                    ->where(function ($q) use ($user) {
                        $q->whereJsonContains('nadlezne_osobe', (int) $user->id)
                            ->orWhereJsonContains('nadlezne_osobe', (string) $user->id);
                    })
                    ->exists();

                if (!$canAccess) {
                    return response()->json(['message' => 'Nemate pravo pristupa ovom radniku.'], 403);
                }
            }

            $manualCarrySum = (float) DB::table('annual_leave_decisions')
                ->where('employee_id', $employeeId)
                ->where('year', $year)
                ->sum('carried_over_days');

            $prev = $service->getEmployeeYearBalance($employeeId, $year - 1);
            $autoCarry = (int) max(0, (int) ($prev['remaining_days'] ?? 0));
            $carryOverDays = $manualCarrySum > 0 ? (int) round($manualCarrySum, 0) : $autoCarry;
            $carryOverMode = $manualCarrySum > 0 ? 'stored' : 'auto';

            $decisions = DB::table('annual_leave_decisions as d')
                ->where('d.employee_id', $employeeId)
                ->where('d.year', $year)
                ->orderBy('d.part')
                ->orderBy('d.id')
                ->get([
                    'd.id',
                    'd.part',
                    'd.decision_number',
                    'd.decision_date',
                    'd.valid_from',
                    'd.valid_to',
                    DB::raw('CAST(ROUND(COALESCE(d.granted_days,0) + COALESCE(d.carried_over_days,0),0) AS SIGNED) as total_days'),
                ])
                ->map(function ($d) {
                    return [
                        'id' => (int) $d->id,
                        'part' => (string) ($d->part ?? 'ostalo'),
                        'decision_number' => $d->decision_number,
                        'decision_date' => $d->decision_date,
                        'valid_from' => $d->valid_from,
                        'valid_to' => $d->valid_to,
                        'total_days' => (int) $d->total_days,
                    ];
                })
                ->values();

            $usages = DB::table('annual_leave_usages as u')
                ->join('annual_leave_decisions as d', 'd.id', '=', 'u.annual_leave_decision_id')
                ->where('d.employee_id', $employeeId)
                ->where('d.year', $year)
                ->orderBy('u.date_from')
                ->orderBy('u.id')
                ->get([
                    'u.id',
                    'u.annual_leave_decision_id',
                    'u.date_from',
                    'u.date_to',
                    DB::raw('CAST(ROUND(COALESCE(u.days,0),0) AS SIGNED) as days'),
                    'u.note',
                    'd.part',
                    'd.decision_number',
                ])
                ->map(function ($u) {
                    return [
                        'id' => (int) $u->id,
                        'annual_leave_decision_id' => (int) $u->annual_leave_decision_id,
                        'date_from' => $u->date_from,
                        'date_to' => $u->date_to,
                        'days' => (int) $u->days,
                        'note' => $u->note,
                        'part' => (string) ($u->part ?? 'ostalo'),
                        'decision_number' => $u->decision_number,
                    ];
                })
                ->values();

            return response()->json([
                'employee_id' => $employeeId,
                'year' => $year,
                'carryover_days' => $carryOverDays,
                'carryover_mode' => $carryOverMode,
                'carryover_from_year' => $carryOverMode === 'auto' ? ($year - 1) : null,
                'decisions' => $decisions,
                'usages' => $usages,
            ]);
        })->name('api.godisnji.balanceDetails');

        Route::get('/api/godisnji/balance-all', function (Request $request) {
            $validated = $request->validate([
                'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            ]);
            $year = (int) ($validated['year'] ?? now()->year);
            $minYear = (int) (DB::table('annual_leave_decisions')->min('year') ?? $year);
            $minYear = max(2000, min($minYear, $year));

            $decisionsAgg = DB::table('annual_leave_decisions')
                ->whereBetween('year', [$minYear, $year])
                ->select([
                    'employee_id',
                    'year',
                    DB::raw('CAST(ROUND(SUM(COALESCE(granted_days,0)), 0) AS SIGNED) as granted_days'),
                    DB::raw('CAST(ROUND(SUM(COALESCE(carried_over_days,0)), 0) AS SIGNED) as carried_over_days'),
                ])
                ->groupBy('employee_id', 'year')
                ->get();

            $usageAgg = DB::table('annual_leave_usages as u')
                ->join('annual_leave_decisions as d', 'd.id', '=', 'u.annual_leave_decision_id')
                ->whereBetween('d.year', [$minYear, $year])
                ->select([
                    'd.employee_id',
                    'd.year',
                    DB::raw('CAST(ROUND(SUM(COALESCE(u.days,0)), 0) AS SIGNED) as used_days'),
                ])
                ->groupBy('d.employee_id', 'd.year')
                ->get();

            $granted = [];
            $manualCarry = [];
            foreach ($decisionsAgg as $r) {
                $eid = (int) $r->employee_id;
                $y = (int) $r->year;
                $granted[$eid][$y] = (int) $r->granted_days;
                $manualCarry[$eid][$y] = (int) $r->carried_over_days;
            }

            $used = [];
            foreach ($usageAgg as $r) {
                $eid = (int) $r->employee_id;
                $y = (int) $r->year;
                $used[$eid][$y] = (int) $r->used_days;
            }

            $user = $request->user();
            $isAdmin = (bool) (($user?->isadmin ?? false) || ($user?->is_admin ?? false));

            $employeesQuery = DB::table('employees as e')
                ->where(function ($q) {
                    $q->whereNull('e.Active')->orWhere('e.Active', '=', 1);
                });

            if (!$isAdmin && $user) {
                $uid = (int) $user->id;
                $employeesQuery->where(function ($q) use ($uid) {
                    $q->whereJsonContains('e.nadlezne_osobe', $uid)
                        ->orWhereJsonContains('e.nadlezne_osobe', (string) $uid);
                });
            }

            $employees = $employeesQuery
                ->select([
                    'e.id as employee_id',
                    'e.firstName',
                    'e.lastName',
                ])
                ->orderBy('e.lastName')
                ->orderBy('e.firstName')
                ->get();

            $rows = $employees->map(function ($e) use ($year, $minYear, $granted, $manualCarry, $used) {
                $eid = (int) $e->employee_id;
                $prevRemaining = 0;
                $totalDays = 0;
                $usedDays = 0;
                $remainingDays = 0;

                for ($y = $minYear; $y <= $year; $y++) {
                    $g = (int) ($granted[$eid][$y] ?? 0);
                    $mCarry = (int) ($manualCarry[$eid][$y] ?? 0);
                    $u = (int) ($used[$eid][$y] ?? 0);

                    $autoCarry = max(0, (int) $prevRemaining);
                    $carry = $mCarry > 0 ? $mCarry : $autoCarry;
                    $approved = $g + $carry;
                    $remaining = $approved - $u;

                    if ($y === $year) {
                        $totalDays = (int) $approved;
                        $usedDays = (int) $u;
                        $remainingDays = (int) $remaining;
                    }

                    $prevRemaining = $remaining;
                }

                return [
                    'employee_id' => $eid,
                    'firstName' => $e->firstName,
                    'lastName' => $e->lastName,
                    'year' => $year,
                    'used_days' => $usedDays,
                    'total_days' => $totalDays,
                    'remaining_days' => $remainingDays,
                ];
            })->values();

            return response()->json([
                'year' => $year,
                'rows' => $rows,
            ]);
        })->name('api.godisnji.balanceAll');

        // Šihterica
        Route::get('/hr/sihterica', [SihtericaController::class, 'index'])->name('hr.sihterica');
        Route::post('/hr/sihterica/manual', [SihtericaController::class, 'manualStore'])->name('hr.sihterica.manual');

        // Masovna dodjela statusa — samo admin ili Šef HR
        Route::middleware('adminOrFunkcije:Šef HR')->group(function () {
            Route::get('/hr/masovna-dodjela-statusa', [BulkDayStatusPagesController::class, 'index'])->name('hr.statusi.masovno');
            Route::post('/hr/masovna-dodjela-statusa', [BulkDayStatusPagesController::class, 'store'])->name('hr.statusi.masovno.store');
        });

        // Prekovremeni sati
        Route::get('/hr/prekovremeni-sati', [OvertimePagesController::class, 'index'])->name('hr.prekovremeni-sati');
        Route::get('/hr/prekovremeni-iskoristenje', [OvertimeUsagePagesController::class, 'index'])->name('hr.prekovremeni.iskoristenje');
        Route::post('/hr/prekovremeni-iskoristenje', [OvertimeUsagePagesController::class, 'store'])->name('hr.prekovremeni.iskoristenje.store');

        Route::get('/api/prekovremeni/balance', function (Request $request, OvertimeService $service) {
            $validated = $request->validate([
                'employee_id' => ['required', 'integer', 'exists:employees,id'],
                'usage_date' => ['required', 'date'],
                'minutes_requested' => ['nullable', 'integer', 'min:1'],
            ]);
            return response()->json($service->getAvailableBalance(
                (int) $validated['employee_id'],
                (string) $validated['usage_date'],
                isset($validated['minutes_requested']) ? (int) $validated['minutes_requested'] : null,
            ));
        })->name('api.prekovremeni.balance');

        // Dodjela smjena — samo admin ili Šef HR
        Route::middleware('adminOrFunkcije:Šef HR')->group(function () {
            Route::get('/hr/dodjela-smjene', [DepartmentShiftPagesController::class, 'index'])->name('hr.smjene.dodjela');
            Route::post('/hr/dodjela-smjene', [DepartmentShiftPagesController::class, 'store'])->name('hr.smjene.dodjela.store');
            Route::post('/hr/dodjela-smjene/smjena', [DepartmentShiftPagesController::class, 'storeShift'])->name('hr.smjene.store');
            Route::put('/hr/dodjela-smjene/{department}', [DepartmentShiftPagesController::class, 'update'])->name('hr.smjene.dodjela.update');
        });

        // Uposlenici
        Route::get('/hr/uposlenici', [EmployeePagesController::class, 'index'])->name('hr.uposlenici.pregled');
        Route::put('/hr/uposlenici/{employee}/radno-mjesto', [EmployeePagesController::class, 'updateRadnoMjesto'])->name('hr.uposlenici.update-radno-mjesto');
        Route::get('/hr/uposlenici-forma/{employee?}', [EmployeePagesController::class, 'form'])
        ->middleware(['adminOrFunkcije:HR,Šef HR'])
        ->name('hr.uposlenici.forma');
        Route::post('/hr/uposlenici-forma', [EmployeePagesController::class, 'store'])->name('hr.uposlenici.store');
        Route::put('/hr/uposlenici-forma/{employee}', [EmployeePagesController::class, 'update'])->name('hr.uposlenici.update');

    }); // Kraj HR ruta
});

// ╔═══════════════════════════════════════════════════════════════════════╗
// ║  7. API ENDPOINTI — liste proizvoda, CE oznaka                     ║
// ╚═══════════════════════════════════════════════════════════════════════╝

Route::get('/productslist', [ProductController::class, 'numeredlist']);

Route::middleware('auth')->group(function () {
    Route::get('/getCeOznaka', [ProductController::class, 'getCeOznaka']);
    Route::get('/getOrderNumber', [ProductionOrderController::class, 'getOrderNumber']);
    Route::resource('products', ProductController::class);
    Route::get('/productslistBihnel', [ProductController::class, 'numeredlistBihnel']);
    Route::get('/productslistPSED', [ProductController::class, 'numeredlistPSED']);
    Route::get('/productslistMSED', [ProductController::class, 'numeredlistMSED']);
    Route::get('/productslistBK6', [ProductController::class, 'numeredlistBK6']);
    Route::get('/productslistBK8', [ProductController::class, 'numeredlistBK8']);
});

// ╔═══════════════════════════════════════════════════════════════════════╗
// ║  8. AUTH & SIGNED EMAIL LINKOVI                                    ║
// ╚═══════════════════════════════════════════════════════════════════════╝

require __DIR__ . '/auth.php';

Route::get('/approvals/email/direct', [ApprovalController::class, 'emailDirectApprove'])
    ->middleware('signed')
    ->name('approvals.email.direct');

Route::get('/approvals/email/open', [ApprovalController::class, 'emailOpenPending'])
    ->middleware('signed')
    ->name('approvals.email.open');
