<?php

use App\Models\AturanGaji;
use App\Models\Kelas;
use App\Models\Mentor;
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

describe('mentor phone number (D20)', function () {
    $body = fn (array $override = []) => array_merge([
        'name' => 'Dewi Lestari', 'username' => 'dewi', 'email' => 'dewi@uji.test', 'password' => 'rahasia-uji8',
    ], $override);

    test('a new mentor may not reuse another mentor\'s kontak', function () use ($body) {
        $this->postJson('/api/mentor', $body(['kontak' => '081234567890']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['kontak' => 'sudah dipakai mentor lain']);

        expect(User::where('username', 'dewi')->exists())->toBeFalse();
    });

    test('a new mentor with a free kontak, or none, is accepted', function () use ($body) {
        $this->postJson('/api/mentor', $body(['kontak' => '081200000000']))->assertCreated();
        $this->postJson('/api/mentor', $body(['username' => 'eka', 'email' => 'eka@uji.test']))->assertCreated();
    });

    test('an update may keep its own kontak but not take another mentor\'s', function () {
        $this->putJson("/api/mentor/{$this->f->mBudi->id}", [
            'name' => 'Budi Santoso', 'username' => 'budi', 'email' => 'budi@uji.test', 'kontak' => '081234567890',
        ])->assertOk();

        $this->putJson("/api/mentor/{$this->f->mSari->id}", [
            'name' => 'Sari Dewi', 'username' => 'sari', 'email' => 'sari@uji.test', 'kontak' => '081234567890',
        ])->assertUnprocessable()->assertJsonValidationErrors('kontak');

        expect($this->f->mSari->fresh()->kontak)->toBe('081298765432');
    });
});

describe('database-level uniqueness for kelas and kontak (review #22)', function () {
    $migration = fn () => require database_path('migrations/2026_11_12_000101_add_unique_kelas_and_kontak_indexes.php');

    test('the database refuses a duplicate kelas, whatever the case or spacing', function () {
        expect(fn () => DB::table('kelas')->insert(['nama' => ' matematika 7a', 'tingkat' => 'smp ']))
            ->toThrow(QueryException::class);
    });

    test('the database refuses a duplicate kontak but allows many mentors without one', function () {
        // In a savepoint, so PostgreSQL can go on after the refused insert.
        expect(fn () => DB::transaction(fn () => DB::table('mentor')->insert(['user_id' => $this->f->admin->id, 'kontak' => '081234567890'])))
            ->toThrow(QueryException::class);

        $none = DB::table('mentor')->whereNull('kontak')->count();
        DB::table('mentor')->whereIn('id', [$this->f->mBudi->id, $this->f->mSari->id])->update(['kontak' => null]);
        expect(DB::table('mentor')->whereNull('kontak')->count())->toBe($none + 2);
    });

    test('a kelas created by a parallel request answers 422, not 500', function () {
        // The parallel request inserts the same kelas after validation passed.
        Kelas::creating(fn () => DB::table('kelas')->insert(['nama' => 'Kimia 9C', 'tingkat' => 'SMP']));

        $this->postJson('/api/kelas', ['nama' => 'Kimia 9C', 'tingkat' => 'SMP', 'mentor_id' => $this->f->mSari->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nama' => 'sudah terdaftar'])
            ->assertJsonMissingPath('error');
    });

    test('a kontak taken by a parallel request answers 422, not 500', function () {
        Mentor::creating(fn () => DB::table('mentor')->insert(['user_id' => $this->f->admin->id, 'kontak' => '081200000001']));

        $this->postJson('/api/mentor', [
            'name' => 'Dewi Lestari', 'username' => 'dewi', 'email' => 'dewi@uji.test', 'password' => 'rahasia-uji8', 'kontak' => '081200000001',
        ])->assertUnprocessable()->assertJsonValidationErrors(['kontak' => 'sudah dipakai mentor lain']);

        expect(User::where('username', 'dewi')->exists())->toBeFalse();
    });

    test('an aturan created by a parallel request answers 422, not 500', function () {
        $kelas = Kelas::create(['nama' => 'Kimia 9C', 'tingkat' => 'SMP', 'mentor_id' => $this->f->mSari->id]);
        AturanGaji::creating(fn () => DB::table('aturan_gaji')->insert(['kelas_id' => $kelas->id, 'tarif' => 1]));

        $this->postJson('/api/aturan-gaji', ['kelas_id' => $kelas->id, 'tarif' => 45000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['kelas_id' => 'sudah memiliki aturan gaji']);
    });

    test('the migration lists duplicate kelas and kontak instead of changing them', function () use ($migration) {
        $m = $migration();
        $m->down();
        $kelas = DB::table('kelas')->insertGetId(['nama' => 'matematika 7a ', 'tingkat' => 'SMP']);
        $mentor = DB::table('mentor')->insertGetId(['user_id' => $this->f->admin->id, 'kontak' => '081234567890']);

        expect(fn () => $m->up())->toThrow(RuntimeException::class, "id {$this->f->k7a->id} (\"Matematika 7A\" SMP), id {$kelas} (\"matematika 7a \" SMP)");
        expect(fn () => $m->up())->toThrow(RuntimeException::class, "mentor kontak 081234567890: mentor id {$this->f->mBudi->id}, {$mentor}");
        expect(DB::table('kelas')->where('id', $kelas)->exists())->toBeTrue()
            ->and(DB::table('mentor')->where('id', $mentor)->value('kontak'))->toBe('081234567890');

        DB::table('kelas')->delete($kelas);
        DB::table('mentor')->delete($mentor);
        $m->up();
        expect(fn () => DB::table('kelas')->insert(['nama' => 'MATEMATIKA 7A', 'tingkat' => 'SMP']))->toThrow(QueryException::class);
    });

    test('the migration turns an empty kontak into none so several can exist', function () use ($migration) {
        $m = $migration();
        $m->down();
        DB::table('mentor')->whereIn('id', [$this->f->mBudi->id, $this->f->mSari->id])->update(['kontak' => '']);

        $m->up();

        expect(DB::table('mentor')->whereIn('id', [$this->f->mBudi->id, $this->f->mSari->id])->pluck('kontak')->all())->toBe([null, null]);
    });

    test('an update that leaves nama and tingkat as they are is not checked', function () use ($migration) {
        // A duplicate that predates the rule (index dropped to recreate one).
        $m = $migration();
        $m->down();
        $dup = Kelas::create(['nama' => 'matematika 7a', 'tingkat' => 'SMP', 'mentor_id' => $this->f->mBudi->id]);

        $this->putJson("/api/kelas/{$dup->id}", ['nama' => 'matematika 7a', 'tingkat' => 'SMP', 'mentor_id' => $this->f->mSari->id])->assertOk();
        $this->putJson("/api/kelas/{$dup->id}", ['mentor_id' => $this->f->mBudi->id])->assertOk();
        $this->putJson("/api/kelas/{$dup->id}", ['nama' => 'Matematika 7A'])->assertOk();
        $this->putJson("/api/kelas/{$this->f->k7b->id}", ['nama' => 'Matematika 7A'])
            ->assertUnprocessable()->assertJsonValidationErrors(['nama' => 'sudah terdaftar']);
    });

    test('a full-form mentor update that resends an unchanged kontak is not checked', function () use ($migration) {
        $m = $migration();
        $m->down();
        DB::table('mentor')->where('id', $this->f->mSari->id)->update(['kontak' => '081234567890']);

        $this->putJson("/api/mentor/{$this->f->mSari->id}", [
            'name' => 'Sari Dewi Baru', 'username' => 'sari', 'email' => 'sari@uji.test', 'kontak' => '081234567890',
        ])->assertOk();
        expect($this->f->mSari->fresh()->user->name)->toBe('Sari Dewi Baru');
    });
});
