<?php

use App\Models\Mentor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
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
});
