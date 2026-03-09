<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\PassController;
use App\Http\Controllers\PreglediController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\ProductionOrderController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\ProfileController;
use App\Mail\UpcomingExamsMail;
use Carbon\Carbon;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\ProductionPlanningController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\HR\SihtericaController;
use App\Http\Controllers\HR\EmployeePagesController;
use App\Http\Controllers\HR\AnnualLeaveDecisionPagesController;
use App\Http\Controllers\HR\DepartmentShiftPagesController;
use App\Http\Controllers\HR\SickLeavePagesController;
use App\Http\Controllers\HR\AnnualLeaveUsagePagesController;
use App\Http\Controllers\UpcomingExamsController;
use App\Services\AnnualLeaveService;
use Illuminate\Http\Request;

// API za CE oznaku
Route::get('/getCeOznaka', [App\Http\Controllers\ProductController::class, 'getCeOznaka'])->middleware(['auth']);

// API za broj naloga
Route::get('/getOrderNumber', [ProductionOrderController::class, 'getOrderNumber'])->middleware(['auth']);

Route::get('/productionorders/createorder', [ProductionOrderController::class, 'create'])->middleware(['auth'])->name('productionorders.createorder');
/* Route::get('/productionorders/createorder', function () {
    return "Hello";
}); */

// API za productslist
Route::get('/productslist', [App\Http\Controllers\ProductController::class, 'numeredlist']);

