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

test('a mentor who still teaches cannot be deleted, and the message names the kelas', function () {
    $before = ($this->gaji)();

    $this->deleteJson("/api/mentor/{$this->f->mBudi->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['mentor' => 'Matematika 7A, Matematika 7B']);

    expect(Mentor::find($this->f->mBudi->id))->not->toBeNull()
        ->and(Kelas::where('mentor_id', $this->f->mBudi->id)->count())->toBe(2)
        ->and(($this->gaji)())->toBe($before);
});

test('a mentor without kelas can be deleted together with the account', function () {
    $this->deleteJson("/api/mentor/{$this->f->mRina->id}")->assertOk();

    expect(Mentor::find($this->f->mRina->id))->toBeNull()
        ->and($this->f->rina->fresh())->toBeNull();
});

test('an aturan gaji whose kelas has kehadiran cannot be deleted', function () {
    $before = ($this->gaji)();

    $this->deleteJson("/api/aturan-gaji/{$this->f->aturan7a->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['aturan_gaji' => 'sudah memiliki kehadiran']);

    expect(AturanGaji::find($this->f->aturan7a->id))->not->toBeNull()
        ->and(($this->gaji)())->toBe($before);
});

test('an aturan gaji whose kelas has no kehadiran can be deleted', function () {
    // K-7B has no sessions; an `alpa`-only session would not count either.
    $this->f->jadwal($this->f->k7b, BimbelFixture::day(-2), ['Gita' => 'a']);

    $this->deleteJson("/api/aturan-gaji/{$this->f->aturan7b->id}")->assertOk();

    expect(AturanGaji::find($this->f->aturan7b->id))->toBeNull();
});
