<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\KelasController;
use App\Http\Controllers\MentorController;
use App\Http\Controllers\SiswaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::get('/me', [AuthController::class, 'me']);
    
    Route::apiResource('kelas', KelasController::class)->parameters([
        'kelas' => 'kelas'
    ]);
    Route::apiResource('siswa', SiswaController::class)->parameters([
        'siswa' => 'siswa'
    ]);
    Route::apiResource('mentor', MentorController::class)->parameters([
        'mentor' => 'mentor'
    ]);
});