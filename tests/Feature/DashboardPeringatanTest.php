<?php

use App\Models\Kelas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BimbelFixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->f = BimbelFixture::make();
    $this->peringatan = function ($user) {
        Sanctum::actingAs($user);

        return $this->getJson('/api/dashboard/stats')->assertOk()->json('data');
    };
});

test('a mentor is warned about a past session without absensi (UC-16 2b)', function () {
    $p = ($this->peringatan)($this->f->budi);

    // J-LALU (yesterday) has no absensi; J-ISI and the September session are filled.
    expect($p['jumlah_absensi_belum_diisi'])->toBe(1)
        ->and($p['absensi_belum_diisi'])->toBe([[
            'jadwal_id' => $this->f->jLalu->id,
            'kelas_id' => $this->f->k7a->id,
            'kelas' => 'Matematika 7A',
            'tanggal' => BimbelFixture::day(-1),
            'waktu_mulai' => '08:00',
            'waktu_selesai' => '10:00',
            'materi' => 'Materi uji',
        ]]);
});

test('the warning disappears once the session is recorded', function () {
    Sanctum::actingAs($this->f->budi);
    $this->postJson('/api/absensi', ['jadwal_id' => $this->f->jLalu->id, 'absensi' => [
        ['siswa_id' => $this->f->siswa['Andi']->id, 'status' => 'H'],
    ]])->assertCreated();

    expect(($this->peringatan)($this->f->budi)['jumlah_absensi_belum_diisi'])->toBe(0);
});

test('a mentor sees today\'s sessions with their absensi state (UC-16 2a)', function () {
    $pagi = $this->f->jadwal($this->f->k7a, BimbelFixture::day(0), ['Andi' => 'h'], '07:00:00', '08:00:00');
    $siang = $this->f->jadwal($this->f->k7b, BimbelFixture::day(0), [], '13:00:00', '15:00:00');

    $hariIni = ($this->peringatan)($this->f->budi)['jadwal_hari_ini'];

    expect(collect($hariIni)->map(fn ($j) => [$j['jadwal_id'], $j['waktu_mulai'], $j['sudah_diabsen']])->all())
        ->toBe([[$pagi->id, '07:00', true], [$siang->id, '13:00', false]]);
});

test('nothing is shown when neither applies (UC-16 2a alternative)', function () {
    expect(collect(($this->peringatan)($this->f->sari))->only(['jumlah_absensi_belum_diisi', 'absensi_belum_diisi', 'jadwal_hari_ini'])->all())->toBe([
        'jumlah_absensi_belum_diisi' => 0,
        'absensi_belum_diisi' => [],
        'jadwal_hari_ini' => [],
    ]);
});

test('a session of a kelas without students is not a missing absensi', function () {
    $kosong = Kelas::create(['nama' => 'Kimia 9C', 'tingkat' => 'SMP', 'mentor_id' => $this->f->mSari->id]);
    $this->f->jadwal($kosong, BimbelFixture::day(-2));

    expect(($this->peringatan)($this->f->sari)['jumlah_absensi_belum_diisi'])->toBe(0);
});

test('other mentors\' sessions never show, and admins get no reminder block', function () {
    expect(collect(($this->peringatan)($this->f->sari)['absensi_belum_diisi']))->toBeEmpty();

    Sanctum::actingAs($this->f->admin);
    expect($this->getJson('/api/dashboard/stats')->json('data'))->not->toHaveKeys(['absensi_belum_diisi', 'jadwal_hari_ini']);
});
