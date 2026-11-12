<?php

use App\Models\PeriodeGaji;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-058: report_gaji holds the finalized salary of one mentor for one
 * month, with enough detail to show and print it later without recomputing
 * (names, tarif and adjustments as they were).
 *
 * The table existed but was never written by the application. If production
 * already has two rows for the same mentor and month, the migration lists
 * them and stops instead of deleting either.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('report_gaji')
            ->whereNotNull('mentor_id')
            ->groupBy('mentor_id', 'bulan')
            ->havingRaw('COUNT(*) > 1')
            ->get(['mentor_id', 'bulan']);

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                "Cannot add a unique index on report_gaji (mentor_id, bulan): these rows are duplicated.\n"
                .$duplicates->map(fn ($d) => "  mentor_id {$d->mentor_id}, bulan {$d->bulan}")->implode("\n")
                ."\nKeep one row per mentor and month, then run the migration again."
            );
        }

        Schema::table('report_gaji', function (Blueprint $table) {
            $table->foreignIdFor(PeriodeGaji::class)->nullable()->after('id')->constrained('periode_gaji')->cascadeOnDelete();
            $table->string('mentor_nama')->nullable()->after('mentor_id');
            $table->decimal('gaji_pokok', 15, 2)->default(0)->after('jumlah_kehadiran');
            $table->decimal('bonus', 15, 2)->default(0)->after('gaji_pokok');
            $table->decimal('potongan', 15, 2)->default(0)->after('bonus');
            $table->json('rincian')->nullable()->after('total_gaji');

            $table->unique(['mentor_id', 'bulan']);
        });
    }

    public function down(): void
    {
        Schema::table('report_gaji', function (Blueprint $table) {
            $table->dropUnique(['mentor_id', 'bulan']);
            $table->dropConstrainedForeignId('periode_gaji_id');
            $table->dropColumn(['mentor_nama', 'gaji_pokok', 'bonus', 'potongan', 'rincian']);
        });
    }
};
