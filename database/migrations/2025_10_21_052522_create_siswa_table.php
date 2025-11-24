<?php

use App\Models\Kelas;
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
        Schema::create('siswa', function (Blueprint $table) {
            $table->id();
            $table->string('nama');

            $table->foreignIdFor(Kelas::class)->nullable()->constrained()->nullOnDelete();

            // Student detail fields
            $table->string('kontak')->nullable();
            $table->text('alamat')->nullable();
            $table->string('asal_sekolah')->nullable();

            // Legal guardian data fields
            $table->string('nama_wali')->nullable();
            $table->string('kontak_wali')->nullable();
            $table->string('pekerjaan_wali')->nullable();
            $table->text('alamat_wali')->nullable();

            $table->boolean('is_active')->default(true);
            $table->dateTime('tanggal_bergabung')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siswa');
    }
};
