<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * §6.6 / NFR-09 (N1): NIK and NPWP are stored encrypted with APP_KEY (the
 * model casts them as `encrypted`). Ciphertext is far longer than 16/25
 * characters, so the columns become text first.
 *
 * Idempotent: a value that already decrypts is left alone, so running the
 * migration again, or after a partial run, never encrypts twice.
 */
return new class extends Migration
{
    private const COLUMNS = ['nik', 'npwp'];

    public function up(): void
    {
        Schema::table('mentor', function (Blueprint $table) {
            $table->text('nik')->nullable()->change();
            $table->text('npwp')->nullable()->change();
        });

        $this->rewrite(fn (string $value) => $this->decrypts($value) ? $value : Crypt::encryptString($value));
    }

    public function down(): void
    {
        $this->rewrite(fn (string $value) => $this->decrypts($value) ? Crypt::decryptString($value) : $value);

        Schema::table('mentor', function (Blueprint $table) {
            $table->string('nik', 16)->nullable()->change();
            $table->string('npwp', 25)->nullable()->change();
        });
    }

    private function rewrite(callable $transform): void
    {
        DB::table('mentor')->select(['id', ...self::COLUMNS])->orderBy('id')->each(function ($mentor) use ($transform) {
            $changes = [];
            foreach (self::COLUMNS as $column) {
                if ($mentor->{$column} !== null && $mentor->{$column} !== '') {
                    $new = $transform($mentor->{$column});
                    if ($new !== $mentor->{$column}) {
                        $changes[$column] = $new;
                    }
                }
            }

            if ($changes !== []) {
                DB::table('mentor')->where('id', $mentor->id)->update($changes);
            }
        });
    }

    private function decrypts(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
