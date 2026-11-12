<?php

use App\Models\Notification;
use App\Models\PenyesuaianGaji;
use App\Models\PeriodeGaji;
use App\Models\ReportGaji;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BimbelFixture;

uses(RefreshDatabase::class);

/**
 * September 2026 in the fixture: Budi 2 × 30.000 (K-7A, 7 Sep), Sari
 * 1 × 40.000 (K-8B, 8 Sep). Budi also gets a 50.000 bonus here.
 */
beforeEach(function () {
    $this->f = BimbelFixture::make();
    PenyesuaianGaji::create(['mentor_id' => $this->f->mBudi->id, 'periode' => '2026-09-01', 'jenis' => 'bonus', 'nominal' => 50000, 'keterangan' => 'Lembur']);
    Sanctum::actingAs($this->f->admin);

    $this->finalize = fn (array $body = []) => $this->postJson('/api/report/gaji', $body + ['bulan' => '2026-09']);
    $this->rekapSep = fn () => collect($this->getJson('/api/report/gaji?paginate=false&bulan=2026-09')->json('data'))
        ->mapWithKeys(fn ($r) => [$r['mentor'] => [(float) $r['nominal'], $r['status']]])->sortKeys()->all();
});

test('the preview shows the draft per mentor with sessions, tarif and adjustments (UC-10 steps 2–7)', function () {
    $data = $this->getJson('/api/report/gaji/periode/2026-09')->assertOk()->json('data');

    expect($data['status'])->toBe('draft')
        ->and($data['label'])->toBe('September 2026')
        ->and($data['jadwal_belum_diabsen'])->toBe(0)
        ->and($data['total'])->toMatchArray(['jumlah_mentor' => 2, 'total_kehadiran' => 3, 'total' => 150000.0])
        ->and($data['mentor'][0])->toMatchArray([
            'mentor' => 'Budi Santoso', 'total_kehadiran' => 2, 'gaji_pokok' => 60000.0, 'bonus' => 50000.0, 'potongan' => 0.0, 'total' => 110000.0,
            'rincian' => [['kelas_id' => $this->f->k7a->id, 'kelas' => 'Matematika 7A', 'tarif' => 30000.0, 'jumlah_kehadiran' => 2, 'nominal' => 60000.0]],
        ])
        ->and($data['mentor'][0]['penyesuaian'][0])->toMatchArray(['jenis' => 'bonus', 'nominal' => 50000.0, 'keterangan' => 'Lembur']);
});

test('finalizing stores the final report per mentor, locks the month and notifies the mentors (UC-10 steps 8–10)', function () {
    ($this->finalize)()
        ->assertCreated()
        ->assertJsonPath('message', 'Proses gaji untuk periode September 2026 telah berhasil difinalisasi.')
        ->assertJsonPath('data.status', 'final');

    $periode = PeriodeGaji::sole();
    expect($periode->total_gaji)->toBe(150000.0)
        ->and($periode->finalized_by)->toBe($this->f->admin->id)
        ->and(ReportGaji::orderBy('mentor_nama')->get()->map->only('mentor_nama', 'jumlah_kehadiran', 'gaji_pokok', 'bonus', 'total_gaji')->all())->toBe([
            ['mentor_nama' => 'Budi Santoso', 'jumlah_kehadiran' => 2, 'gaji_pokok' => 60000.0, 'bonus' => 50000.0, 'total_gaji' => 110000.0],
            ['mentor_nama' => 'Sari Dewi', 'jumlah_kehadiran' => 1, 'gaji_pokok' => 40000.0, 'bonus' => 0.0, 'total_gaji' => 40000.0],
        ])
        ->and(Notification::where('user_id', $this->f->budi->id)->value('title'))->toBe('Slip gaji September 2026 tersedia')
        ->and(($this->rekapSep)())->toBe(['Budi Santoso' => [110000.0, 'final'], 'Sari Dewi' => [40000.0, 'final']]);

    $this->getJson('/api/report/gaji/periode/2026-09')->assertJsonPath('data.status', 'final')->assertJsonPath('data.finalized_by', 'Admin');
});

