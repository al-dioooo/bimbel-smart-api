<?php

use App\Models\Mentor;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-064: manual bonus or potongan per mentor per salary period, set by the
 * admin and applied on top of Σ(hadir × tarif).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penyesuaian_gaji', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(Mentor::class)->constrained('mentor')->cascadeOnDelete();
            // First day of the salary month.
            $table->date('periode');
            $table->string('jenis', 10)->comment('bonus, potongan');
            $table->decimal('nominal', 15, 2);
            $table->string('keterangan')->nullable();
            $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['mentor_id', 'periode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penyesuaian_gaji');
    }
};
