<?php

use App\Models\Notification;
use App\Models\PenyesuaianGaji;
use App\Models\PeriodeGaji;
use App\Models\ReportGaji;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

test('a month cannot be finalized twice, nor before it starts', function () {
    ($this->finalize)()->assertCreated();
    ($this->finalize)()->assertUnprocessable()->assertJsonValidationErrors(['bulan' => 'sudah difinalisasi']);

    ($this->finalize)(['bulan' => now()->addMonth()->format('Y-m')])
        ->assertUnprocessable()->assertJsonValidationErrors(['bulan' => 'belum berjalan']);

    expect(PeriodeGaji::count())->toBe(1);
});

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
