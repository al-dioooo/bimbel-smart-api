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
 * Idempotent: a value that already decrypts with the current APP_KEY is
 * left alone, so running the migration again, or after a partial run, never
 * encrypts twice. It stops without changing anything when APP_KEY is empty,
 * or when a value looks encrypted (Laravel's payload) but does not decrypt,
 * i.e. it was written with another key. An empty string becomes NULL first,
 * since the `encrypted` cast only skips NULL.
 *
 * Deploy with the application in maintenance mode, so the old code cannot
 * write plain values after this runs, and back up APP_KEY with the database.
 */
return new class extends Migration
{
    private const COLUMNS = ['nik', 'npwp'];

    public function up(): void
    {
        $this->ensureKeyAndReadable();

        Schema::table('mentor', function (Blueprint $table) {
            $table->text('nik')->nullable()->change();
            $table->text('npwp')->nullable()->change();
        });

        foreach (self::COLUMNS as $column) {
            DB::table('mentor')->where($column, '')->update([$column => null]);
        }

        $this->rewrite(fn (string $value) => $this->decrypts($value) ? $value : Crypt::encryptString($value));
    }

    public function down(): void
    {
        $this->ensureKeyAndReadable();

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

    /**
     * Stops before any change when there is no key, or when a value is
     * ciphertext this key cannot read: re-encrypting it would bury the
     * data, decrypting it is impossible.
     */
    private function ensureKeyAndReadable(): void
    {
        if (blank(config('app.key'))) {
            throw new RuntimeException('APP_KEY is empty. Set the production APP_KEY (and back it up) before encrypting NIK/NPWP.');
        }

        $unreadable = [];
        DB::table('mentor')->select(['id', ...self::COLUMNS])->orderBy('id')->each(function ($mentor) use (&$unreadable) {
            foreach (self::COLUMNS as $column) {
                $value = $mentor->{$column};
                if (is_string($value) && $value !== '' && $this->looksEncrypted($value) && ! $this->decrypts($value)) {
                    $unreadable[] = "  mentor id {$mentor->id}: {$column}";
                }
            }
        });

        if ($unreadable !== []) {
            throw new RuntimeException(
                "These values are encrypted but do not decrypt with the current APP_KEY (written with another key?):\n"
                .implode("\n", $unreadable)
                ."\nRestore the APP_KEY they were written with; nothing was changed."
            );
        }
    }

    /** Laravel's payload: base64 of JSON with iv, value and mac. */
    private function looksEncrypted(string $value): bool
    {
        $payload = json_decode((string) base64_decode($value, true), true);

        return is_array($payload) && isset($payload['iv'], $payload['value'], $payload['mac']);
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
