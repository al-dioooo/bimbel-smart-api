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
