<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\PassController;
use App\Http\Controllers\PreglediController;
use App\Http\Controllers\ProductController;
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
use App\Http\Controllers\ProductionPlanningController;

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

Route::get('/ppz/dashboard', function () {
    return Inertia::render('PPZ/Dashboard');
})->middleware(['auth'])->name('ppz.dashboard');

// Planiranje proizvodnje (Direktor Proizvodnje)
Route::middleware(['auth'])->group(function () {
    Route::get('/planiranje/proizvodnja', [ProductionPlanningController::class, 'index'])->name('planning.index');
    Route::post('/planiranje/proizvodnja', [ProductionPlanningController::class, 'store'])->name('planning.store');
    Route::get('/planiranje/gantt', [ProductionPlanningController::class, 'gantt'])->name('planning.gantt');
});

Route::get('/prodaja/dashboard', function () {
    return Inertia::render('Nalozi/ProdajaDashboard');
})->middleware(['auth'])->name('prodaja.dashboard');

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
})->middleware(['auth']);

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/pregledi/index', [PreglediController::class, 'index'])->middleware(['auth'])->name('pregledi.index');
Route::get('/pregledi/upcoming', [PreglediController::class, 'reportUpcoming'])->middleware(['auth'])->name('pregledi.upcoming');
Route::get('/pregledi/nextMonth', [PreglediController::class, 'reportUpcomingNextMonth'])->middleware(['auth'])->name('pregledi.nextMonth');
Route::get('/pregledi/kontrolni', function () {
    return Inertia::render('Pregledi/KontrolniPregledi');
})->middleware(['auth'])->name('pregledi.kontrolni');

Route::get('/pregledi/za-sedam-dana', [PreglediController::class, 'zaSedamDana'])->middleware(['auth']);

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
    Route::get('/approvals/pending', [ApprovalController::class, 'pending'])->name('approvals.pending');
    Route::post('/approvals/{approval}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::post('/approvals/bulk-approve', [ApprovalController::class, 'bulkApprove'])->name('approvals.bulkApprove');
    Route::post('/approvals/{approval}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');
    Route::post('/approvals/order/{order}/approve-one-up', [ApprovalController::class, 'approveOneUp'])->name('approvals.approveOneUp');
    // Approver-only pages (not Radnik)
    Route::get('/odobrenja/moja', function () { return Inertia::render('Nalozi/OdobrenjaZaSefaKomercijale'); })
        ->middleware('funkcije:Šef Komercijale,Direktor Komercijale,Direktor Proizvodnje,Šef Operative')
        ->name('approvals.mine');
    Route::get('/nalozi/status', function () { return Inertia::render('Nalozi/StatusNaloga'); })
        ->middleware('funkcije:Šef Komercijale,Direktor Komercijale,Direktor Proizvodnje,Šef Operative')
        ->name('orders.status');

    // Direktor Komercijale dedicated approvals page
    Route::get('/odobrenja/direktor-komercijale', function () { return Inertia::render('Nalozi/OdobrenjaDirektoraKomercijale'); })
        ->middleware('funkcije:Direktor Komercijale')
        ->name('approvals.director.sales');

    // Direktor Proizvodnje dedicated approvals page
    Route::get('/odobrenja/direktor-proizvodnje', function () { return Inertia::render('Nalozi/OdobrenjaDirektoraProizvodnje'); })
        ->middleware('funkcije:Direktor Proizvodnje')
        ->name('approvals.director.production');

    // Šef Operative dedicated approvals page
    Route::get('/odobrenja/sef-operative', function () { return Inertia::render('Nalozi/OdobrenjaSefaOperative'); })
        ->middleware('funkcije:Šef Operative')
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

Route::get('/ppz/izvjestaj-pregledi', function() {
    return Inertia::render('PPZ/IzvjestajPregledi');
})->middleware(['auth'])->name('ppz.izvjestajPregledi');

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
});



// Radnik-only pages
Route::get('/nalozi/nalozi-za-proizvodnju', [ProductionOrderController::class, 'showForm'])
    ->middleware(['auth','funkcije:Radnik'])->name('nalozi.za-proizvodnju');
Route::get('/nalozi/kreirani', function() {
    return Inertia::render('Nalozi/KreiraniNalozi');
})->middleware(['auth','verified','funkcije:Radnik'])->name('nalozi.kreirani');
Route::get('/nalozi/radnik/odobreni', function() {
    return Inertia::render('Nalozi/OdobreniNaloziRadnik');
})->middleware(['auth','verified','funkcije:Radnik'])->name('nalozi.radnik.odobreni');
    Route::get('/productionorders/mine/created', [ProductionOrderController::class, 'myCreated'])->middleware(['auth'])->name('productionorders.mine.created');
    Route::get('/productionorders/created', [ProductionOrderController::class, 'created'])->middleware(['auth'])->name('productionorders.created');

Route::post('/productionorders', [ProductionOrderController::class, 'store'])->middleware(['auth'])->name('productionorders.store');
Route::get('/productionorders/{order}', [ProductionOrderController::class, 'details'])->middleware(['auth'])->name('productionorders.show');
Route::get('/api/productionorders/{order}', [ProductionOrderController::class, 'detailsJson'])->middleware(['auth'])->name('productionorders.show.json');

// Lista proizvoda za BIHNEL proizvode
Route::get('/productslistBihnel', [ProductController::class, 'numeredlistBihnel'])->middleware(['auth']);
// Lista proizvoda za BK-6 proizvode
Route::get('/productslistBK6', [ProductController::class, 'numeredlistBK6'])->middleware(['auth']);
// Lista proizvoda za BK-8 proizvode
Route::get('/productslistBK8', [ProductController::class, 'numeredlistBK8'])->middleware(['auth']);
require __DIR__ . '/auth.php';