Route::get('/', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth']);

Route::get('/admin', function () {
    return Inertia::render('Admin/Dashboard');
})->middleware(['auth', 'adminOnly'])->name('admin.dashboard');

// PPZ / ZNR routes grouped for centralized protection
Route::middleware(['auth','adminOrFunkcije:PPZ'])->group(function () {
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

    Route::get('/ppz/izvjestaj-pregledi', function() {
        return Inertia::render('PPZ/IzvjestajPregledi');
    })->name('ppz.izvjestajPregledi');
});

// Planiranje proizvodnje (Direktor Proizvodnje)
Route::middleware(['auth'])->group(function () {
    Route::get('/planiranje/proizvodnja', [ProductionPlanningController::class, 'index'])->name('planning.index');
    Route::post('/planiranje/proizvodnja', [ProductionPlanningController::class, 'store'])->name('planning.store');
    Route::get('/planiranje/gantt', [ProductionPlanningController::class, 'gantt'])->name('planning.gantt');
    Route::post('/planiranje/gantt/insert', [ProductionPlanningController::class, 'insert'])->name('planning.insert');
    Route::post('/planiranje/gantt/bulk-insert', [ProductionPlanningController::class, 'bulkInsert'])->name('planning.bulkInsert');
    Route::post('/planiranje/vezivanje-naloga', [ProductionPlanningController::class, 'linkOrder'])->name('planning.linkOrder');
    // Holidays management (Direktor Proizvodnje)
    Route::get('/planiranje/praznici', [HolidayController::class, 'index'])->name('planning.holidays.index');
    Route::post('/planiranje/praznici', [HolidayController::class, 'store'])->name('planning.holidays.store');
    Route::delete('/planiranje/praznici/{holiday}', [HolidayController::class, 'destroy'])->name('planning.holidays.destroy');
});

Route::get('/prodaja/dashboard', function () {
    return Inertia::render('Nalozi/ProdajaDashboard');
})->middleware(['auth'])->name('prodaja.dashboard');

Route::get('/private', function () {
    return Inertia::render('PrivacyPolicy');
})->middleware(['auth'])->name('private');

Route::get('/send-pregledi-email', [UpcomingExamsController::class, 'send'])
    ->middleware(['auth','adminOrFunkcije:PPZ']);

Route::get('/dashboard', function () {
    return redirect('/');
})->middleware(['auth', 'verified'])->name('dashboard');

// Sector landing pages
Route::get('/sector/hr', function () {
    return Inertia::render('Sector/Hr');
})->middleware(['auth'])->name('sector.hr');

// Moved PPZ pregledi routes into the group above

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::resource('employees', EmployeeController::class);
    Route::resource('attendances', AttendanceController::class);
    Route::get('/passes/active', [PassController::class, 'active'])
        ->middleware('bossOrAdmin')
        ->name('passes.active');
    Route::get('/passes/approved', [PassController::class, 'approved'])
        ->middleware('bossOrAdmin')
        ->name('passes.approved');
    Route::patch('/passes/{pass}/type', [PassController::class, 'updateType'])->name('passes.updateType');
    Route::post('/passes/{pass}/confirm', [PassController::class, 'confirm'])->name('passes.confirm');
    Route::resource('passes', PassController::class);
    Route::resource('leaves', LeaveController::class);

    // HR: annual leave balance screen
    Route::get('/hr/godisnji-saldo', function () {
        return Inertia::render('HR/GodisnjiSaldo');
    })->name('hr.godisnji.saldo');

    // HR: annual leave decisions (rješenja) - entry form
    Route::get('/hr/godisnji-rjesenja', [AnnualLeaveDecisionPagesController::class, 'index'])
        ->name('hr.godisnji.rjesenja');

    Route::post('/hr/godisnji-rjesenja', [AnnualLeaveDecisionPagesController::class, 'store'])
        ->name('hr.godisnji.rjesenja.store');

    // HR: annual leave usage (iskorištenje)
    Route::get('/hr/godisnji-iskoristenje', [AnnualLeaveUsagePagesController::class, 'index'])
        ->name('hr.godisnji.iskoristenje');

    Route::post('/hr/godisnji-iskoristenje', [AnnualLeaveUsagePagesController::class, 'store'])
        ->name('hr.godisnji.iskoristenje.store');

    // HR: sick leaves (bolovanja)
    Route::get('/hr/bolovanja', [SickLeavePagesController::class, 'index'])
        ->name('hr.bolovanja');

    Route::post('/hr/bolovanja', [SickLeavePagesController::class, 'store'])
        ->name('hr.bolovanja.store');

    // Annual leave helpers (JSON)
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

        // If no carryover is stored for this year, expose automatic carryover as available on the first decision.
        // This keeps the usage-entry dropdown consistent with the year balance.
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

        $employees = DB::table('employees as e')
            ->where(function ($q) {
                $q->whereNull('e.Active')->orWhere('e.Active', '=', 1);
            })
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

    // HR
    Route::get('/hr/sihterica', [SihtericaController::class, 'index'])
        ->name('hr.sihterica');

    Route::get('/hr/dodjela-smjene', [DepartmentShiftPagesController::class, 'index'])
        ->name('hr.smjene.dodjela');

    Route::post('/hr/dodjela-smjene', [DepartmentShiftPagesController::class, 'store'])
        ->name('hr.smjene.dodjela.store');

    Route::get('/hr/uposlenici-forma/{employee?}', [EmployeePagesController::class, 'form'])
        ->name('hr.uposlenici.forma');

    Route::post('/hr/uposlenici-forma', [EmployeePagesController::class, 'store'])
        ->name('hr.uposlenici.store');

    Route::put('/hr/uposlenici-forma/{employee}', [EmployeePagesController::class, 'update'])
        ->name('hr.uposlenici.update');

    Route::get('/hr/uposlenici', [EmployeePagesController::class, 'index'])
        ->name('hr.uposlenici.pregled');
    // Approvals
    Route::post('/approvals/send', [ApprovalController::class, 'sendForApproval'])->name('approvals.send');
    Route::get('/approvals/pending', [ApprovalController::class, 'pending'])->name('approvals.pending');
    Route::post('/approvals/{approval}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::post('/approvals/bulk-approve', [ApprovalController::class, 'bulkApprove'])->name('approvals.bulkApprove');
    Route::post('/approvals/{approval}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');
    Route::post('/approvals/order/{order}/approve-one-up', [ApprovalController::class, 'approveOneUp'])->name('approvals.approveOneUp');
    // Approver-only pages (not Radnik)
    Route::get('/odobrenja/moja', function () { return Inertia::render('Nalozi/OdobrenjaZaSefaKomercijale'); })
        ->middleware(['adminOrFunkcije:Šef Komercijale,Direktor Komercijale,Direktor Proizvodnje,Zamjenik1,Zamjenik2,Šef Operative'])
        ->name('approvals.mine');
    Route::get('/nalozi/status', function () { return Inertia::render('Nalozi/StatusNaloga'); })
        ->middleware(['adminOrFunkcije:Šef Komercijale,Direktor Komercijale,Direktor Proizvodnje,Zamjenik1,Zamjenik2,Šef Operative'])
        ->name('orders.status');

    // Direktor Komercijale dedicated approvals page
    Route::get('/odobrenja/direktor-komercijale', function () { return Inertia::render('Nalozi/OdobrenjaDirektoraKomercijale'); })
        ->middleware(['adminOrFunkcije:Direktor Komercijale'])
        ->name('approvals.director.sales');

    // Direktor Proizvodnje dedicated approvals page
    Route::get('/odobrenja/direktor-proizvodnje', function () { return Inertia::render('Nalozi/OdobrenjaDirektoraProizvodnje'); })
        ->middleware(['adminOrFunkcije:Direktor Proizvodnje,Zamjenik1,Zamjenik2'])
        ->name('approvals.director.production');

    // Šef Operative dedicated approvals page
    Route::get('/odobrenja/sef-operative', function () { return Inertia::render('Nalozi/OdobrenjaSefaOperative'); })
        ->middleware(['adminOrFunkcije:Šef Operative'])
        ->name('approvals.chief.operations');
    // Orders list for sending
    Route::get('/productionorders/mine/for-sending', [ProductionOrderController::class, 'myForSending'])->name('productionorders.mine.forSending');
    // Radnik approved orders data (for one-up action)
    Route::get('/productionorders/radnik/approved', [ProductionOrderController::class, 'radnikApproved'])->name('productionorders.radnik.approved');
    // Update and duplicate production orders
    Route::put('/productionorders/{order}', [ProductionOrderController::class, 'update'])->name('productionorders.update');
    Route::post('/productionorders/{order}/duplicate', [ProductionOrderController::class, 'duplicate'])->name('productionorders.duplicate');
    Route::delete('/productionorders/{order}', [ProductionOrderController::class, 'destroy'])->name('productionorders.destroy');
});

