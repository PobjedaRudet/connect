<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\KontrolniPreglediController;
use App\Http\Controllers\PreglediController;
use App\Models\KontrolniPregledi;
use App\Models\Pregledi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;


// RESTful update/delete za Pregledi
Route::put('/pregledi/{id}', [PreglediController::class, 'update']);
Route::delete('/pregledi/{id}', [PreglediController::class, 'destroy']);


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test', function () {
    return response()->json([
        'message' => 'Hello2, World22!'
    ]);
});

Route::get('/employee/{id}', [\App\Http\Controllers\EmployeeController::class, 'showEmployee'])
    ->where('id', '[0-9]+');


Route::post('/attendance', [AttendanceController::class, 'store']);

//Ažuriranje pregleda i lista pregleda
Route::get('/pregledi', [PreglediController::class, 'preglediNakonAzuriranja']);
Route::post('/pregledi/azuriraj', [PreglediController::class, 'azuriraj']);

//Lista kontrolnih pregleda
Route::get('/pregledi/kontrolni', [KontrolniPreglediController::class, 'kontrolniPregledi']);

//Unosi podatke o kontrolnim pregledima
Route::post('/kontrolni-pregledi', [KontrolniPreglediController::class, 'store']);

// API ruta za sve preglede uposlenika
Route::get('/employee/{id}/pregledi', function($id) {
    Log::info("Fetching pregledi for employee with ID: $id");
    return Pregledi::where('employee_id', $id)->orderByDesc('datum_pregleda')->get();
});
// API ruta za sve kontrolne preglede po nizu pregleda
Route::get('/kontrolni-pregledi/by-pregledi', function(\Illuminate\Http\Request $request) {
    $ids = $request->query('ids', []);
    Log::info("Fetching kontrolni pregledi for pregledi IDs: " . implode(',', $ids));
    return KontrolniPregledi::whereIn('pregledi_id', $ids)->orderByDesc('datum_kontrolnog_pregleda')->get();
});
// Izvještaj pregleda za PPZ
Route::get('/ppz-izvjestaj-pregledi', [PreglediController::class, 'apiIzvjestajPregledi']);
// Export pregleda u Word dokument
Route::post('/ppz-izvjestaj-pregledi-word', [\App\Http\Controllers\PreglediController::class, 'apiIzvjestajPreglediWord']);
// Redosljed radnika za izvještaj
Route::get('/radnici-po-redosljedu', [PreglediController::class, 'apiRadniciPoRedosljedu']);


// API ruta za ažuriranje statusa zaposlenika
Route::put('/employees/{id}/status', [EmployeeController::class, 'updateStatus']);
// API ruta za ažuriranje invalidnosti zaposlenika
Route::put('/employees/{id}/invalidnost', [EmployeeController::class, 'updateInvalidnost']);


