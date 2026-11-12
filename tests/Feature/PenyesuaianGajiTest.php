<?php

use App\Models\AturanGaji;
use App\Models\PenyesuaianGaji;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BimbelFixture;

uses(RefreshDatabase::class);

/**
 * September 2026 in the fixture: Budi 2 × 30.000, Sari 1 × 40.000.
 */
beforeEach(function () {
    $this->f = BimbelFixture::make();
    Sanctum::actingAs($this->f->admin);

    $this->rekap = fn (string $query = 'bulan=2026-09') => collect($this->getJson("/api/report/gaji?paginate=false&$query")->assertOk()->json('data'))
        ->mapWithKeys(fn ($r) => ["{$r['mentor']}|{$r['tahun']}-{$r['bulan']}" => [(int) $r['total_kehadiran_murid'], (float) $r['gaji_pokok'], (float) $r['bonus'], (float) $r['potongan'], (float) $r['nominal']]])
        ->sortKeys()->all();
    $this->tambah = fn (array $body) => $this->postJson('/api/penyesuaian-gaji', $body + ['mentor_id' => $this->f->mBudi->id, 'bulan' => '2026-09']);
});

test('a bonus and a potongan change the month\'s salary (REQ-064)', function () {
    ($this->tambah)(['jenis' => 'bonus', 'nominal' => 50000, 'keterangan' => 'Tambahan sesi'])
        ->assertCreated()
        ->assertJsonPath('data.bulan', '2026-09')
        ->assertJsonPath('data.jenis', 'bonus');
    ($this->tambah)(['jenis' => 'potongan', 'nominal' => 10000])->assertCreated();

    expect(($this->rekap)())->toBe([
        'Budi Santoso|2026-9' => [2, 60000.0, 50000.0, 10000.0, 100000.0],
        'Sari Dewi|2026-9' => [1, 40000.0, 0.0, 0.0, 40000.0],
    ]);
});

test('an adjustment in a month without sessions still shows that month', function () {
    ($this->tambah)(['jenis' => 'bonus', 'nominal' => 25000, 'bulan' => '2026-08'])->assertCreated();

    expect(($this->rekap)('bulan=2026-08'))->toBe(['Budi Santoso|2026-8' => [0, 0.0, 25000.0, 0.0, 25000.0]]);
});

test('date ranges and search include the adjustments of the months they touch', function () {
    ($this->tambah)(['jenis' => 'bonus', 'nominal' => 5000])->assertCreated();

    expect(($this->rekap)('from=2026-09-07&to=2026-09-07'))->toBe(['Budi Santoso|2026-9' => [2, 60000.0, 5000.0, 0.0, 65000.0]])
        ->and(array_keys(($this->rekap)('bulan=2026-09&search=budi')))->toBe(['Budi Santoso|2026-9']);
});

test('the per-kelas detail returns the adjustments of the month next to the kelas rows', function () {
    ($this->tambah)(['jenis' => 'potongan', 'nominal' => 7500, 'keterangan' => 'Terlambat'])->assertCreated();

    $res = $this->getJson("/api/report/gaji/{$this->f->mBudi->id}?paginate=false&bulan=2026-09")->assertOk();

    expect($res->json('data.0.nominal'))->toEqual(60000)
        ->and($res->json('penyesuaian.0'))->toMatchArray(['jenis' => 'potongan', 'nominal' => 7500.0, 'keterangan' => 'Terlambat', 'bulan' => '2026-09']);
});

test('the aturan gaji route accepts a bonus as a penyesuaian (UC-11)', function () {
    $this->postJson('/api/aturan-gaji', [
        'kelas_id' => $this->f->k7a->id, 'jenis' => 'bonus', 'mentor_id' => $this->f->mBudi->id, 'nominal' => 50000, 'bulan' => '2026-09',
    ])->assertCreated()->assertJsonPath('data.jenis', 'bonus');

    expect(AturanGaji::where('kelas_id', $this->f->k7a->id)->count())->toBe(1)
        ->and(($this->rekap)()['Budi Santoso|2026-9'][4])->toBe(110000.0);
});

test('without jenis the aturan gaji route still manages tarifs', function () {
    $this->postJson('/api/aturan-gaji', ['kelas_id' => $this->f->k7a->id, 'tarif' => 35000])->assertUnprocessable()->assertJsonValidationErrors('kelas_id');
    $this->postJson('/api/aturan-gaji', ['kelas_id' => $this->f->k7a->id, 'jenis' => 'lain', 'tarif' => 1])->assertUnprocessable()->assertJsonValidationErrors('jenis');
});

test('invalid adjustments are refused', function (array $body, string $field) {
    ($this->tambah)($body)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['jenis' => 'hadiah', 'nominal' => 1], 'jenis'],
    [['jenis' => 'bonus', 'nominal' => 0], 'nominal'],
    [['jenis' => 'bonus', 'nominal' => 'lima puluh'], 'nominal'],
    [['jenis' => 'bonus', 'nominal' => 1, 'bulan' => '2026-9'], 'bulan'],
    [['jenis' => 'bonus', 'nominal' => 1, 'mentor_id' => 999999], 'mentor_id'],
    [['jenis' => 'bonus', 'nominal' => 1, 'keterangan' => str_repeat('k', 256)], 'keterangan'],
]);

test('adjustments are listed, updated and deleted by the admin only', function () {
    $id = ($this->tambah)(['jenis' => 'bonus', 'nominal' => 50000])->json('data.id');

    $this->getJson("/api/penyesuaian-gaji?bulan=2026-09&mentor_id={$this->f->mBudi->id}")->assertOk()->assertJsonPath('data.data.0.id', $id);
    $this->putJson("/api/penyesuaian-gaji/$id", ['nominal' => 75000, 'bulan' => '2026-10'])->assertOk()->assertJsonPath('data.bulan', '2026-10');
    expect(PenyesuaianGaji::find($id)->nominal)->toBe(75000.0);

    Sanctum::actingAs($this->f->budi);
    $this->getJson('/api/penyesuaian-gaji')->assertForbidden();
    $this->postJson('/api/penyesuaian-gaji', ['mentor_id' => $this->f->mBudi->id, 'bulan' => '2026-09', 'jenis' => 'bonus', 'nominal' => 1])->assertForbidden();
    $this->deleteJson("/api/penyesuaian-gaji/$id")->assertForbidden();

    Sanctum::actingAs($this->f->admin);
    $this->deleteJson("/api/penyesuaian-gaji/$id")->assertOk();
    expect(PenyesuaianGaji::count())->toBe(0);
});