Route::resource('products', ProductController::class)->middleware(['auth']);

// UI pages for Products management (avoid conflict with existing products resource API)
Route::middleware(['auth'])->group(function () {
    Route::get('/proizvodi', [ProductsController::class, 'index'])->name('products.ui.index');
    Route::get('/proizvodi/novi', [ProductsController::class, 'create'])->name('products.ui.create');
    Route::post('/proizvodi', [ProductsController::class, 'store'])->name('products.ui.store');
    Route::get('/proizvodi/{product}/edit', [ProductsController::class, 'edit'])->name('products.ui.edit');
    Route::put('/proizvodi/{product}', [ProductsController::class, 'update'])->name('products.ui.update');
});

// Moved ppz.izvjestajPregledi into the group above

// Izvještaji za Direktora Komercijale
Route::middleware(['auth'])->group(function () {
    Route::get('/izvjestaji/kupci', [ReportsController::class, 'byCustomers'])->name('reports.customers');
    Route::get('/izvjestaji/proizvodi', [ReportsController::class, 'byProducts'])->name('reports.products');
    Route::get('/izvjestaji/mjesecni', [ReportsController::class, 'monthly'])->name('reports.monthly');
    Route::get('/izvjestaji/godisnji', [ReportsController::class, 'yearly'])->name('reports.yearly');

    // JSON endpoints for charts
    Route::get('/api/izvjestaji/kupci', [ReportsController::class, 'byCustomersJson'])->name('api.reports.customers');
    Route::get('/api/izvjestaji/proizvodi', [ReportsController::class, 'byProductsJson'])->name('api.reports.products');
    Route::get('/api/izvjestaji/mjesecni', [ReportsController::class, 'monthlyJson'])->name('api.reports.monthly');
    Route::get('/api/izvjestaji/godisnji', [ReportsController::class, 'yearlyJson'])->name('api.reports.yearly');
    // Partneri (kupci)
    Route::get('/kupci', [PartnerController::class, 'index'])->name('partners.index');
    Route::get('/kupci/novi', [PartnerController::class, 'create'])->name('partners.create');
    Route::post('/kupci', [PartnerController::class, 'store'])->name('partners.store');
    Route::get('/kupci/{partner}/edit', [PartnerController::class, 'edit'])->name('partners.edit');
    Route::put('/kupci/{partner}', [PartnerController::class, 'update'])->name('partners.update');
});



