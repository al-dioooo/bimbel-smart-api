<?php

use App\Models\PengajuanJadwal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BimbelFixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->f = BimbelFixture::make();
    Sanctum::actingAs($this->f->budi);

    $this->ajukan = fn (string $mulai, string $selesai) => $this->postJson('/api/pengajuan-jadwal', [
        'jadwal_id' => $this->f->jDepan->id,
        'tanggal_sebelum' => BimbelFixture::day(3), 'tanggal_sesudah' => BimbelFixture::day(4),
        'waktu_mulai_sebelum' => '08:00:00', 'waktu_mulai_sesudah' => $mulai,
        'waktu_selesai_sebelum' => '10:00:00', 'waktu_selesai_sesudah' => $selesai,
        'alasan' => 'Uji',
    ]);
});

test('the new session must end after it starts (D7, REQ-039)', function (string $mulai, string $selesai) {
    ($this->ajukan)($mulai, $selesai)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('waktu_selesai_sesudah');

    expect(PengajuanJadwal::count())->toBe(0);
})->with([
    'ends before it starts' => ['15:00:00', '13:00:00'],
    'one minute before' => ['13:00:00', '12:59:00'],
    'same minute' => ['13:00:00', '13:00:00'],
]);

test('one minute after the start is accepted', function () {
    ($this->ajukan)('13:00:00', '13:01:00')->assertCreated()->assertJsonPath('data.status', 'pending');
});
