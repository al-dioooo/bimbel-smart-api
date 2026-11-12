<?php

use App\Models\AturanGaji;
use App\Models\Kelas;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BimbelFixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->f = BimbelFixture::make();
    Sanctum::actingAs($this->f->admin);
});

describe('aturan gaji (D2)', function () {
    test('a second aturan for the same kelas is refused and the report is unchanged', function () {
        $before = $this->getJson('/api/report/gaji?paginate=false')->json('data');

        $this->postJson('/api/aturan-gaji', ['kelas_id' => $this->f->k7a->id, 'tarif' => 35000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['kelas_id' => 'sudah memiliki aturan gaji']);

        expect(AturanGaji::where('kelas_id', $this->f->k7a->id)->count())->toBe(1)
            ->and($this->getJson('/api/report/gaji?paginate=false')->json('data'))->toBe($before);
    });

    test('an aturan for a kelas without one is accepted', function () {
        $kelas = Kelas::create(['nama' => 'Kimia 9C', 'tingkat' => 'SMP', 'mentor_id' => $this->f->mSari->id]);

        $this->postJson('/api/aturan-gaji', ['kelas_id' => $kelas->id, 'tarif' => 45000])->assertCreated();
    });

    test('an aturan can be updated in place but not moved onto a kelas that has one', function () {
        $this->putJson("/api/aturan-gaji/{$this->f->aturan7a->id}", ['kelas_id' => $this->f->k7a->id, 'tarif' => 32000])->assertOk();
        $this->putJson("/api/aturan-gaji/{$this->f->aturan7a->id}", ['kelas_id' => $this->f->k8b->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('kelas_id');

        expect($this->f->aturan7a->fresh()->kelas_id)->toBe($this->f->k7a->id);
    });

    test('the database refuses a duplicate even without the API', function () {
        expect(fn () => DB::table('aturan_gaji')->insert(['kelas_id' => $this->f->k7a->id, 'tarif' => 1]))
            ->toThrow(QueryException::class);
    });

    test('the migration lists existing duplicates instead of deleting them', function () {
        $migration = require database_path('migrations/2026_11_12_000001_add_unique_kelas_id_to_aturan_gaji_table.php');
        $migration->down();
        $extra = DB::table('aturan_gaji')->insertGetId(['kelas_id' => $this->f->k7a->id, 'tarif' => 35000]);

        expect(fn () => $migration->up())->toThrow(RuntimeException::class, "kelas_id {$this->f->k7a->id}: id {$this->f->aturan7a->id} (tarif 30000");
        expect(DB::table('aturan_gaji')->where('kelas_id', $this->f->k7a->id)->pluck('id')->all())
            ->toBe([$this->f->aturan7a->id, $extra]);

        DB::table('aturan_gaji')->delete($extra);
        $migration->up();
        expect(fn () => DB::table('aturan_gaji')->insert(['kelas_id' => $this->f->k7a->id, 'tarif' => 1]))
            ->toThrow(QueryException::class);
    });
});

describe('kelas (D20)', function () {
    test('a kelas with the same nama and tingkat is refused, whatever the case or spacing', function (string $nama, string $tingkat) {
        $n = Kelas::count();

        $this->postJson('/api/kelas', ['nama' => $nama, 'tingkat' => $tingkat, 'mentor_id' => $this->f->mBudi->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nama' => 'sudah terdaftar']);

        expect(Kelas::count())->toBe($n);
    })->with([
        ['Matematika 7A', 'SMP'],
        ['matematika 7a', 'smp'],
        ['  Matematika 7A ', 'SMP'],
    ]);

    test('the same nama at another tingkat is a different kelas', function () {
        $this->postJson('/api/kelas', ['nama' => 'Matematika 7A', 'tingkat' => 'SMA', 'mentor_id' => $this->f->mBudi->id])
            ->assertCreated();
    });

    test('an update may keep its own nama but not take another kelas\'s', function () {
        $this->putJson("/api/kelas/{$this->f->k7a->id}", ['nama' => 'Matematika 7A', 'tingkat' => 'SMP'])->assertOk();
        $this->putJson("/api/kelas/{$this->f->k7b->id}", ['nama' => 'Matematika 7A'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nama' => 'sudah terdaftar']);

        expect($this->f->k7b->fresh()->nama)->toBe('Matematika 7B');
    });
});
