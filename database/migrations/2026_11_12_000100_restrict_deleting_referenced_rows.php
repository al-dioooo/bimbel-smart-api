<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-029 / REQ-040: rows that attendance and salary depend on cannot be
 * deleted. The controllers refuse with a message; these foreign keys make
 * the database refuse too, so a delete racing a concurrent insert can no
 * longer orphan absensi (they used to be nullOnDelete).
 *
 * No data changes, so nothing to check first; down() restores nullOnDelete.
 */
return new class extends Migration
{
    /** [table, column, referenced table] */
    private const KEYS = [
        ['absensi', 'jadwal_id', 'jadwal'],
        ['absensi', 'siswa_id', 'siswa'],
        ['jadwal', 'kelas_id', 'kelas'],
        ['kelas', 'mentor_id', 'mentor'],
        ['report_gaji', 'mentor_id', 'mentor'],
    ];

    public function up(): void
    {
        foreach (self::KEYS as [$table, $column, $parent]) {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $parent) {
                $blueprint->dropForeign([$column]);
                $blueprint->foreign($column)->references('id')->on($parent)->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::KEYS as [$table, $column, $parent]) {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $parent) {
                $blueprint->dropForeign([$column]);
                $blueprint->foreign($column)->references('id')->on($parent)->nullOnDelete();
            });
        }
    }
};
