<?php

use App\Http\Controllers\AttendanceController;
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

//add attendance post request to handle Employe Id in Attendance index function
Route::post('/attendance', [AttendanceController::class, 'store']);

