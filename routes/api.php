<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\KontrolniPreglediController;
use App\Http\Controllers\PreglediController;
use App\Models\KontrolniPregledi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;




Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test', function () {
    return response()->json([
        'message' => 'Hello, World2!'
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
