<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BimbelFixture;

uses(RefreshDatabase::class);

/** §3.1 e (N3): messages in Bahasa Indonesia, the UC-01 ones verbatim. */
beforeEach(function () {
    $this->f = BimbelFixture::make();
});

test('login messages follow UC-01', function () {
    $this->postJson('/api/login', ['identifier' => '', 'password' => ''])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Username dan password tidak boleh kosong. (dan 1 kesalahan lainnya)')
        ->assertJsonPath('errors.password.0', 'Username dan password tidak boleh kosong.');

    $this->postJson('/api/login', ['identifier' => 'budi', 'password' => 'salah-sekali'])
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Username atau password salah.');

    $this->postJson('/api/login', ['identifier' => 'budi', 'password' => BimbelFixture::PASSWORD])
        ->assertOk()->assertJsonPath('message', 'Login berhasil.');
});

test('validation messages and attribute names are Indonesian', function () {
    Sanctum::actingAs($this->f->admin);

    $this->postJson('/api/siswa', ['nama' => '', 'kelas_id' => 999999])
        ->assertUnprocessable()
        ->assertJsonPath('errors.nama.0', 'Nama wajib diisi.')
        ->assertJsonPath('errors.kelas_id.0', 'Kelas yang dipilih tidak terdaftar.');

    $this->postJson('/api/jadwal', ['kelas_id' => $this->f->k7a->id, 'tanggal' => '2026-02-30', 'waktu_mulai' => '25:00', 'waktu_selesai' => '10:00'])
        ->assertJsonPath('errors.tanggal.0', 'Tanggal harus berupa tanggal yang valid.')
        ->assertJsonPath('errors.waktu_mulai.0', 'Waktu mulai harus sesuai format H:i.');

    $this->postJson('/api/mentor', ['name' => 'Dewi', 'username' => 'budi', 'email' => 'bukan-email', 'password' => 'pendek'])
        ->assertJsonPath('errors.username.0', 'Username sudah digunakan.')
        ->assertJsonPath('errors.email.0', 'Email harus berupa alamat email yang valid.')
        ->assertJsonPath('errors.password.0', 'Kata sandi minimal 8 karakter.');
});

test('success messages are Indonesian and the siswa typo is gone', function () {
    Sanctum::actingAs($this->f->admin);

    $this->postJson('/api/siswa', ['nama' => 'Citra', 'kelas_id' => $this->f->k7a->id])->assertJsonPath('message', 'Data siswa berhasil ditambahkan.');
    $this->getJson('/api/kelas')->assertJsonPath('message', 'Data kelas berhasil diambil.');
    $this->putJson("/api/kelas/{$this->f->k7b->id}", ['nama' => 'Matematika 7C'])->assertJsonPath('message', 'Data kelas berhasil diperbarui.');

    Sanctum::actingAs($this->f->budi);
    $this->postJson('/api/absensi', ['jadwal_id' => $this->f->jLalu->id, 'absensi' => [['siswa_id' => $this->f->siswa['Andi']->id, 'status' => 'H']]])
        ->assertJsonPath('message', 'Absensi berhasil disimpan.');
});

test('framework errors are Indonesian too', function () {
    $this->getJson('/api/me')->assertUnauthorized()->assertJsonPath('message', 'Sesi Anda telah berakhir atau Anda belum login. Silakan login kembali.');

    Sanctum::actingAs($this->f->budi);
    $this->getJson('/api/mentor')->assertForbidden()->assertJsonPath('message', 'Fitur ini hanya dapat digunakan oleh Admin/Owner.');
    $this->getJson("/api/kelas/{$this->f->k8b->id}")->assertForbidden()->assertJsonPath('message', 'Anda tidak memiliki akses ke data ini.');
    $this->getJson('/api/jadwal/999999')->assertNotFound()->assertJsonPath('message', 'Data tidak ditemukan.');
    $this->getJson('/api/tidak-ada')->assertNotFound()->assertJsonPath('message', 'Alamat tidak ditemukan.');
    $this->putJson('/api/me')->assertStatus(405)->assertJsonPath('message', 'Metode permintaan tidak didukung untuk alamat ini.');
});
