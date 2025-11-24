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
        Schema::create('jadwal', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(Kelas::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(Mentor::class)->nullable()->constrained()->nullOnDelete();

            $table->date('tanggal');
            $table->time('waktu_mulai');
            $table->time('waktu_selesai');

            $table->string('materi')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jadwal');
    }
};
