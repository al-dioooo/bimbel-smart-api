<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BimbelFixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->f = BimbelFixture::make();
    Sanctum::actingAs($this->f->admin);
    $this->reports = [
        'gaji' => '/api/report/gaji',
        'gaji.detail' => "/api/report/gaji/{$this->f->mBudi->id}",
        'absensi' => '/api/report/absensi',
        'absensi.detail' => "/api/report/absensi/{$this->f->k7a->id}",
    ];
});

test('an invalid filter is a 422 on that filter, not an unfiltered report (D8)', function (string $report, string $query, string $field) {
    $this->getJson($this->reports[$report].'?'.$query)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    ['gaji', 'bulan=2026-9', 'bulan'],
    ['gaji', 'bulan=2026-13', 'bulan'],
    ['gaji', 'bulan=September', 'bulan'],
    ['gaji', 'from=2026-09-01', 'to'],
    ['gaji', 'to=2026-09-30', 'from'],
    ['gaji', 'from=2026-09-30&to=2026-09-01', 'to'],
    ['gaji', 'from=2026-02-30&to=2026-03-01', 'from'],
    ['gaji.detail', 'bulan=2026-9', 'bulan'],
    ['absensi', 'from=2026-09-01', 'to'],
    ['absensi', 'to=2026-09-30', 'from'],
    ['absensi.detail', 'from=2026-09-01', 'to'],
    ['absensi', 'bulan=2026-9', 'bulan'],
    ['absensi.detail', 'bulan=September', 'bulan'],
    ['gaji.detail', 'from=2026-09-30&to=2026-09-01', 'to'],
]);

test('valid filters still work', function (string $report, string $query) {
    $this->getJson($this->reports[$report].'?'.$query)->assertOk();
})->with([
    ['gaji', 'bulan=2026-09'],
    ['gaji', 'from=2026-09-01&to=2026-09-30'],
    ['gaji', 'from=2026-09-07&to=2026-09-07'],
    ['gaji', ''],
    ['gaji.detail', 'bulan=2026-09'],
    ['absensi', 'from=2026-09-01&to=2026-09-30'],
    ['absensi.detail', 'from=2026-09-01&to=2026-09-30'],
]);

test('the bulan filter keeps only that month', function () {
    $rows = $this->getJson('/api/report/gaji?paginate=false&bulan=2026-09')->json('data');

    expect(collect($rows)->map(fn ($r) => [$r['mentor'], (int) $r['bulan'], (float) $r['nominal']])->sortBy(0)->values()->all())
        ->toBe([['Budi Santoso', 9, 60000.0], ['Sari Dewi', 9, 40000.0]]);
});

describe('every validated filter is applied (review #29)', function () {
    test('laporan gaji detail keeps only the from/to range', function () {
        $rows = fn (string $q) => collect($this->getJson("/api/report/gaji/{$this->f->mBudi->id}?paginate=false{$q}")->json('data'))
            ->map(fn ($r) => [$r['kelas'], (int) $r['bulan'], (float) $r['nominal']])->sortBy(1)->values()->all();

        expect($rows('&from=2026-09-01&to=2026-09-30'))->toBe([['Matematika 7A', 9, 60000.0]])
            ->and($rows('&from=2026-10-01&to=2026-10-31'))->toBe([])
            ->and(count($rows('')))->toBe(2);
    });

    test('laporan absensi keeps only the bulan', function () {
        $rows = fn (string $q) => collect($this->getJson("/api/report/absensi?paginate=false{$q}")->json('data'))
            ->map(fn ($r) => [$r['kelas'], (int) $r['bulan'], (int) $r['hadir']])->sortBy(0)->values()->all();

        expect($rows('&bulan=2026-09'))->toBe([['Fisika 8B', 9, 1], ['Matematika 7A', 9, 2]])
            ->and($rows('&bulan=2026-10'))->toBe([])
            ->and(count($rows('')))->toBe(3);
    });

    test('laporan absensi detail keeps only the bulan', function () {
        $rows = fn (string $q) => collect($this->getJson("/api/report/absensi/{$this->f->k7a->id}?paginate=false{$q}")->json('data.rekap'))
            ->map(fn ($r) => [$r['siswa'], (int) $r['hadir'], (int) $r['sakit']])->all();

        expect($rows('&bulan=2026-09'))->toBe([['Andi', 1, 0], ['Bela', 1, 0]])
            ->and($rows(''))->toBe([['Andi', 2, 0], ['Bela', 1, 1]]);
    });
});
