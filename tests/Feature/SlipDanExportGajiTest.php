<?php

use App\Exports\TabelExport;
use App\Models\PenyesuaianGaji;
use App\Services\GajiCalculator;
use App\Support\Laporan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\BimbelFixture;

uses(RefreshDatabase::class);

/**
 * September 2026: Budi 2 × 30.000 + bonus 50.000 − potongan 10.000 = 100.000;
 * Sari 1 × 40.000.
 */
beforeEach(function () {
    $this->f = BimbelFixture::make();
    PenyesuaianGaji::create(['mentor_id' => $this->f->mBudi->id, 'periode' => '2026-09-01', 'jenis' => 'bonus', 'nominal' => 50000, 'keterangan' => 'Lembur']);
    PenyesuaianGaji::create(['mentor_id' => $this->f->mBudi->id, 'periode' => '2026-09-01', 'jenis' => 'potongan', 'nominal' => 10000, 'keterangan' => 'Terlambat']);

    // The downloaded workbook as rows of cell values.
    $this->sheet = function ($response): array {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());
        $rows = IOFactory::load($path)->getActiveSheet()->toArray();
        unlink($path);

        return $rows;
    };
});

describe('slip gaji PDF (REQ-061)', function () {
    test('the admin downloads any mentor\'s slip as a PDF', function () {
        Sanctum::actingAs($this->f->admin);

        $res = $this->get("/api/report/gaji/{$this->f->mBudi->id}?format=pdf&bulan=2026-09", ['Accept' => 'application/pdf'])->assertOk();

        expect($res->headers->get('Content-Type'))->toContain('application/pdf')
            ->and($res->headers->get('Content-Disposition'))->toContain('slip-gaji-budi-santoso-2026-09.pdf')
            ->and(substr($res->getContent(), 0, 5))->toBe('%PDF-');
    });

    test('a mentor downloads their own slip but not another mentor\'s', function () {
        Sanctum::actingAs($this->f->budi);
        $this->get("/api/report/gaji/{$this->f->mBudi->id}?format=pdf&bulan=2026-09")->assertOk();

        Sanctum::actingAs($this->f->sari);
        $this->getJson("/api/report/gaji/{$this->f->mBudi->id}?format=pdf&bulan=2026-09")->assertForbidden();
    });

    test('a non-JSON download request still gets JSON errors', function () {
        Sanctum::actingAs($this->f->admin);

        $this->get("/api/report/gaji/{$this->f->mBudi->id}?format=pdf&bulan=2026-9", ['Accept' => 'application/pdf'])
            ->assertUnprocessable()->assertJsonValidationErrors('bulan');
        $this->get("/api/report/gaji/{$this->f->mBudi->id}?format=xlsx", ['Accept' => 'application/pdf'])
            ->assertUnprocessable()->assertJsonValidationErrors('format');
    });

    test('a download without a token is a JSON 401, not a redirect', function () {
        $this->get("/api/report/gaji/{$this->f->mBudi->id}?format=pdf", ['Accept' => 'application/pdf'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    });

    test('the slip holds the kelas lines, the adjustments and the total', function () {
        $slip = app(GajiCalculator::class)->slip($this->f->mBudi->id, 'Budi Santoso', '2026-09');

        expect($slip['mentor'])->toMatchArray(['total_kehadiran' => 2, 'gaji_pokok' => 60000.0, 'bonus' => 50000.0, 'potongan' => 10000.0, 'total' => 100000.0]);

        $html = view('pdf.slip-gaji', $slip + ['dicetak' => '1 Oktober 2026 08:00'])->render();
        expect($html)->toContain('SLIP GAJI', 'Budi Santoso', 'September 2026', 'Matematika 7A', 'Rp30.000', 'Rp60.000', 'Lembur', '-Rp10.000', 'Rp100.000', 'DRAF');
    });

    test('a month without activity gives an empty slip, not an error', function () {
        $slip = app(GajiCalculator::class)->slip($this->f->mRina->id, 'Rina Kartika', '2026-09');

        expect($slip['mentor'])->toMatchArray(['mentor' => 'Rina Kartika', 'total' => 0.0, 'rincian' => []]);
    });

    test('once finalized the slip says FINAL', function () {
        Sanctum::actingAs($this->f->admin);
        $this->postJson('/api/report/gaji', ['bulan' => '2026-09'])->assertCreated();

        $slip = app(GajiCalculator::class)->slip($this->f->mBudi->id, 'Budi Santoso', '2026-09');
        $slip['periode']['finalized_label'] = '1 Oktober 2026';
        expect(view('pdf.slip-gaji', $slip + ['dicetak' => 'x'])->render())->toContain('FINAL', 'Final, difinalisasi 1 Oktober 2026');
    });
});

describe('export laporan gaji', function () {
    test('Excel holds one row per mentor per month and a total row', function () {
        Sanctum::actingAs($this->f->admin);

        $res = $this->get('/api/report/gaji/export?format=xlsx&bulan=2026-09')->assertOk();
        expect($res->headers->get('Content-Disposition'))->toContain('laporan-gaji-2026-09.xlsx');

        $rows = ($this->sheet)($res);
        expect($rows[0][0])->toBe('Laporan Gaji Guru/Mentor')
            ->and($rows[1][0])->toBe('Periode: September 2026')
            ->and(array_slice($rows, 4))->toEqual([
                ['Guru/Mentor', 'Periode', 'Kehadiran', 'Gaji Pokok', 'Bonus', 'Potongan', 'Total', 'Status'],
                ['Budi Santoso', 'September 2026', 2, 60000, 50000, 10000, 100000, 'Draf'],
                ['Sari Dewi', 'September 2026', 1, 40000, 0, 0, 40000, 'Draf'],
                ['Total', null, 3, 100000, 50000, 10000, 140000, null],
            ]);
    });

    test('PDF uses the same filters', function () {
        Sanctum::actingAs($this->f->admin);

        $res = $this->get('/api/report/gaji/export?format=pdf&search=sari')->assertOk();
        expect($res->headers->get('Content-Type'))->toContain('application/pdf');
    });

    test('the format is required and the export is admin-only', function () {
        Sanctum::actingAs($this->f->admin);
        $this->getJson('/api/report/gaji/export')->assertUnprocessable()->assertJsonValidationErrors('format');
        $this->getJson('/api/report/gaji/export?format=docx')->assertUnprocessable()->assertJsonValidationErrors('format');

        Sanctum::actingAs($this->f->budi);
        $this->getJson('/api/report/gaji/export?format=pdf')->assertForbidden();
    });
});

describe('safe exports (review #38)', function () {
    beforeEach(function () {
        Sanctum::actingAs($this->f->admin);
        // A mentor sets their own name; it lands in the admin's workbook.
        $this->f->budi->update(['name' => '=HYPERLINK("http://contoh.test","klik")']);
        $this->f->sari->update(['name' => 'Sari <b>Dewi</b> & Rekan']);
    });

    test('text that looks like a formula is written as text, numbers stay numbers', function () {
        $res = $this->get('/api/report/gaji/export?format=xlsx&bulan=2026-09')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $res->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        expect($sheet->getCell('A6')->getValue())->toBe('=HYPERLINK("http://contoh.test","klik")')
            ->and($sheet->getCell('A6')->getDataType())->toBe('s')
            ->and($sheet->getCell('A6')->isFormula())->toBeFalse()
            ->and($sheet->getCell('D6')->getDataType())->toBe('n')
            ->and($sheet->getCell('G6')->getValue())->toEqual(100000);
    });

    test('the binder is reset after the export and empty cells stay empty', function () {
        $this->get('/api/report/gaji/export?format=xlsx&bulan=2026-09')->assertOk()->streamedContent();

        expect(Cell::getValueBinder())->toBeInstanceOf(DefaultValueBinder::class)
            ->not->toBeInstanceOf(TabelExport::class);
    });

    test('names with < and & are escaped in the PDFs', function () {
        $slip = app(GajiCalculator::class)->slip($this->f->mSari->id, 'Sari <b>Dewi</b> & Rekan', '2026-09');
        expect(view('pdf.slip-gaji', $slip + ['dicetak' => 'x'])->render())
            ->toContain('Sari &lt;b&gt;Dewi&lt;/b&gt; &amp; Rekan')
            ->not->toContain('<b>Dewi</b>');

        $this->get("/api/report/gaji/{$this->f->mSari->id}?format=pdf&bulan=2026-09")->assertOk();
        $this->get('/api/report/gaji/export?format=pdf&bulan=2026-09')->assertOk();
    });

    test('dompdf cannot fetch remote files or run PHP', function () {
        $options = Laporan::pdf('pdf.tabel', ['judul' => 'x', 'keterangan' => [], 'headings' => [], 'rows' => [], 'total' => null, 'numeric' => []])
            ->getDomPDF()->getOptions();

        expect($options->getIsRemoteEnabled())->toBeFalse()
            ->and($options->getIsPhpEnabled())->toBeFalse()
            ->and($options->getChroot())->toBe([resource_path('views')]);
    });

    test('an export is refused beyond its row limit instead of rendering without end', function () {
        $rows = array_fill(0, Laporan::MAX_ROWS['pdf'] + 1, ['a', 1]);

        expect(fn () => Laporan::table('pdf', 'x', 'X', [], ['A', 'B'], $rows))
            ->toThrow(ValidationException::class, 'lebih dari 1000 baris');
        expect(Laporan::limit('pdf'))->toBe(1001)->and(Laporan::limit('xlsx'))->toBe(20001);
    });
});

describe('a finalized month after its sources change (review #37/#38)', function () {
    beforeEach(function () {
        Sanctum::actingAs($this->f->admin);
        $this->postJson('/api/report/gaji', ['bulan' => '2026-09'])->assertCreated();
        $this->putJson("/api/aturan-gaji/{$this->f->aturan7a->id}", ['tarif' => 35000])->assertOk();
        $this->putJson("/api/kelas/{$this->f->k7a->id}", ['mentor_id' => $this->f->mSari->id])->assertOk();
    });

    test('the slip keeps the stored amounts', function () {
        $slip = app(GajiCalculator::class)->slip($this->f->mBudi->id, 'Budi Santoso', '2026-09');

        expect($slip['periode']['status'])->toBe('final')
            ->and($slip['mentor'])->toMatchArray(['gaji_pokok' => 60000.0, 'total' => 100000.0])
            ->and($slip['mentor']['rincian'][0])->toMatchArray(['kelas' => 'Matematika 7A', 'tarif' => 30000.0, 'nominal' => 60000.0]);
    });

    test('the export keeps the stored amounts', function () {
        $rows = ($this->sheet)($this->get('/api/report/gaji/export?format=xlsx&bulan=2026-09')->assertOk());

        expect(array_slice($rows, 5))->toEqual([
            ['Budi Santoso', 'September 2026', 2, 60000, 50000, 10000, 100000, 'Final'],
            ['Sari Dewi', 'September 2026', 1, 40000, 0, 0, 40000, 'Final'],
            ['Total', null, 3, 100000, 50000, 10000, 140000, null],
        ]);
    });
});
