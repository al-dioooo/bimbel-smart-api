<?php

use App\Models\Absensi;
use App\Models\AturanGaji;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Mentor;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BimbelFixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->f = BimbelFixture::make();
    Sanctum::actingAs($this->f->admin);
    $this->gaji = fn () => $this->getJson('/api/report/gaji?paginate=false')->json('data');
});

test('a jadwal with absensi cannot be deleted (REQ-040)', function () {
    $before = ($this->gaji)();

    $this->deleteJson("/api/jadwal/{$this->f->jIsi->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['jadwal' => 'sudah memiliki catatan absensi']);

    expect(Jadwal::find($this->f->jIsi->id))->not->toBeNull()
        ->and(Absensi::where('jadwal_id', $this->f->jIsi->id)->count())->toBe(2)
        ->and(($this->gaji)())->toBe($before);
});

test('a jadwal without absensi can be deleted', function () {
    $this->deleteJson("/api/jadwal/{$this->f->jDepan->id}")->assertOk();

    expect(Jadwal::find($this->f->jDepan->id))->toBeNull();
});

test('a kelas with jadwal cannot be deleted (REQ-029)', function () {
    $this->deleteJson("/api/kelas/{$this->f->k7a->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['kelas' => 'masih memiliki jadwal']);

    expect(Kelas::find($this->f->k7a->id))->not->toBeNull()
        ->and(Jadwal::where('kelas_id', $this->f->k7a->id)->count())->toBe(4);
});

test('a kelas without jadwal can be deleted', function () {
    $this->deleteJson("/api/kelas/{$this->f->k7b->id}")->assertOk();

    expect(Kelas::find($this->f->k7b->id))->toBeNull();
});
