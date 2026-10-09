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

// Both roles. `mentor.scope` pins ?mentor_id= for mentors, and show/store
// actions check ownership, so a mentor only ever sees their own kelas.
Route::group(['middleware' => ['auth:sanctum', 'mentor.scope']], function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::patch('/me/update', [AuthController::class, 'updateProfile']);

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Data (read)
    Route::apiResource('kelas', KelasController::class)->only(['index', 'show'])->parameters([
        'kelas' => 'kelas'
    ]);
    Route::apiResource('siswa', SiswaController::class)->only(['index', 'show'])->parameters([
        'siswa' => 'siswa'
    ]);

    // Absensi — mentors save attendance for their own jadwal.
    Route::apiResource('absensi', AbsensiController::class)->only(['index', 'show', 'store'])->parameters([
        'absensi' => 'absensi'
    ]);

    // Jadwal
    Route::apiResource('jadwal', JadwalController::class)->only(['index', 'show'])->parameters([
        'jadwal' => 'jadwal'
    ]);
    // Mentors submit requests and may cancel their own pending ones.
    Route::apiResource('pengajuan-jadwal', PengajuanJadwalController::class)->only(['index', 'show', 'store', 'destroy'])->parameters([
        'pengajuanJadwal' => 'pengajuanJadwal'
    ]);

    // Report — both are derived on read (there is no report_absensi table), so
    // these are plain routes rather than model-bound apiResources.
    Route::get('report/gaji/{mentor}', [ReportGajiController::class, 'show']);
    Route::get('report/absensi', [ReportAbsensiController::class, 'index']);
    Route::get('report/absensi/{kelas}', [ReportAbsensiController::class, 'show']);

    // Notifications — each user reads and marks their own.
    Route::apiResource('notification', NotificationController::class)->only(['index', 'show', 'update', 'destroy'])->parameters([
        'notification' => 'notification'
    ]);

    // Admin only.
    Route::middleware('admin')->group(function () {
        Route::apiResource('kelas', KelasController::class)->except(['index', 'show'])->parameters([
            'kelas' => 'kelas'
        ]);
        Route::apiResource('siswa', SiswaController::class)->except(['index', 'show'])->parameters([
            'siswa' => 'siswa'
        ]);
        Route::apiResource('mentor', MentorController::class)->parameters([
            'mentor' => 'mentor'
        ]);

        Route::apiResource('absensi', AbsensiController::class)->only(['update', 'destroy'])->parameters([
            'absensi' => 'absensi'
        ]);

        Route::apiResource('jadwal', JadwalController::class)->except(['index', 'show'])->parameters([
            'jadwal' => 'jadwal'
        ]);
        // Approving or rejecting is the only update.
        Route::apiResource('pengajuan-jadwal', PengajuanJadwalController::class)->only(['update'])->parameters([
            'pengajuanJadwal' => 'pengajuanJadwal'
        ]);

        Route::apiResource('aturan-gaji', AturanGajiController::class)->parameters([
            'aturanGaji' => 'aturanGaji'
        ]);

        Route::get('report/gaji', [ReportGajiController::class, 'index']);

        Route::apiResource('notification', NotificationController::class)->only(['store'])->parameters([
            'notification' => 'notification'
        ]);
    });
});
