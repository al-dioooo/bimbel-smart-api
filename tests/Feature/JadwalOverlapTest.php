<?php

use App\Models\Jadwal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BimbelFixture;

uses(RefreshDatabase::class);

/**
 * J-DEPAN is K-7A (Budi) in three days, 08:00–10:00. Budi also teaches K-7B;
 * Sari teaches K-8B.
 */
beforeEach(function () {
    $this->f = BimbelFixture::make();
    Sanctum::actingAs($this->f->admin);

    $this->tambah = fn ($kelas, string $mulai, string $selesai, int $day = 3) => $this->postJson('/api/jadwal', [
        'kelas_id' => $kelas->id, 'tanggal' => BimbelFixture::day($day), 'waktu_mulai' => $mulai, 'waktu_selesai' => $selesai, 'materi' => 'Uji',
    ]);
});

test('an overlapping session of the same kelas is refused with the UC-13 message', function (string $mulai, string $selesai) {
    $n = Jadwal::count();

    ($this->tambah)($this->f->k7a, $mulai, $selesai)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['waktu_mulai' => 'Jadwal sudah terisi. Silakan pilih waktu lain.']);

    expect(Jadwal::count())->toBe($n);
})->with([
    'covers the end' => ['09:00', '11:00'],
    'one minute over the end' => ['09:59', '11:00'],
    'one minute over the start' => ['06:00', '08:01'],
    'inside' => ['08:30', '09:30'],
    'around' => ['07:00', '11:00'],
]);

test('sessions that only touch, or are apart, are accepted', function (string $mulai, string $selesai) {
    ($this->tambah)($this->f->k7a, $mulai, $selesai)->assertCreated();
})->with([
    'starts when it ends' => ['10:00', '12:00'],
    'ends when it starts' => ['06:00', '08:00'],
    'a minute after' => ['10:01', '12:00'],
    'a minute before' => ['06:00', '07:59'],
]);

test('another kelas of the same mentor conflicts too (REQ-032)', function () {
    ($this->tambah)($this->f->k7b, '09:00', '11:00')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['waktu_mulai' => 'Bentrok dengan Matematika 7A pukul 08:00–10:00']);
});

test('another mentor\'s kelas at the same time, or the same slot on another day, is fine', function () {
    ($this->tambah)($this->f->k8b, '09:00', '11:00')->assertCreated();
    ($this->tambah)($this->f->k7a, '08:00', '10:00', 4)->assertCreated();
});

test('moving a session onto an occupied slot is refused and leaves it unchanged (REQ-034)', function () {
    $other = $this->f->jadwal($this->f->k7a, BimbelFixture::day(4));

    $this->putJson("/api/jadwal/{$other->id}", ['tanggal' => BimbelFixture::day(3), 'waktu_mulai' => '09:00', 'waktu_selesai' => '11:00'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('waktu_mulai');

    $other->refresh();
    expect($other->tanggal)->toStartWith(BimbelFixture::day(4))
        ->and($other->waktu_mulai)->toBe('08:00:00');
});

test('a partial update is checked with the stored values', function () {
    $other = $this->f->jadwal($this->f->k7a, BimbelFixture::day(4));

    // Only the date changes; the stored 08:00–10:00 now collides with J-DEPAN.
    $this->putJson("/api/jadwal/{$other->id}", ['tanggal' => BimbelFixture::day(3)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('waktu_mulai');
});

test('a session never conflicts with itself', function () {
    $this->putJson("/api/jadwal/{$this->f->jDepan->id}", ['waktu_mulai' => '09:00', 'waktu_selesai' => '11:00'])->assertOk();
    $this->putJson("/api/jadwal/{$this->f->jDepan->id}", ['materi' => 'Pecahan'])->assertOk();

    expect($this->f->jDepan->fresh()->waktu_mulai)->toBe('09:00:00');
});
