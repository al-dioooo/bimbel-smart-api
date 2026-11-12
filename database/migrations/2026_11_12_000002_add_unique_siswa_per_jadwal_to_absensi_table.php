<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One absensi row per siswa per jadwal (UC-08 2a). The store action already
 * refuses "input ganda"; the index closes the gap between two simultaneous
 * submissions.
 *
 * Existing duplicates are listed and the migration stops; which status is
 * right is for the admin to decide through Ubah Absensi.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('absensi')
            ->whereNotNull('jadwal_id')
            ->whereNotNull('siswa_id')
            ->groupBy('jadwal_id', 'siswa_id')
            ->havingRaw('COUNT(*) > 1')
            ->get(['jadwal_id', 'siswa_id']);

        if ($duplicates->isNotEmpty()) {
            $lines = $duplicates->map(function ($d) {
                $rows = DB::table('absensi')->where('jadwal_id', $d->jadwal_id)->where('siswa_id', $d->siswa_id)
                    ->orderBy('id')->get(['id', 'status'])
                    ->map(fn ($row) => "id {$row->id} ({$row->status})")
                    ->implode(', ');

                return "  jadwal_id {$d->jadwal_id}, siswa_id {$d->siswa_id}: {$rows}";
            });

            throw new RuntimeException(
                "Cannot add a unique index on absensi (jadwal_id, siswa_id): these siswa have more than one record on the same jadwal.\n"
                .$lines->implode("\n")
                ."\nKeep one record per siswa and jadwal, then run the migration again."
            );
        }

        Schema::table('absensi', function (Blueprint $table) {
            $table->unique(['jadwal_id', 'siswa_id']);
        });
    }

    public function down(): void
    {
        Schema::table('absensi', function (Blueprint $table) {
            $table->dropUnique(['jadwal_id', 'siswa_id']);
        });
    }
};
