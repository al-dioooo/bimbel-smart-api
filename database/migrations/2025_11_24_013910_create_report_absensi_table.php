<?php

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
        Schema::create('report_absensi', function (Blueprint $table) {
            $table->id();

            $table->date('from');
            $table->date('to');

            $table->unsignedInteger('hadir');
            $table->unsignedInteger('sakit');
            $table->unsignedInteger('izin');
            $table->unsignedInteger('alpa');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_absensi');
    }
};
