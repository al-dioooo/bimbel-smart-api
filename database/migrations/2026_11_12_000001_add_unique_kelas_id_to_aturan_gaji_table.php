<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One aturan gaji per kelas (REQ-055). The salary report joins aturan_gaji on
 * kelas_id, so a second rule doubles every hadir of that kelas.
 *
 * Existing duplicates are never resolved here: which tarif is right is a
 * business decision. The migration stops and lists them instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('aturan_gaji')
            ->whereNotNull('kelas_id')
            ->groupBy('kelas_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('kelas_id');

        if ($duplicates->isNotEmpty()) {
            $lines = $duplicates->map(function ($kelasId) {
                $rules = DB::table('aturan_gaji')->where('kelas_id', $kelasId)->orderBy('id')->get(['id', 'tarif'])
                    ->map(fn ($rule) => "id {$rule->id} (tarif {$rule->tarif})")
                    ->implode(', ');

                return "  kelas_id {$kelasId}: {$rules}";
            });

            throw new RuntimeException(
                "Cannot add a unique index on aturan_gaji.kelas_id: these kelas have more than one aturan gaji.\n"
                .$lines->implode("\n")
                ."\nKeep one rule per kelas (delete or reassign the others), then run the migration again."
            );
        }

        Schema::table('aturan_gaji', function (Blueprint $table) {
            $table->unique('kelas_id');
        });
    }

    public function down(): void
    {
        Schema::table('aturan_gaji', function (Blueprint $table) {
            $table->dropUnique(['kelas_id']);
        });
    }
};
