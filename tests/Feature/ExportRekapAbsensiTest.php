<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\BimbelFixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->f = BimbelFixture::make();
    $this->sheet = function ($response): array {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());
        $rows = IOFactory::load($path)->getActiveSheet()->toArray();
        unlink($path);

        return $rows;
    };
});

test('the rekap exports to Excel with the same rows as the screen and a total', function () {
    Sanctum::actingAs($this->f->admin);

    $res = $this->get('/api/report/absensi/export?format=xlsx&from=2026-09-01&to=2026-09-30')->assertOk();
    expect($res->headers->get('Content-Disposition'))->toContain('rekap-absensi.xlsx');

    $rows = ($this->sheet)($res);
    expect($rows[0][0])->toBe('Rekap Absensi Siswa')
        ->and($rows[1][0])->toBe('Tanggal: 2026-09-01 s.d. 2026-09-30')
        ->and(array_slice($rows, 4))->toEqual([
            ['Kelas', 'Tingkat', 'Guru/Mentor', 'Periode', 'Hadir', 'Sakit', 'Izin', 'Alpa', 'Total', 'Kehadiran'],
            ['Fisika 8B', 'SMP', 'Sari Dewi', 'September 2026', 1, 0, 0, 0, 1, '100%'],
            ['Matematika 7A', 'SMP', 'Budi Santoso', 'September 2026', 2, 0, 0, 0, 2, '100%'],
            ['Total', null, null, null, 3, 0, 0, 0, 3, '100%'],
        ]);
});

test('the rekap exports to PDF', function () {
    Sanctum::actingAs($this->f->admin);

    $res = $this->get('/api/report/absensi/export?format=pdf&search=fisika')->assertOk();
    expect($res->headers->get('Content-Type'))->toContain('application/pdf')
        ->and(substr($res->getContent(), 0, 5))->toBe('%PDF-');
});

test('a mentor exports only their own kelas', function () {
    Sanctum::actingAs($this->f->sari);

    $rows = ($this->sheet)($this->get('/api/report/absensi/export?format=xlsx&mentor_id='.$this->f->mBudi->id)->assertOk());

    expect(collect(array_slice($rows, 5, -1))->pluck(0)->unique()->values()->all())->toBe(['Fisika 8B']);
});

test('the per-kelas rekap exports per siswa', function () {
    Sanctum::actingAs($this->f->budi);

    $rows = ($this->sheet)($this->get("/api/report/absensi/{$this->f->k7a->id}?format=xlsx")->assertOk());

    expect($rows[0][0])->toBe('Rekap Absensi Matematika 7A (SMP)')
        ->and($rows[1][0])->toBe('Guru/Mentor: Budi Santoso')
        ->and(array_slice($rows, 5))->toEqual([
            ['Siswa', 'Hadir', 'Sakit', 'Izin', 'Alpa', 'Total', 'Kehadiran'],
            ['Andi', 2, 0, 0, 0, 2, '100%'],
            ['Bela', 1, 1, 0, 0, 2, '50%'],
            ['Total', 3, 1, 0, 0, 4, '75%'],
        ]);
});

test('another mentor\'s kelas, a missing format or an unknown one are refused', function () {
    Sanctum::actingAs($this->f->sari);
    $this->getJson("/api/report/absensi/{$this->f->k7a->id}?format=pdf")->assertNotFound();

    $this->getJson('/api/report/absensi/export')->assertUnprocessable()->assertJsonValidationErrors('format');
    $this->getJson('/api/report/absensi/export?format=csv')->assertUnprocessable()->assertJsonValidationErrors('format');
});

test('the bulan filter applies to both exports (review #29/#39)', function () {
    Sanctum::actingAs($this->f->admin);

    $rekap = ($this->sheet)($this->get('/api/report/absensi/export?format=xlsx&bulan=2026-09')->assertOk());
    expect(array_column(array_slice($rekap, 5, -1), 0))->toBe(['Fisika 8B', 'Matematika 7A']);

    $detail = ($this->sheet)($this->get("/api/report/absensi/{$this->f->k7a->id}?format=xlsx&bulan=2026-09")->assertOk());
    expect(array_slice($detail, 6))->toEqual([
        ['Andi', 1, 0, 0, 0, 1, '100%'],
        ['Bela', 1, 0, 0, 0, 1, '100%'],
        ['Total', 2, 0, 0, 0, 2, '100%'],
    ]);
});
