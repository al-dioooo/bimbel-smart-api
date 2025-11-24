<?php

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
        Schema::create('report_gaji', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(Mentor::class)->nullable()->constrained()->nullOnDelete();

            $table->date('bulan');
            $table->unsignedInteger('jumlah_kehadiran');
            $table->double('total_gaji', 17, 2);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_gaji');
    }
};
