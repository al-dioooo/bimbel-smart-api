<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UC-10 step 9: a finalized salary month. Its existence locks the month.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periode_gaji', function (Blueprint $table) {
            $table->id();

            // First day of the salary month; one finalization per month.
            $table->date('periode')->unique();
            $table->timestamp('finalized_at');
            $table->foreignIdFor(User::class, 'finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('jumlah_mentor')->default(0);
            $table->decimal('total_gaji', 15, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periode_gaji');
    }
};
