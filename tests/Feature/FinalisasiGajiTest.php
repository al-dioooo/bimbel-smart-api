<?php

use App\Models\Absensi;
use App\Models\AturanGaji;
use App\Models\Kelas;
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

test('an incomplete session needs the confirmation too, by the reminders\' rule (round 2 review)', function () {
    // Andi and Bela joined in January; on 14 September only Andi is recorded.
    $this->f->k7a->siswa()->update(['tanggal_bergabung' => '2026-01-05 09:00:00']);
    $this->f->jadwal($this->f->k7a, '2026-09-14', ['Andi' => 'h']);

    expect($this->getJson('/api/report/gaji/periode/2026-09')->json('data.jadwal_belum_diabsen'))->toBe(1);
    ($this->finalize)()->assertUnprocessable()->assertJsonValidationErrors(['lanjutkan' => 'Terdapat 1 sesi absensi yang belum terisi']);

    // A student who joined after the session does not make it incomplete.
    $this->f->siswa['Bela']->update(['tanggal_bergabung' => '2026-09-20 09:00:00']);
    expect($this->getJson('/api/report/gaji/periode/2026-09')->json('data.jadwal_belum_diabsen'))->toBe(0);
    ($this->finalize)()->assertCreated();
});

test('the gaji detail totals read a finalized month as stored', function () {
    ($this->finalize)()->assertCreated();
    $this->putJson("/api/kelas/{$this->f->k7a->id}", ['mentor_id' => $this->f->mSari->id])->assertOk();

    $this->getJson("/api/report/gaji/{$this->f->mBudi->id}?bulan=2026-09")
        ->assertJsonPath('total.total', fn ($v) => (float) $v === 110000.0)
        ->assertJsonPath('total.bonus', fn ($v) => (float) $v === 50000.0);
});

test('the tarif of an aturan whose kelas was deleted can still be edited (round 2 review)', function () {
    $kelas = Kelas::create(['nama' => 'Kimia 9C', 'tingkat' => 'SMP', 'mentor_id' => $this->f->mSari->id]);
    $aturan = AturanGaji::create(['kelas_id' => $kelas->id, 'tarif' => 45000]);
    $this->deleteJson("/api/kelas/{$kelas->id}")->assertOk();
    expect($aturan->fresh()->kelas_id)->toBeNull();

    $this->putJson("/api/aturan-gaji/{$aturan->id}", ['tarif' => 50000])
        ->assertOk()
        ->assertJsonPath('bulan_terdampak', []);
    expect($aturan->fresh()->tarif)->toBe(50000.0);

    // Given a kelas again, the response lists that kelas's open months.
    $this->putJson("/api/aturan-gaji/{$aturan->id}", ['kelas_id' => $this->f->k7b->id])->assertUnprocessable(); // K-7B has an aturan
    $baru = Kelas::create(['nama' => 'Kimia 9D', 'tingkat' => 'SMP', 'mentor_id' => $this->f->mSari->id]);
    $this->putJson("/api/aturan-gaji/{$aturan->id}", ['kelas_id' => $baru->id])->assertOk()->assertJsonPath('bulan_terdampak', []);
});

test('only admins preview and finalize', function () {
    Sanctum::actingAs($this->f->budi);

    $this->getJson('/api/report/gaji/periode/2026-09')->assertForbidden();
    ($this->finalize)()->assertForbidden();
});

