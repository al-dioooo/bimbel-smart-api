<?php

use App\Http\Controllers\KelasController;
use App\Http\Controllers\MentorController;
use App\Http\Controllers\SiswaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('kelas', KelasController::class)->parameters([
    'kelas' => 'kelas'
]);
Route::apiResource('siswa', SiswaController::class)->parameters([
    'siswa' => 'siswa'
]);
Route::apiResource('mentor', MentorController::class)->parameters([
    'mentor' => 'mentor'
]);