<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BimbelFixture;

uses(RefreshDatabase::class);

/**
 * D18: report search ignores letter case, like every other module. SQLite's
 * LIKE already does for ASCII, so this file only proves the fix when run on
 * PostgreSQL (DB_CONNECTION=pgsql).
 */
beforeEach(function () {
    $this->f = BimbelFixture::make();
    Sanctum::actingAs($this->f->admin);
});

test('laporan gaji finds a mentor whatever the case', function (string $search) {
    $mentors = collect($this->getJson("/api/report/gaji?paginate=false&search=$search")->json('data'))->pluck('mentor')->unique()->values()->all();

    expect($mentors)->toBe(['Sari Dewi']);
})->with(['sari', 'SARI', 'Sari']);

test('rekap absensi finds a kelas or mentor whatever the case', function (string $search, string $kelas) {
    $found = collect($this->getJson("/api/report/absensi?paginate=false&search=$search")->json('data'))->pluck('kelas')->unique()->values()->all();

    expect($found)->toBe([$kelas]);
})->with([
    ['fisika', 'Fisika 8B'],
    ['FISIKA', 'Fisika 8B'],
    ['sari', 'Fisika 8B'],
]);

test('the per-kelas rekap finds a siswa whatever the case', function () {
    $found = collect($this->getJson("/api/report/absensi/{$this->f->k7a->id}?paginate=false&search=andi")->json('data.rekap'))->pluck('siswa')->all();

    expect($found)->toBe(['Andi']);
});