describe('after finalization (UC-10 step 9)', function () {
    beforeEach(function () {
        ($this->finalize)()->assertCreated();
        $this->andi = Absensi::where('jadwal_id', $this->f->jSep7a->id)->where('siswa_id', $this->f->siswa['Andi']->id)->first();
    });

    test('absensi of the month cannot be added, edited or deleted', function () {
        $citra = $this->f->k7a->siswa()->create(['nama' => 'Citra']);

        $this->postJson('/api/absensi', ['jadwal_id' => $this->f->jSep7a->id, 'absensi' => [['siswa_id' => $citra->id, 'status' => 'H']]])
            ->assertUnprocessable()->assertJsonValidationErrors(['jadwal_id' => 'Periode gaji September 2026 sudah difinalisasi']);
        $this->putJson("/api/absensi/{$this->andi->id}", ['status' => 'A'])->assertUnprocessable()->assertJsonValidationErrors('absensi');
        $this->deleteJson("/api/absensi/{$this->andi->id}")->assertUnprocessable()->assertJsonValidationErrors('absensi');

        expect($this->andi->fresh()->status)->toBe('h');
    });

    test('a jadwal of the month cannot be edited or deleted, and none can be moved into it', function () {
        $empty = $this->f->jadwal($this->f->k7b, '2026-09-21');

        $this->putJson("/api/jadwal/{$this->f->jSep7a->id}", ['materi' => 'Ubah'])->assertUnprocessable()->assertJsonValidationErrors('jadwal');
        $this->deleteJson("/api/jadwal/{$empty->id}")->assertUnprocessable()->assertJsonValidationErrors('jadwal');
        $this->putJson("/api/jadwal/{$this->f->jDepan->id}", ['tanggal' => '2026-09-22'])->assertUnprocessable()->assertJsonValidationErrors('tanggal');
    });

    test('a tarif change leaves the finalized month as stored and reprices the open months (review #37)', function () {
        $snapshot = fn () => [
            'list' => ($this->rekapSep)(),
            'range' => collect($this->getJson('/api/report/gaji?paginate=false&from=2026-09-07&to=2026-09-07')->json('data'))
                ->map(fn ($r) => [$r['mentor'], (float) $r['nominal'], $r['status']])->sortBy(0)->values()->all(),
            'detail' => collect($this->getJson("/api/report/gaji/{$this->f->mBudi->id}?paginate=false&bulan=2026-09")->json('data'))
                ->map(fn ($r) => [$r['kelas'], (float) $r['tarif'], (float) $r['nominal'], $r['status']])->all(),
            'preview' => $this->getJson('/api/report/gaji/periode/2026-09')->json('data.total.total'),
            'dashboard' => $this->getJson('/api/dashboard/stats?from=2026-09-01&to=2026-09-30')->json('data.penghasilan'),
        ];
        $before = $snapshot();
        $nov = fn () => collect($this->getJson('/api/report/gaji?paginate=false&bulan=2026-11')->json('data'))->pluck('nominal', 'mentor')->map(fn ($n) => (float) $n)->sortKeys()->all();
        expect($nov())->toBe(['Budi Santoso' => 30000.0, 'Sari Dewi' => 0.0]);

        $this->putJson("/api/aturan-gaji/{$this->f->aturan7a->id}", ['tarif' => 35000])
            ->assertOk()
            ->assertJsonPath('bulan_terdampak', ['2026-11']);

        expect($snapshot())->toBe($before)
            ->and($before['range'])->toBe([['Budi Santoso', 110000.0, 'final'], ['Sari Dewi', 40000.0, 'final']])
            ->and($nov())->toBe(['Budi Santoso' => 35000.0, 'Sari Dewi' => 0.0]);
    });

    test('a tarif of a kelas whose kehadiran are all finalized affects no open month', function () {
        $this->putJson("/api/aturan-gaji/{$this->f->aturan8b->id}", ['tarif' => 45000])
            ->assertOk()
            ->assertJsonPath('bulan_terdampak', []);
    });

    test('the tarif still cannot be deleted while the kelas has kehadiran', function () {
        $this->deleteJson("/api/aturan-gaji/{$this->f->aturan8b->id}")->assertUnprocessable();
    });

    test('no jadwal can be added to the month', function () {
        $this->postJson('/api/jadwal', [
            'kelas_id' => $this->f->k7b->id, 'tanggal' => '2026-09-28', 'waktu_mulai' => '08:00', 'waktu_selesai' => '09:00', 'materi' => 'Susulan',
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'tanggal' => 'Periode gaji September 2026 sudah difinalisasi, sehingga jadwal tidak dapat ditambahkan pada periode tersebut.',
        ]);

        $this->postJson('/api/jadwal', [
            'kelas_id' => $this->f->k7b->id, 'tanggal' => '2026-10-28', 'waktu_mulai' => '08:00', 'waktu_selesai' => '09:00', 'materi' => 'Susulan',
        ])->assertCreated();
    });

    test('the mentor is not reminded of sessions in the month', function () {
        $this->f->jadwal($this->f->k7a, '2026-09-14');
        Sanctum::actingAs($this->f->budi);

        expect(collect($this->getJson('/api/dashboard/stats')->json('data.absensi_belum_diisi'))->pluck('tanggal')->all())
            ->toBe([BimbelFixture::day(-1)]);
    });

    test('adjustments of the month cannot be added, changed or removed', function () {
        $bonus = PenyesuaianGaji::sole();

        $this->postJson('/api/penyesuaian-gaji', ['mentor_id' => $this->f->mSari->id, 'bulan' => '2026-09', 'jenis' => 'bonus', 'nominal' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('bulan');
        $this->postJson('/api/aturan-gaji', ['mentor_id' => $this->f->mSari->id, 'bulan' => '2026-09', 'jenis' => 'potongan', 'nominal' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('bulan');
        $this->putJson("/api/penyesuaian-gaji/{$bonus->id}", ['nominal' => 1])->assertUnprocessable();
        $this->deleteJson("/api/penyesuaian-gaji/{$bonus->id}")->assertUnprocessable();

        $this->postJson('/api/penyesuaian-gaji', ['mentor_id' => $this->f->mSari->id, 'bulan' => '2026-10', 'jenis' => 'bonus', 'nominal' => 1])->assertCreated();
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

    test('a range that only cuts through the finalized month shows the whole stored month', function () {
        $rows = collect($this->getJson('/api/report/gaji?paginate=false&from=2026-09-07&to=2026-09-07')->json('data'));

        expect($rows->map(fn ($r) => [$r['mentor'], (float) $r['nominal'], $r['status']])->sortBy(0)->values()->all())
            ->toBe([['Budi Santoso', 110000.0, 'final'], ['Sari Dewi', 40000.0, 'final']]);
    });

    test('other months stay open', function () {
        $this->postJson('/api/absensi', ['jadwal_id' => $this->f->jLalu->id, 'absensi' => [['siswa_id' => $this->f->siswa['Andi']->id, 'status' => 'H']]])
            ->assertCreated();
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

test('the dashboard earnings are the salary: pokok + bonus - potongan (review #37)', function () {
    PenyesuaianGaji::create(['mentor_id' => $this->f->mSari->id, 'periode' => '2026-09-01', 'jenis' => 'potongan', 'nominal' => 5000]);
    $sep = '/api/dashboard/stats?from=2026-09-01&to=2026-09-30';

    expect($this->getJson($sep)->json('data.penghasilan'))->toEqual(145000);
    Sanctum::actingAs($this->f->budi);
    expect($this->getJson($sep)->json('data.penghasilan'))->toEqual(110000);
});
