<?php

use App\Models\Kelas;
use App\Models\Mentor;
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
        Schema::create('pengajuan_jadwal', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(Mentor::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(Kelas::class)->nullable()->constrained()->nullOnDelete();

            $table->date('tanggal_sebelum');
            $table->date('tanggal_sesudah');

            $table->time('waktu_mulai_sebelum');
            $table->time('waktu_mulai_sesudah');
            $table->time('waktu_selesai_sebelum');
            $table->time('waktu_selesai_sesudah');

            $table->string('status', 25)->default('pending')->comment('pending, approved, rejected');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengajuan_jadwal');
    }
};
