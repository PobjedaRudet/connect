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
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\ProductionPlanningController;
use App\Http\Controllers\Admin\PageAccessController;
use App\Http\Controllers\HolidayController;

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
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->middleware(['auth']);

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
Route::middleware(['auth','can.page'])->group(function () {
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
})->middleware(['auth','can.page'])->name('prodaja.dashboard');

Route::get('/private', function () {
    return Inertia::render('PrivacyPolicy');
})->middleware(['auth'])->name('private');

Route::get('/send-pregledi-email', function () {
    $today = Carbon::today();
    $nextWeek = $today->copy()->addDays(7);

    $employees = Employee::whereHas('pregledi')
        ->with(['pregledi' => fn ($q) => $q->orderByDesc('datum_pregleda')->limit(10)])
        ->get();

    $upcoming = [];
    $expired = [];

    foreach ($employees as $employee) {
        $lastExam = $employee->pregledi->first();
        if (!$lastExam || !$employee->period) continue;

        $nextDue = Carbon::parse($lastExam->datum_pregleda)->addMonths((int)$employee->period);

        if ($nextDue->between($today, $nextWeek)) {
            $upcoming[] = ['employee' => $employee, 'next_due' => $nextDue];
        } elseif ($nextDue->lessThan($today)) {
            $expired[] = ['employee' => $employee, 'next_due' => $nextDue];
        }
    }

    $recipients = [
        'z.neira@pobjeda.com',
        'a.salkovic@pobjeda.com',
        'k.asim@pobjeda.com',
    ];

    Mail::to($recipients)->send(new UpcomingExamsMail($upcoming, $expired));

    return 'Mail poslan.';
})->middleware(['auth','adminOrFunkcije:PPZ']);

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Moved PPZ pregledi routes into the group above

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::resource('employees', EmployeeController::class);
    Route::resource('attendances', AttendanceController::class);
    Route::resource('passes', PassController::class);
    Route::resource('leaves', LeaveController::class);
    // Approvals
    Route::post('/approvals/send', [ApprovalController::class, 'sendForApproval'])->name('approvals.send');
    Route::get('/approvals/pending', [ApprovalController::class, 'pending'])->middleware('can.page')->name('approvals.pending');
    Route::post('/approvals/{approval}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::post('/approvals/bulk-approve', [ApprovalController::class, 'bulkApprove'])->name('approvals.bulkApprove');
    Route::post('/approvals/{approval}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');
    Route::post('/approvals/order/{order}/approve-one-up', [ApprovalController::class, 'approveOneUp'])->name('approvals.approveOneUp');
    // Approver-only pages (not Radnik)
    Route::get('/odobrenja/moja', function () { return Inertia::render('Nalozi/OdobrenjaZaSefaKomercijale'); })
        ->middleware(['funkcije:Šef Komercijale,Direktor Komercijale,Direktor Proizvodnje,Šef Operative','can.page'])
        ->name('approvals.mine');
    Route::get('/nalozi/status', function () { return Inertia::render('Nalozi/StatusNaloga'); })
        ->middleware(['funkcije:Šef Komercijale,Direktor Komercijale,Direktor Proizvodnje,Šef Operative','can.page'])
        ->name('orders.status');

    // Direktor Komercijale dedicated approvals page
    Route::get('/odobrenja/direktor-komercijale', function () { return Inertia::render('Nalozi/OdobrenjaDirektoraKomercijale'); })
        ->middleware(['funkcije:Direktor Komercijale','can.page'])
        ->name('approvals.director.sales');

    // Direktor Proizvodnje dedicated approvals page
    Route::get('/odobrenja/direktor-proizvodnje', function () { return Inertia::render('Nalozi/OdobrenjaDirektoraProizvodnje'); })
        ->middleware(['funkcije:Direktor Proizvodnje','can.page'])
        ->name('approvals.director.production');

    // Šef Operative dedicated approvals page
    Route::get('/odobrenja/sef-operative', function () { return Inertia::render('Nalozi/OdobrenjaSefaOperative'); })
        ->middleware(['funkcije:Šef Operative','can.page'])
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
Route::middleware(['auth','can.page'])->group(function () {
    Route::get('/proizvodi', [ProductsController::class, 'index'])->name('products.ui.index');
    Route::get('/proizvodi/novi', [ProductsController::class, 'create'])->name('products.ui.create');
    Route::post('/proizvodi', [ProductsController::class, 'store'])->name('products.ui.store');
    Route::get('/proizvodi/{product}/edit', [ProductsController::class, 'edit'])->name('products.ui.edit');
    Route::put('/proizvodi/{product}', [ProductsController::class, 'update'])->name('products.ui.update');
});

// Moved ppz.izvjestajPregledi into the group above

// Izvještaji za Direktora Komercijale
Route::middleware(['auth','can.page'])->group(function () {
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
    ->middleware(['auth','adminOrFunkcije:Radnik,Šef Komercijale','can.page'])->name('nalozi.za-proizvodnju');
Route::get('/nalozi/kreirani', function() {
    return Inertia::render('Nalozi/KreiraniNalozi');
})->middleware(['auth','verified','adminOrFunkcije:Radnik,Šef Komercijale','can.page'])->name('nalozi.kreirani');
Route::get('/nalozi/radnik/odobreni', function() {
    return Inertia::render('Nalozi/OdobreniNaloziRadnik');
})->middleware(['auth','verified','funkcije:Radnik','can.page'])->name('nalozi.radnik.odobreni');
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

// Admin: Page Access management (isadmin OR specific funkcije)
Route::middleware(['auth','adminOrFunkcije:Direktor Komercijale,Direktor Proizvodnje'])->group(function () {
    Route::get('/admin/page-access', [PageAccessController::class, 'index'])->name('admin.page-access');
    Route::post('/admin/page-access/pages', [PageAccessController::class, 'storePage'])->name('admin.page-access.pages.store');
    Route::post('/admin/page-access/assign', [PageAccessController::class, 'assign'])->name('admin.page-access.assign');
});

// Signed email approval links (no auth middleware; controller will enforce user login matches uid)
Route::get('/approvals/email/direct', [ApprovalController::class, 'emailDirectApprove'])
    ->middleware('signed')
    ->name('approvals.email.direct');
Route::get('/approvals/email/open', [ApprovalController::class, 'emailOpenPending'])
    ->middleware('signed')
    ->name('approvals.email.open');

