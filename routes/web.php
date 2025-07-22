<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\PassController;
use App\Http\Controllers\PreglediController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Mail\UpcomingExamsMail;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;










Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/ppz/dashboard', function () {
    return Inertia::render('PPZ/Dashboard');
})->name('ppz.dashboard');

Route::get('/prodaja/dashboard', function () {
    return Inertia::render('Prodaja/Dashboard');
})->name('prodaja.dashboard');

Route::get('/private', function () {
    return Inertia::render('PrivacyPolicy');
})->name('private');

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
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/pregledi/index', [PreglediController::class, 'index'])->name('pregledi.index');
Route::get('/pregledi/upcoming', [PreglediController::class, 'reportUpcoming'])->name('pregledi.upcoming');
Route::get('/pregledi/nextMonth', [PreglediController::class, 'reportUpcomingNextMonth'])->name('pregledi.nextMonth');
Route::get('/pregledi/kontrolni', function () {
    return Inertia::render('Pregledi/KontrolniPregledi');
})->name('pregledi.kontrolni');


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::resource('employees', EmployeeController::class);
    Route::resource('attendances', AttendanceController::class);
    Route::resource('passes', PassController::class);
    Route::resource('leaves', LeaveController::class);
});

Route::resource('products', ProductController::class);

Route::get('/ppz/izvjestaj-pregledi', function() {
    return Inertia::render('PPZ/IzvjestajPregledi');
})->name('ppz.izvjestajPregledi');

Route::get('/nalozi/nalozi-za-proizvodnju', function () {
    return Inertia::render('Nalozi/NaloziZaProizvodnju');
})->name('nalozi.za-proizvodnju');

require __DIR__ . '/auth.php';
