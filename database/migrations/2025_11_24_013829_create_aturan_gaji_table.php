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
        Schema::create('aturan_gaji', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(Kelas::class)->nullable()->constrained()->nullOnDelete();

            $table->double('tarif', 17, 2);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aturan_gaji');
    }
};
