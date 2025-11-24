<?php

use App\Models\Jadwal;
use App\Models\Siswa;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('absensi', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(Jadwal::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(Siswa::class)->nullable()->constrained()->nullOnDelete();

            $table->date('tanggal');
            $table->char('status', 1)->default('h'); // h: hadir, i: izin, s: sakit, a: alpa

            $table->boolean('is_open')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('absensi');
    }
};
