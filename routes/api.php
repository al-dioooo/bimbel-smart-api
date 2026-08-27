<?php

use App\Http\Controllers\AbsensiController;
use App\Http\Controllers\AturanGajiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JadwalController;
use App\Http\Controllers\KelasController;
use App\Http\Controllers\MentorController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PengajuanJadwalController;
use App\Http\Controllers\ReportAbsensiController;
use App\Http\Controllers\ReportGajiController;
use App\Http\Controllers\SiswaController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::patch('/me/update', [AuthController::class, 'updateProfile']);

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Data
    Route::apiResource('kelas', KelasController::class)->parameters([
        'kelas' => 'kelas'
    ]);
    Route::apiResource('siswa', SiswaController::class)->parameters([
        'siswa' => 'siswa'
    ]);
    Route::apiResource('mentor', MentorController::class)->parameters([
        'mentor' => 'mentor'
    ]);

    // Absensi
    Route::apiResource('absensi', AbsensiController::class)->parameters([
        'absensi' => 'absensi'
    ]);

    // Jadwal
    Route::apiResource('jadwal', JadwalController::class)->parameters([
        'jadwal' => 'jadwal'
    ]);
    Route::apiResource('pengajuan-jadwal', PengajuanJadwalController::class)->parameters([
        'pengajuanJadwal' => 'pengajuanJadwal'
    ]);

    // Aturan Gaji
    Route::apiResource('aturan-gaji', AturanGajiController::class)->parameters([
        'aturanGaji' => 'aturanGaji'
    ]);

    // Report — both are derived on read (there is no report_absensi table), so
    // these are plain routes rather than model-bound apiResources.
    Route::get('report/gaji', [ReportGajiController::class, 'index']);
    Route::get('report/gaji/{mentor}', [ReportGajiController::class, 'show']);
    Route::get('report/absensi', [ReportAbsensiController::class, 'index']);
    Route::get('report/absensi/{kelas}', [ReportAbsensiController::class, 'show']);

    // Notifications
    Route::apiResource('notification', NotificationController::class)->parameters([
        'notification' => 'notification'
    ]);
});
