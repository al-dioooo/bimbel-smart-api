<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-022 / REQ-028: a kelas (nama + tingkat, case and outer spaces
 * ignored) and a mentor's phone number exist once. Validation already
 * refuses duplicates; these indexes stop two simultaneous requests too.
 *
 * Existing duplicates are listed and the migration stops; which record is
 * right is for the admin to decide. An empty kontak means "none" and
 * becomes NULL first (NULLs never collide).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('mentor')->where('kontak', '')->update(['kontak' => null]);

        $problems = [];

        $kelas = DB::table('kelas')
            ->selectRaw('LOWER(TRIM(nama)) as nama, LOWER(TRIM(tingkat)) as tingkat')
            ->groupByRaw('LOWER(TRIM(nama)), LOWER(TRIM(tingkat))')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        foreach ($kelas as $dup) {
            $ids = DB::table('kelas')->whereRaw('LOWER(TRIM(nama)) = ? AND LOWER(TRIM(tingkat)) = ?', [$dup->nama, $dup->tingkat])
                ->orderBy('id')->get(['id', 'nama', 'tingkat'])
                ->map(fn ($k) => "id {$k->id} (\"{$k->nama}\" {$k->tingkat})")->implode(', ');
            $problems[] = "  kelas {$dup->nama} / {$dup->tingkat}: {$ids}";
        }

        $kontak = DB::table('mentor')->whereNotNull('kontak')->groupBy('kontak')->havingRaw('COUNT(*) > 1')->pluck('kontak');
        foreach ($kontak as $nomor) {
            $ids = DB::table('mentor')->where('kontak', $nomor)->orderBy('id')->pluck('id')->implode(', ');
            $problems[] = "  mentor kontak {$nomor}: mentor id {$ids}";
        }

        if ($problems !== []) {
            throw new RuntimeException(
                "Cannot add the unique indexes on kelas (nama, tingkat) and mentor.kontak: these rows are duplicated.\n"
                .implode("\n", $problems)
                ."\nRename or merge the duplicates, then run the migration again."
            );
        }

        DB::statement('CREATE UNIQUE INDEX kelas_nama_tingkat_unique ON kelas (LOWER(TRIM(nama)), LOWER(TRIM(tingkat)))');

        Schema::table('mentor', function (Blueprint $table) {
            $table->unique('kontak');
        });
    }

    public function down(): void
    {
        Schema::table('mentor', function (Blueprint $table) {
            $table->dropUnique(['kontak']);
        });

        DB::statement('DROP INDEX kelas_nama_tingkat_unique');
    }
};
