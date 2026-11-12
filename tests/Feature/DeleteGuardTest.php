<?php

use App\Models\Absensi;
use App\Models\AturanGaji;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Mentor;
use App\Models\Siswa;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

test('a siswa with absensi cannot be deleted', function () {
    $andi = $this->f->siswa['Andi'];

    $this->deleteJson("/api/siswa/{$andi->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['siswa' => 'sudah memiliki catatan absensi']);

    expect(Siswa::find($andi->id))->not->toBeNull()
        ->and(Absensi::where('siswa_id', $andi->id)->count())->toBe(2);
});

test('a siswa without absensi can be deleted', function () {
    $this->deleteJson("/api/siswa/{$this->f->siswa['Gita']->id}")->assertOk();

    expect(Siswa::find($this->f->siswa['Gita']->id))->toBeNull();
});

describe('database-level guards (review of #21)', function () {
    test('the database itself refuses deleting referenced rows', function (string $what) {
        $f = $this->f;
        $delete = match ($what) {
            'jadwal with absensi' => fn () => DB::table('jadwal')->where('id', $f->jIsi->id)->delete(),
            'siswa with absensi' => fn () => DB::table('siswa')->where('id', $f->siswa['Andi']->id)->delete(),
            'kelas with jadwal' => fn () => DB::table('kelas')->where('id', $f->k7a->id)->delete(),
            'mentor with kelas' => fn () => DB::table('mentor')->where('id', $f->mBudi->id)->delete(),
        };

        expect($delete)->toThrow(QueryException::class);
    })->with(['jadwal with absensi', 'siswa with absensi', 'kelas with jadwal', 'mentor with kelas']);

    test('the foreign keys go back to nullOnDelete on rollback and forward again', function () {
        $migration = require database_path('migrations/2026_11_12_000100_restrict_deleting_referenced_rows.php');
        $migration->down();
        DB::table('jadwal')->where('id', $this->f->jDepan->id)->delete();
        $migration->up();

        expect(fn () => DB::table('jadwal')->where('id', $this->f->jIsi->id)->delete())->toThrow(QueryException::class);
    })->skip(fn () => DB::connection()->getDriverName() === 'sqlite', 'SQLite rebuilds the table inside the test transaction, where foreign keys cannot be re-enabled; run on PostgreSQL');
});

describe('updates cannot bypass the guards (review of #21)', function () {
    test('an aturan gaji whose kelas has kehadiran cannot move to another kelas', function () {
        $kosong = Kelas::create(['nama' => 'Kimia 9C', 'tingkat' => 'SMP', 'mentor_id' => $this->f->mSari->id]);

        $this->putJson("/api/aturan-gaji/{$this->f->aturan7a->id}", ['kelas_id' => $kosong->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['kelas_id' => 'sudah memiliki kehadiran']);
        $this->putJson("/api/aturan-gaji/{$this->f->aturan7b->id}", ['kelas_id' => $kosong->id])->assertOk();

        expect($this->f->aturan7a->fresh()->kelas_id)->toBe($this->f->k7a->id);
    });

    test('a jadwal with absensi cannot move to another kelas, an empty one can', function () {
        $this->putJson("/api/jadwal/{$this->f->jIsi->id}", ['kelas_id' => $this->f->k7b->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['kelas_id' => 'sudah memiliki catatan absensi']);
        $this->putJson("/api/jadwal/{$this->f->jDepan->id}", ['kelas_id' => $this->f->k7b->id])->assertOk();

        expect($this->f->jIsi->fresh()->kelas_id)->toBe($this->f->k7a->id);
    });

    test('a mentor with a finalized salary record cannot be deleted', function () {
        DB::table('report_gaji')->insert(['mentor_id' => $this->f->mRina->id, 'bulan' => '2026-09-01', 'jumlah_kehadiran' => 0, 'total_gaji' => 0]);

        $this->deleteJson("/api/mentor/{$this->f->mRina->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mentor' => 'catatan gaji yang difinalisasi']);

        expect(Mentor::find($this->f->mRina->id))->not->toBeNull();
    });
});