// Kreiranje i pregled kreiranih naloga: Radnik i Šef Komercijale (plus admin)
Route::get('/nalozi/nalozi-za-proizvodnju', [ProductionOrderController::class, 'showForm'])
    ->middleware(['auth','adminOrFunkcije:Radnik,Šef Komercijale'])->name('nalozi.za-proizvodnju');
Route::get('/nalozi/kreirani', function() {
    return Inertia::render('Nalozi/KreiraniNalozi');
})->middleware(['auth','verified','adminOrFunkcije:Radnik,Šef Komercijale'])->name('nalozi.kreirani');
Route::get('/nalozi/radnik/odobreni', function() {
    return Inertia::render('Nalozi/OdobreniNaloziRadnik');
})->middleware(['auth','verified','adminOrFunkcije:Radnik'])->name('nalozi.radnik.odobreni');
    Route::get('/productionorders/mine/created', [ProductionOrderController::class, 'myCreated'])->middleware(['auth'])->name('productionorders.mine.created');
    Route::get('/productionorders/created', [ProductionOrderController::class, 'created'])->middleware(['auth'])->name('productionorders.created');

Route::post('/productionorders', [ProductionOrderController::class, 'store'])->middleware(['auth'])->name('productionorders.store');
Route::get('/productionorders/{order}', [ProductionOrderController::class, 'details'])->middleware(['auth'])->name('productionorders.show');
Route::get('/api/productionorders/{order}', [ProductionOrderController::class, 'detailsJson'])->middleware(['auth'])->name('productionorders.show.json');
// Export to Word (minimal) using storage template
Route::get('/productionorders/{order}/export-word', [ProductionOrderController::class, 'exportWordSimple'])
    ->middleware(['auth'])
    ->name('productionorders.export-word');

// Lista proizvoda za BIHNEL proizvode
Route::get('/productslistBihnel', [ProductController::class, 'numeredlistBihnel'])->middleware(['auth']);
// Lista proizvoda za PSED proizvode
Route::get('/productslistPSED', [ProductController::class, 'numeredlistPSED'])->middleware(['auth']);
// Lista proizvoda za MSED proizvode
Route::get('/productslistMSED', [ProductController::class, 'numeredlistMSED'])->middleware(['auth']);
// Lista proizvoda za BK-6 proizvode
Route::get('/productslistBK6', [ProductController::class, 'numeredlistBK6'])->middleware(['auth']);
// Lista proizvoda za BK-8 proizvode
Route::get('/productslistBK8', [ProductController::class, 'numeredlistBK8'])->middleware(['auth']);
require __DIR__ . '/auth.php';

// Signed email approval links (no auth middleware; controller will enforce user login matches uid)
Route::get('/approvals/email/direct', [ApprovalController::class, 'emailDirectApprove'])
    ->middleware('signed')
    ->name('approvals.email.direct');
Route::get('/approvals/email/open', [ApprovalController::class, 'emailOpenPending'])
    ->middleware('signed')
    ->name('approvals.email.open');

