<?php

use App\Models\Mentor;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BimbelFixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->f = BimbelFixture::make();
    $this->raw = fn (int $id) => DB::table('mentor')->where('id', $id)->first(['nik', 'npwp']);
});

test('NIK and NPWP are stored encrypted and read back in plain text (N1)', function () {
    Sanctum::actingAs($this->f->admin);

    $id = $this->postJson('/api/mentor', [
        'name' => 'Dewi', 'username' => 'dewi', 'email' => 'dewi@uji.test', 'password' => 'rahasia-uji8',
        'nik' => '3201010101900001', 'npwp' => '01.234.567.8-901.000',
    ])->assertCreated()->json('data.id');

    $raw = ($this->raw)($id);
    expect($raw->nik)->not->toContain('3201010101900001')
        ->and(Crypt::decryptString($raw->nik))->toBe('3201010101900001')
        ->and(Crypt::decryptString($raw->npwp))->toBe('01.234.567.8-901.000');

    $this->getJson("/api/mentor/$id")->assertJsonPath('data.nik', '3201010101900001')->assertJsonPath('data.npwp', '01.234.567.8-901.000');
});

test('an empty NIK stays null', function () {
    expect(($this->raw)($this->f->mBudi->id)->nik)->toBeNull()
        ->and($this->f->mBudi->fresh()->nik)->toBeNull();
});

test('the migration encrypts existing plain values once and down() restores them', function () {
    $migration = require database_path('migrations/2026_11_12_000007_encrypt_nik_npwp_on_mentor_table.php');
    $migration->down();
    DB::table('mentor')->where('id', $this->f->mBudi->id)->update(['nik' => '3201010101900001', 'npwp' => '01.234.567.8-901.000']);

    $migration->up();
    $once = ($this->raw)($this->f->mBudi->id);
    $migration->up(); // again: nothing changes

    expect(($this->raw)($this->f->mBudi->id))->toEqual($once)
        ->and(Crypt::decryptString($once->nik))->toBe('3201010101900001')
        ->and(Mentor::find($this->f->mBudi->id)->npwp)->toBe('01.234.567.8-901.000');

    $migration->down();
    expect((array) ($this->raw)($this->f->mBudi->id))->toBe(['nik' => '3201010101900001', 'npwp' => '01.234.567.8-901.000']);
})->skip(fn () => DB::connection()->getDriverName() === 'sqlite', 'SQLite rebuilds the mentor table to change a column, which its restrictOnDelete keys refuse; run on PostgreSQL.');

describe('a safe migration (review #40)', function () {
    beforeEach(function () {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite rebuilds the mentor table to change a column, which its restrictOnDelete keys refuse; run on PostgreSQL.');
        }
        $this->migration = require database_path('migrations/2026_11_12_000007_encrypt_nik_npwp_on_mentor_table.php');
        $this->migration->down();
    });

    test('an empty NIK or NPWP becomes NULL, so the mentor still loads', function () {
        DB::table('mentor')->where('id', $this->f->mBudi->id)->update(['nik' => '', 'npwp' => '']);

        $this->migration->up();

        expect((array) ($this->raw)($this->f->mBudi->id))->toBe(['nik' => null, 'npwp' => null]);
        Sanctum::actingAs($this->f->admin);
        $this->getJson("/api/mentor/{$this->f->mBudi->id}")->assertOk()->assertJsonPath('data.nik', null);
        $this->getJson('/api/mentor')->assertOk();
    });

    test('it stops when APP_KEY is empty', function () {
        DB::table('mentor')->where('id', $this->f->mBudi->id)->update(['nik' => '3201010101900001']);
        config(['app.key' => '']);

        expect(fn () => $this->migration->up())->toThrow(RuntimeException::class, 'APP_KEY is empty');
        expect(($this->raw)($this->f->mBudi->id)->nik)->toBe('3201010101900001');
    });

    test('it stops on ciphertext from another key instead of encrypting it again', function () {
        $other = new Encrypter(random_bytes(32), 'AES-256-CBC');
        $foreign = $other->encryptString('3201010101900001');
        // As after an earlier run with that key, when the column was text.
        Schema::table('mentor', fn ($table) => $table->text('nik')->nullable()->change());
        DB::table('mentor')->where('id', $this->f->mBudi->id)->update(['nik' => $foreign]);
        DB::table('mentor')->where('id', $this->f->mSari->id)->update(['npwp' => '01.234.567.8-901.000']);

        expect(fn () => $this->migration->up())
            ->toThrow(RuntimeException::class, "mentor id {$this->f->mBudi->id}: nik");
        expect(($this->raw)($this->f->mBudi->id)->nik)->toBe($foreign)
            ->and(($this->raw)($this->f->mSari->id)->npwp)->toBe('01.234.567.8-901.000');
        expect(fn () => $this->migration->down())->toThrow(RuntimeException::class, 'do not decrypt');
    });
});