test('a month cannot be finalized twice, nor before it has ended', function () {
    ($this->finalize)()->assertCreated();
    ($this->finalize)()->assertUnprocessable()->assertJsonValidationErrors(['bulan' => 'sudah difinalisasi']);

    // The current month still has sessions to record (J-DEPAN, in 3 days).
    $bulanIni = now()->format('Y-m');
    ($this->finalize)(['bulan' => $bulanIni, 'lanjutkan' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['bulan' => 'belum berakhir. Finalisasi dapat dilakukan mulai 1 '.now()->addMonthNoOverflow()->locale('id')->translatedFormat('F Y')]);
    ($this->finalize)(['bulan' => now()->addMonth()->format('Y-m')])
        ->assertUnprocessable()->assertJsonValidationErrors(['bulan' => 'belum berakhir']);

    expect(PeriodeGaji::count())->toBe(1);
});

test('the last day of a month is still too early, the first day of the next is not', function () {
    $this->travelTo('2026-10-31 23:59:00');
    ($this->finalize)()->assertCreated();

    $this->travelTo('2026-11-30 23:59:00');
    ($this->finalize)(['bulan' => '2026-11', 'lanjutkan' => true])->assertUnprocessable()->assertJsonValidationErrors(['bulan' => 'belum berakhir']);
});

test('a month with no salary data is not finalized', function () {
    ($this->finalize)(['bulan' => '2026-08'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['bulan' => 'Tidak ada data gaji pada periode Agustus 2026']);

    expect(PeriodeGaji::count())->toBe(0);
});

test('a month with a negative salary is not finalized', function () {
    // Written directly: the API refuses such a potongan since review #35.
    PenyesuaianGaji::create(['mentor_id' => $this->f->mSari->id, 'periode' => '2026-09-01', 'jenis' => 'potongan', 'nominal' => 45000]);

    ($this->finalize)()->assertUnprocessable()->assertJsonValidationErrors(['bulan' => 'Total gaji Sari Dewi pada September 2026 negatif']);
    expect(PeriodeGaji::count())->toBe(0);
});

test('a previewed total that no longer matches is refused', function () {
    ($this->finalize)(['total' => 140000])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['total' => 'berubah sejak pratinjau (total sekarang Rp150.000)']);

    ($this->finalize)(['total' => 150000])->assertCreated();
});

test('amounts are stored rounded to two decimals in decimal columns', function () {
    PenyesuaianGaji::create(['mentor_id' => $this->f->mSari->id, 'periode' => '2026-09-01', 'jenis' => 'bonus', 'nominal' => 0.1]);
    PenyesuaianGaji::create(['mentor_id' => $this->f->mSari->id, 'periode' => '2026-09-01', 'jenis' => 'bonus', 'nominal' => 0.2]);

    ($this->finalize)()->assertCreated();

    expect(ReportGaji::where('mentor_id', $this->f->mSari->id)->value('total_gaji'))->toBe(40000.3)
        ->and(PeriodeGaji::sole()->total_gaji)->toBe(150000.3)
        ->and(Schema::getColumnType('report_gaji', 'total_gaji'))->toBeIn(['numeric', 'decimal']);
});

test('the migration turns total_gaji into decimal and back', function () {
    $migration = require database_path('migrations/2026_11_12_000006_add_snapshot_columns_to_report_gaji_table.php');

    $migration->down();
    expect(Schema::getColumnType('report_gaji', 'total_gaji'))->toBeIn(['float8', 'double', 'float', 'real']);

    $migration->up();
    expect(Schema::getColumnType('report_gaji', 'total_gaji'))->toBeIn(['numeric', 'decimal']);
})->skip(fn () => DB::connection()->getDriverName() === 'sqlite', 'SQLite rebuilds the table; checked on PostgreSQL.');

test('sessions without absensi need an explicit confirmation (UC-10 3a)', function () {
    $this->f->jadwal($this->f->k7a, '2026-09-14');

    expect($this->getJson('/api/report/gaji/periode/2026-09')->json('data.jadwal_belum_diabsen'))->toBe(1);
    ($this->finalize)()->assertUnprocessable()->assertJsonValidationErrors(['lanjutkan' => 'Terdapat 1 sesi absensi yang belum terisi']);
    expect(PeriodeGaji::count())->toBe(0);

    ($this->finalize)(['lanjutkan' => true])->assertCreated();
});

test('only admins preview and finalize', function () {
    Sanctum::actingAs($this->f->budi);

    $this->getJson('/api/report/gaji/periode/2026-09')->assertForbidden();
    ($this->finalize)()->assertForbidden();
});

describe('after finalization', function () {
    beforeEach(function () {
        ($this->finalize)()->assertCreated();
    });

    test('the report and the detail read the stored month even if a kelas changes mentor', function () {
        $this->putJson("/api/kelas/{$this->f->k7a->id}", ['mentor_id' => $this->f->mSari->id])->assertOk();

        expect(($this->rekapSep)())->toBe(['Budi Santoso' => [110000.0, 'final'], 'Sari Dewi' => [40000.0, 'final']]);
        $this->getJson("/api/report/gaji/{$this->f->mBudi->id}?paginate=false&bulan=2026-09")
            ->assertJsonPath('data.0.kelas', 'Matematika 7A')
            ->assertJsonPath('data.0.nominal', 60000)
            ->assertJsonPath('data.0.status', 'final');

        // Other months stay live: K-7A's sessions of November now count for Sari.
        $nov = collect($this->getJson('/api/report/gaji?paginate=false&bulan='.now()->format('Y-m'))->json('data'))->pluck('status', 'mentor')->all();
        expect($nov)->toBe(['Sari Dewi' => 'draft']);
    });

    test('a range that only cuts through the finalized month shows live figures for its days', function () {
        $rows = collect($this->getJson('/api/report/gaji?paginate=false&from=2026-09-07&to=2026-09-07')->json('data'));

        expect($rows->map(fn ($r) => [$r['mentor'], (float) $r['nominal'], $r['status']])->all())
            ->toBe([['Budi Santoso', 110000.0, 'draft']]);
    });

});

test('finalize and the writers of a month use the same advisory lock', function () {
    // A second session, as another request would have.
    config(['database.connections.lain' => config('database.connections.pgsql')]);
    $lain = fn (string $sql) => DB::connection('lain')->selectOne($sql)->ok;

    // Held until this test's transaction ends.
    PeriodeGaji::lockForFinalize('2026-09-01');
    expect($lain('SELECT pg_try_advisory_lock_shared(7301, 202609) AS ok'))->toBeFalse()
        ->and($lain('SELECT pg_try_advisory_lock_shared(7301, 202610) AS ok'))->toBeTrue();
    $lain('SELECT pg_advisory_unlock_shared(7301, 202610) AS ok');

    PeriodeGaji::ensureOpen('2026-10-15', 'tanggal', 'jadwal ini');
    expect($lain('SELECT pg_try_advisory_lock(7301, 202610) AS ok'))->toBeFalse()
        ->and($lain('SELECT pg_try_advisory_lock_shared(7301, 202610) AS ok'))->toBeTrue();

    DB::purge('lain');
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'Advisory locks are PostgreSQL only.');
