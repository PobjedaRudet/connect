<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Admin\SihtericaAuditController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\HR\AnnualLeaveApiController;
use App\Http\Controllers\HR\AnnualLeaveDecisionPagesController;
use App\Http\Controllers\KapijaController;
use App\Http\Controllers\HR\AnnualLeaveUsagePagesController;
use App\Http\Controllers\HR\AutoPassApprovalPagesController;
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
use App\Services\OvertimeService;
use Illuminate\Http\Request;
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

Route::middleware(['auth', 'adminOnly'])->prefix('admin')->group(function () {
    Route::get('/', function () {
        return Inertia::render('Admin/Dashboard');
    })->name('admin.dashboard');

    Route::get('/sihterica-audit', [SihtericaAuditController::class, 'index'])
        ->name('admin.sihterica-audit');
});

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

Route::middleware('auth', 'adminOrFunkcije:HR,Šef HR,IT,Radnik, Šef PPZ,Šef finansija,Šef službe za SZOI,Šef Komercijale
')->group(function () {

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
    Route::get('/hr/izlaznice-danas', [PassSummaryPagesController::class, 'today'])
        ->name('hr.izlaznice.danas');
    Route::get('/hr/auto-izlaznice', [AutoPassApprovalPagesController::class, 'index'])
        ->name('hr.izlaznice.auto');
    Route::post('/hr/auto-izlaznice/{pass}/odobri', [AutoPassApprovalPagesController::class, 'approve'])
        ->name('hr.izlaznice.auto.approve');
    Route::get('/hr/izlaznice-sumarno', [PassSummaryPagesController::class, 'index'])
        ->name('hr.izlaznice.sumarno');
    Route::patch('/passes/{pass}/type', [PassController::class, 'updateType'])->name('passes.updateType');
    Route::post('/passes/{pass}/confirm', [PassController::class, 'confirm'])->name('passes.confirm');
    Route::resource('passes', PassController::class);

    // ┌───────────────────────────────────────────────────────────────────┐
    // │  HR — samo HR korisnik ili admin                                │
    // └───────────────────────────────────────────────────────────────────┘
    Route::middleware('adminOrFunkcije:HR, Šef HR, IT, Šef PPZ,Šef finansija,Šef službe za SZOI,Šef Komercijale
')->group(function () {

        Route::get('/sector/hr', function () {
            return Inertia::render('Sector/Hr');
        })->name('sector.hr');

        // Godišnji odmori
        Route::get('/hr/godisnji-saldo', function () {
            return Inertia::render('HR/GodisnjiSaldo');
        })->name('hr.godisnji.saldo');

        Route::get('/hr/godisnji-rjesenja', [AnnualLeaveDecisionPagesController::class, 'index'])->name('hr.godisnji.rjesenja');
        Route::get('/hr/godisnji-rjesenja/lista', [AnnualLeaveDecisionPagesController::class, 'lista'])->name('hr.godisnji.rjesenja.lista');
        Route::post('/hr/godisnji-rjesenja', [AnnualLeaveDecisionPagesController::class, 'store'])->name('hr.godisnji.rjesenja.store');
        Route::put('/hr/godisnji-rjesenja/{decision}', [AnnualLeaveDecisionPagesController::class, 'update'])->name('hr.godisnji.rjesenja.update');
        Route::delete('/hr/godisnji-rjesenja/{decision}', [AnnualLeaveDecisionPagesController::class, 'destroy'])->name('hr.godisnji.rjesenja.destroy');
        Route::get('/hr/godisnji-iskoristenje', [AnnualLeaveUsagePagesController::class, 'index'])->name('hr.godisnji.iskoristenje');
        Route::post('/hr/godisnji-iskoristenje', [AnnualLeaveUsagePagesController::class, 'store'])->name('hr.godisnji.iskoristenje.store');

        // Bolovanja
        Route::get('/hr/bolovanja', [SickLeavePagesController::class, 'index'])->name('hr.bolovanja');
        Route::post('/hr/bolovanja', [SickLeavePagesController::class, 'store'])->name('hr.bolovanja.store');

        // API — godišnji odmori
        Route::get('/api/godisnji/balance', [AnnualLeaveApiController::class, 'balance'])->name('api.godisnji.balance');
        Route::get('/api/godisnji/working-days', [AnnualLeaveApiController::class, 'workingDays'])->name('api.godisnji.workingDays');
        Route::get('/api/godisnji/decisions', [AnnualLeaveApiController::class, 'decisions'])->name('api.godisnji.decisions');
        Route::get('/api/godisnji/balance-details', [AnnualLeaveApiController::class, 'balanceDetails'])->name('api.godisnji.balanceDetails');
        Route::get('/api/godisnji/balance-all', [AnnualLeaveApiController::class, 'balanceAll'])->name('api.godisnji.balanceAll');
        Route::get('/api/godisnji/balance-summary', [AnnualLeaveApiController::class, 'balanceSummary'])->name('api.godisnji.balanceSummary');
        Route::get('/api/godisnji/balance-summary-details', [AnnualLeaveApiController::class, 'balanceSummaryDetails'])->name('api.godisnji.balanceSummaryDetails');

        // Šihterica
        Route::get('/hr/sihterica', [SihtericaController::class, 'index'])->name('hr.sihterica');
        Route::post('/hr/sihterica/manual', [SihtericaController::class, 'manualStore'])->name('hr.sihterica.manual');
        Route::put('/hr/sihterica/{record}', [SihtericaController::class, 'update'])->name('hr.sihterica.update');
        Route::delete('/hr/sihterica/{record}', [SihtericaController::class, 'destroy'])->name('hr.sihterica.destroy');

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
            Route::delete('/hr/dodjela-smjene/smjena/{shift}', [DepartmentShiftPagesController::class, 'destroyShift'])->name('hr.smjene.destroy');
            Route::put('/hr/dodjela-smjene/{department}', [DepartmentShiftPagesController::class, 'update'])->name('hr.smjene.dodjela.update');
            Route::delete('/hr/dodjela-smjene/{department}', [DepartmentShiftPagesController::class, 'destroyDepartment'])->name('hr.smjene.dodjela.destroy');
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

// Late-arrival pass approval — nadređeni bira tip izlaznice iz emaila (signed URL, bez login-a)
Route::get('/late-arrival-approval/{pass}', [\App\Http\Controllers\LateArrivalApprovalController::class, 'choose'])
    ->name('late.arrival.approval')
    ->middleware('signed');


