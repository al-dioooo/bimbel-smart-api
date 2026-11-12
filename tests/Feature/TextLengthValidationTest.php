<?php

use App\Models\Notification;
use App\Models\PengajuanJadwal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BimbelFixture;

uses(RefreshDatabase::class);

/**
 * D22 / G11: every string input that lands in a varchar column is limited to
 * that column's length, so too-long text is a 422 with a message on the
 * field, never a database error (PostgreSQL answers "value too long", 500).
 */
beforeEach(function () {
    $this->f = BimbelFixture::make();
    $f = $this->f;

    $pengajuan = PengajuanJadwal::create([
        'jadwal_id' => $f->jDepan->id, 'status' => 'pending',
        'tanggal_sebelum' => BimbelFixture::day(3), 'tanggal_sesudah' => BimbelFixture::day(4),
        'waktu_mulai_sebelum' => '08:00:00', 'waktu_mulai_sesudah' => '13:00:00',
        'waktu_selesai_sebelum' => '10:00:00', 'waktu_selesai_sesudah' => '15:00:00',
    ]);
    $notification = Notification::create(['user_id' => $f->budi->id, 'title' => 'Halo', 'message' => 'Isi']);

    // [actor, method, uri, valid body without the field under test]
    $this->endpoints = [
        'mentor.store' => ['admin', 'POST', '/api/mentor', ['name' => 'Dewi', 'username' => 'dewi', 'email' => 'dewi@uji.test', 'password' => 'rahasia-uji8']],
        'mentor.update' => ['admin', 'PUT', "/api/mentor/{$f->mBudi->id}", []],
        'kelas.store' => ['admin', 'POST', '/api/kelas', ['nama' => 'Kimia 9C', 'tingkat' => 'SMP', 'mentor_id' => $f->mSari->id]],
        'kelas.update' => ['admin', 'PUT', "/api/kelas/{$f->k7b->id}", []],
        'siswa.store' => ['admin', 'POST', '/api/siswa', ['nama' => 'Fajar', 'kelas_id' => $f->k7a->id]],
        'siswa.update' => ['admin', 'PUT', "/api/siswa/{$f->siswa['Gita']->id}", ['nama' => 'Gita']],
        'jadwal.store' => ['admin', 'POST', '/api/jadwal', ['kelas_id' => $f->k7b->id, 'tanggal' => BimbelFixture::day(6), 'waktu_mulai' => '13:00', 'waktu_selesai' => '15:00']],
        'jadwal.update' => ['admin', 'PUT', "/api/jadwal/{$f->jDepan->id}", []],
        'pengajuan.store' => ['budi', 'POST', '/api/pengajuan-jadwal', [
            'jadwal_id' => $f->jDepan->id, 'tanggal_sebelum' => BimbelFixture::day(3), 'tanggal_sesudah' => BimbelFixture::day(5),
            'waktu_mulai_sebelum' => '08:00:00', 'waktu_mulai_sesudah' => '13:00:00',
            'waktu_selesai_sebelum' => '10:00:00', 'waktu_selesai_sesudah' => '15:00:00',
        ]],
        'pengajuan.update' => ['admin', 'PATCH', "/api/pengajuan-jadwal/{$pengajuan->id}", []],
        'notification.store' => ['admin', 'POST', '/api/notification', ['user_id' => $f->budi->id, 'message' => 'Isi']],
        'notification.update' => ['admin', 'PATCH', "/api/notification/{$notification->id}", []],
        'profile.update' => ['budi', 'PATCH', '/api/me/update', []],
    ];

    $this->send = function (string $endpoint, string $field, string $value) {
        [$actor, $method, $uri, $body] = $this->endpoints[$endpoint];
        Sanctum::actingAs($actor === 'admin' ? $this->f->admin : $this->f->budi);

        return $this->json($method, $uri, [$field => $value] + $body);
    };
});

test('text one character over the column is refused with a message on the field', function (string $endpoint, string $field, int $max) {
    ($this->send)($endpoint, $field, str_repeat('a', $max + 1))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with('varchar fields');

test('text exactly at the column length is accepted', function (string $endpoint, string $field, int $max) {
    $res = ($this->send)($endpoint, $field, str_repeat('a', $max));

    expect($res->status())->toBeIn([200, 201], json_encode($res->json()));
})->with('varchar fields');

dataset('varchar fields', [
    ['mentor.store', 'name', 255],
    ['mentor.store', 'tempat_tanggal_lahir', 255],
    ['mentor.store', 'kontak', 100],
    ['mentor.store', 'nik', 16],
    ['mentor.store', 'npwp', 25],
    ['mentor.update', 'name', 255],
    ['mentor.update', 'username', 255],
    ['mentor.update', 'tempat_tanggal_lahir', 255],
    ['mentor.update', 'kontak', 100],
    ['mentor.update', 'nik', 16],
    ['mentor.update', 'npwp', 25],
    ['kelas.store', 'nama', 255],
    ['kelas.store', 'tingkat', 255],
    ['kelas.update', 'nama', 255],
    ['kelas.update', 'tingkat', 255],
    ['siswa.store', 'nama', 255],
    ['siswa.store', 'tempat_tanggal_lahir', 255],
    ['siswa.store', 'kontak', 100],
    ['siswa.store', 'asal_sekolah', 255],
    ['siswa.store', 'nama_wali', 255],
    ['siswa.store', 'kontak_wali', 100],
    ['siswa.store', 'pekerjaan_wali', 255],
    ['siswa.update', 'nama', 255],
    ['siswa.update', 'pekerjaan_wali', 255],
    ['jadwal.store', 'materi', 255],
    ['jadwal.update', 'materi', 255],
    ['pengajuan.store', 'alasan', 255],
    ['pengajuan.update', 'alasan', 255],
    ['notification.store', 'title', 255],
    ['notification.update', 'title', 255],
    ['notification.update', 'link', 100],
    ['profile.update', 'name', 255],
    ['profile.update', 'username', 255],
]);
