<?php

use App\Models\Absensi;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BimbelFixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->f = BimbelFixture::make();
    $this->rows = fn (array $statuses) => collect($statuses)
        ->map(fn ($status, $nama) => ['siswa_id' => $this->f->siswa[$nama]->id, 'status' => $status])
        ->values()->all();
    $this->stored = fn ($jadwal) => Absensi::with('siswa')->where('jadwal_id', $jadwal->id)->orderBy('id')->get()
        ->map(fn ($a) => [$a->siswa->nama, $a->status, substr((string) $a->tanggal, 0, 10)])->all();
});

test('a first submission stores every row with the jadwal date', function () {
    Sanctum::actingAs($this->f->budi);

    $this->postJson('/api/absensi', ['jadwal_id' => $this->f->jLalu->id, 'absensi' => ($this->rows)(['Andi' => 'H', 'Bela' => 'I'])])
        ->assertCreated()
        ->assertJsonCount(2, 'data');

    $d = BimbelFixture::day(-1);
    expect(($this->stored)($this->f->jLalu))->toBe([['Andi', 'h', $d], ['Bela', 'i', $d]]);
});

describe('input ganda (D3)', function () {
    test('re-submitting a siswa that is already recorded is refused and keeps the old status', function (string $who) {
        Sanctum::actingAs($who === 'admin' ? $this->f->admin : $this->f->budi);

        $this->postJson('/api/absensi', ['jadwal_id' => $this->f->jIsi->id, 'absensi' => ($this->rows)(['Andi' => 'A'])])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['absensi' => 'Absensi sudah diisi untuk Andi']);

        $d = BimbelFixture::day(-7);
        expect(($this->stored)($this->f->jIsi))->toBe([['Andi', 'h', $d], ['Bela', 's', $d]]);
    })->with(['budi', 'admin']);

    test('a payload mixing a new and an existing siswa saves nothing', function () {
        Sanctum::actingAs($this->f->budi);
        $citra = Siswa::create(['nama' => 'Citra', 'kelas_id' => $this->f->k7a->id]);

        $this->postJson('/api/absensi', ['jadwal_id' => $this->f->jIsi->id, 'absensi' => [
            ['siswa_id' => $citra->id, 'status' => 'H'],
            ['siswa_id' => $this->f->siswa['Bela']->id, 'status' => 'H'],
        ]])->assertUnprocessable();

        expect(Absensi::where('siswa_id', $citra->id)->exists())->toBeFalse();
    });

    test('a siswa added to the kelas later can still be recorded on a filled session', function () {
        Sanctum::actingAs($this->f->budi);
        $citra = Siswa::create(['nama' => 'Citra', 'kelas_id' => $this->f->k7a->id]);

        $this->postJson('/api/absensi', ['jadwal_id' => $this->f->jIsi->id, 'absensi' => [['siswa_id' => $citra->id, 'status' => 'S']]])
            ->assertCreated()
            ->assertJsonCount(3, 'data');
    });

    test('the same siswa twice in one payload is refused (UC-08 2a)', function () {
        Sanctum::actingAs($this->f->budi);
        $andi = $this->f->siswa['Andi']->id;

        $this->postJson('/api/absensi', ['jadwal_id' => $this->f->jLalu->id, 'absensi' => [
            ['siswa_id' => $andi, 'status' => 'H'],
            ['siswa_id' => $andi, 'status' => 'A'],
        ]])->assertUnprocessable()->assertJsonValidationErrors(['absensi.1.siswa_id' => 'Input ganda']);

        expect(($this->stored)($this->f->jLalu))->toBe([]);
    });
});

describe('tanggal (D4)', function () {
    test('a tanggal other than the jadwal date is refused', function () {
        Sanctum::actingAs($this->f->budi);

        $this->postJson('/api/absensi', ['jadwal_id' => $this->f->jLalu->id, 'tanggal' => BimbelFixture::day(30), 'absensi' => ($this->rows)(['Andi' => 'H'])])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tanggal' => 'harus sama dengan tanggal jadwal']);

        expect(($this->stored)($this->f->jLalu))->toBe([]);
    });

    test('sending the jadwal date itself is accepted', function () {
        Sanctum::actingAs($this->f->budi);

        $this->postJson('/api/absensi', ['jadwal_id' => $this->f->jLalu->id, 'tanggal' => BimbelFixture::day(-1), 'absensi' => ($this->rows)(['Andi' => 'H'])])
            ->assertCreated();
    });
});

describe('class membership (D5)', function () {
    test('nobody can record a siswa of another kelas, admins included', function (string $who) {
        Sanctum::actingAs($who === 'admin' ? $this->f->admin : $this->f->budi);

        $this->postJson('/api/absensi', ['jadwal_id' => $this->f->jLalu->id, 'absensi' => ($this->rows)(['Andi' => 'H', 'Dimas' => 'H'])])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['absensi' => 'tidak terdaftar di kelas ini']);

        expect(($this->stored)($this->f->jLalu))->toBe([]);
    })->with(['budi', 'admin']);
});
