<?php

use App\Models\Absensi;
use App\Models\AturanGaji;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Mentor;
use App\Models\Notification;
use App\Models\PengajuanJadwal;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Two mentors (A, B), each with one kelas, one siswa, a past and a future
 * jadwal, attendance, requests and a notification — so any leak across
 * mentors shows up as B's record in A's response.
 */
beforeEach(function () {
    Model::unguard();

    $this->admin = User::create(['name' => 'Admin', 'username' => 'admin', 'email' => 'admin@x.test', 'password' => 'secret123', 'role' => 1]);

    foreach (['a', 'b'] as $key) {
        $user = User::create(['name' => "Mentor $key", 'username' => "mentor-$key", 'email' => "$key@x.test", 'password' => 'secret123', 'role' => 0]);
        $mentor = Mentor::create(['user_id' => $user->id]);
        $kelas = Kelas::create(['mentor_id' => $mentor->id, 'nama' => "Kelas $key", 'tingkat' => 'SMA']);
        AturanGaji::create(['kelas_id' => $kelas->id, 'tarif' => 50000]);
        $siswa = Siswa::create(['nama' => "Siswa $key", 'kelas_id' => $kelas->id]);
        $past = Jadwal::create(['kelas_id' => $kelas->id, 'tanggal' => now()->subDay()->toDateString(), 'waktu_mulai' => '10:00:00', 'waktu_selesai' => '11:00:00']);
        $future = Jadwal::create(['kelas_id' => $kelas->id, 'tanggal' => now()->addDays(3)->toDateString(), 'waktu_mulai' => '10:00:00', 'waktu_selesai' => '11:00:00']);
        $absensi = Absensi::create(['jadwal_id' => $past->id, 'siswa_id' => $siswa->id, 'tanggal' => $past->tanggal, 'status' => 'h']);
        $pending = PengajuanJadwal::create([
            'jadwal_id' => $future->id, 'status' => 'pending', 'alasan' => 'x',
            'tanggal_sebelum' => $future->tanggal, 'tanggal_sesudah' => $future->tanggal,
            'waktu_mulai_sebelum' => '10:00:00', 'waktu_mulai_sesudah' => '12:00:00',
            'waktu_selesai_sebelum' => '11:00:00', 'waktu_selesai_sesudah' => '13:00:00',
        ]);
        $decided = $pending->replicate()->fill(['status' => 'diterima']);
        $decided->save();
        $notification = Notification::create(['user_id' => $user->id, 'title' => "Hi $key", 'message' => 'm']);

        $this->{$key} = (object) compact('user', 'mentor', 'kelas', 'siswa', 'past', 'future', 'absensi', 'pending', 'decided', 'notification');
    }

    Model::reguard();
});

/** Rows from either a paginated or a flat (paginate=false) list response. */
function rows($response): array
{
    $data = $response->json('data');

    return $data['data'] ?? $data;
}

test('mentors are refused every admin-only endpoint', function (string $method, string $uri) {
    Sanctum::actingAs($this->a->user);

    $uri = strtr($uri, [
        '{kelas}' => $this->a->kelas->id,
        '{siswa}' => $this->a->siswa->id,
        '{jadwal}' => $this->a->past->id,
        '{absensi}' => $this->a->absensi->id,
        '{pengajuan}' => $this->a->pending->id,
        '{mentor}' => $this->a->mentor->id,
    ]);

    $this->json($method, "/api$uri")->assertForbidden();
})->with([
    ['GET', '/mentor'],
    ['GET', '/mentor/{mentor}'],
    ['POST', '/mentor'],
    ['POST', '/kelas'],
    ['PUT', '/kelas/{kelas}'],
    ['DELETE', '/kelas/{kelas}'],
    ['POST', '/siswa'],
    ['PUT', '/siswa/{siswa}'],
    ['DELETE', '/siswa/{siswa}'],
    ['POST', '/jadwal'],
    ['PUT', '/jadwal/{jadwal}'],
    ['DELETE', '/jadwal/{jadwal}'],
    ['PUT', '/absensi/{absensi}'],
    ['DELETE', '/absensi/{absensi}'],
    ['PATCH', '/pengajuan-jadwal/{pengajuan}'],
    ['GET', '/aturan-gaji'],
    ['POST', '/aturan-gaji'],
    ['GET', '/report/gaji'],
    ['POST', '/notification'],
]);

test('admins still reach the admin-only endpoints', function () {
    Sanctum::actingAs($this->admin);

    $this->getJson('/api/mentor')->assertOk();
    $this->getJson('/api/aturan-gaji')->assertOk();
    $this->getJson('/api/report/gaji')->assertOk();
    $this->patchJson("/api/pengajuan-jadwal/{$this->b->pending->id}", ['status' => 'diterima'])->assertOk();
});

test('mentor lists only contain their own kelas, even when asking for another mentor', function () {
    Sanctum::actingAs($this->a->user);
    $other = "mentor_id={$this->b->mentor->id}&paginate=false";

    expect(collect(rows($this->getJson("/api/kelas?$other&search=Kelas")))->pluck('id')->all())
        ->toBe([$this->a->kelas->id]);
    expect(collect(rows($this->getJson("/api/siswa?$other&search=Siswa")))->pluck('id')->all())
        ->toBe([$this->a->siswa->id]);
    expect(collect(rows($this->getJson("/api/jadwal?$other")))->pluck('kelas_id')->unique()->all())
        ->toBe([$this->a->kelas->id]);
    expect(collect(rows($this->getJson("/api/absensi?$other")))->pluck('id')->all())
        ->toBe([$this->a->absensi->id]);
    expect(collect(rows($this->getJson("/api/pengajuan-jadwal?$other&search=Kelas")))->pluck('jadwal_id')->unique()->all())
        ->toBe([$this->a->future->id]);
    expect(collect(rows($this->getJson("/api/report/absensi?$other")))->pluck('kelas_id')->all())
        ->toBe([$this->a->kelas->id]);

    $stats = $this->getJson("/api/dashboard/stats?mentor_id={$this->b->mentor->id}&from=" . now()->subMonth()->toDateString() . '&to=' . now()->toDateString());
    expect($stats->json('data.total_siswa'))->toBe(1)
        ->and($stats->json('data.kehadiran.total'))->toBe(1);
});

test('admin lists are not narrowed', function () {
    Sanctum::actingAs($this->admin);

    expect(rows($this->getJson('/api/kelas?paginate=false')))->toHaveCount(2);
    expect(rows($this->getJson('/api/absensi?paginate=false')))->toHaveCount(2);
});

test('mentors cannot open another mentor\'s records', function () {
    Sanctum::actingAs($this->a->user);
    $b = $this->b;

    $this->getJson("/api/kelas/{$b->kelas->id}")->assertForbidden();
    $this->getJson("/api/siswa/{$b->siswa->id}")->assertForbidden();
    $this->getJson("/api/jadwal/{$b->past->id}")->assertForbidden();
    $this->getJson("/api/absensi/{$b->absensi->id}")->assertForbidden();
    $this->getJson("/api/pengajuan-jadwal/{$b->pending->id}")->assertForbidden();
    $this->getJson("/api/report/gaji/{$b->mentor->id}")->assertForbidden();
    $this->getJson("/api/report/absensi/{$b->kelas->id}")->assertOk()->assertJsonPath('data.kelas', null);

    // ...but their own still open.
    $this->getJson("/api/kelas/{$this->a->kelas->id}")->assertOk();
    $this->getJson("/api/report/gaji/{$this->a->mentor->id}")->assertOk();
});

test('mentors save attendance only for their own past jadwal and their own students', function () {
    Sanctum::actingAs($this->a->user);
    $a = $this->a;
    $row = fn ($siswa) => ['absensi' => [['siswa_id' => $siswa->id, 'status' => 'S']]];

    $this->postJson('/api/absensi', ['jadwal_id' => $a->past->id] + $row($a->siswa))->assertCreated();
    expect($a->absensi->fresh()->status)->toBe('s');

    $this->postJson('/api/absensi', ['jadwal_id' => $this->b->past->id] + $row($this->b->siswa))->assertForbidden();
    $this->postJson('/api/absensi', ['jadwal_id' => $a->future->id] + $row($a->siswa))->assertUnprocessable()->assertJsonValidationErrors('jadwal_id');
    $this->postJson('/api/absensi', ['jadwal_id' => $a->past->id] + $row($this->b->siswa))->assertUnprocessable()->assertJsonValidationErrors('absensi');
});

test('a mentor request is forced pending and keeps the real before values', function () {
    Sanctum::actingAs($this->a->user);
    $a = $this->a;

    $this->postJson('/api/pengajuan-jadwal', [
        'jadwal_id' => $a->future->id,
        'status' => 'diterima',
        'tanggal_sebelum' => '2000-01-01',
        'tanggal_sesudah' => now()->addDays(5)->toDateString(),
        'waktu_mulai_sebelum' => '01:00:00',
        'waktu_mulai_sesudah' => '14:00:00',
        'waktu_selesai_sebelum' => '02:00:00',
        'waktu_selesai_sesudah' => '15:00:00',
    ])->assertCreated();

    $created = PengajuanJadwal::latest('id')->first();
    expect($created->status)->toBe('pending')
        ->and(substr((string) $created->tanggal_sebelum, 0, 10))->toBe(now()->addDays(3)->toDateString())
        ->and($created->waktu_mulai_sebelum)->toBe('10:00:00');

    $this->postJson('/api/pengajuan-jadwal', [
        'jadwal_id' => $this->b->future->id,
        'tanggal_sebelum' => now()->toDateString(),
        'tanggal_sesudah' => now()->addDay()->toDateString(),
        'waktu_mulai_sebelum' => '10:00:00',
        'waktu_mulai_sesudah' => '12:00:00',
        'waktu_selesai_sebelum' => '11:00:00',
        'waktu_selesai_sesudah' => '13:00:00',
    ])->assertForbidden();
});

test('mentors cancel only their own pending requests', function () {
    Sanctum::actingAs($this->a->user);

    $this->deleteJson("/api/pengajuan-jadwal/{$this->a->decided->id}")->assertForbidden();
    $this->deleteJson("/api/pengajuan-jadwal/{$this->b->pending->id}")->assertForbidden();
    $this->deleteJson("/api/pengajuan-jadwal/{$this->a->pending->id}")->assertOk();

    expect(PengajuanJadwal::find($this->a->pending->id))->toBeNull();
});

test('notifications belong to their recipient', function () {
    Sanctum::actingAs($this->a->user);

    expect(collect(rows($this->getJson("/api/notification?user_id={$this->b->user->id}")))->pluck('id')->all())
        ->toBe([$this->a->notification->id]);

    $this->patchJson("/api/notification/{$this->b->notification->id}", ['is_read' => true])->assertForbidden();
    $this->deleteJson("/api/notification/{$this->b->notification->id}")->assertForbidden();

    $this->patchJson("/api/notification/{$this->a->notification->id}", ['is_read' => true, 'title' => 'Rewritten'])->assertOk();
    $own = $this->a->notification->fresh();
    expect($own->is_read)->toBeTrue()->and($own->title)->toBe('Hi a');

    // The API serialises a real boolean, which the frontend's `is_read: boolean` expects.
    $this->getJson('/api/notification')->assertJsonPath('data.0.is_read', true);
});

test('notifications filter by read state, including unread', function () {
    Sanctum::actingAs($this->a->user);
    $unread = $this->a->notification;
    $read = Notification::forceCreate(['user_id' => $this->a->user->id, 'title' => 'Seen', 'message' => 'm', 'is_read' => true]);

    $ids = fn (string $query) => collect(rows($this->getJson("/api/notification$query")))->pluck('id')->sort()->values()->all();

    expect($ids('?is_read=0'))->toBe([$unread->id])
        ->and($ids('?is_read=false'))->toBe([$unread->id])
        ->and($ids('?is_read=1'))->toBe([$read->id])
        ->and($ids('?is_read=true'))->toBe([$read->id])
        ->and($ids(''))->toBe([$unread->id, $read->id])
        ->and($ids('?is_read='))->toBe([$unread->id, $read->id])
        ->and($ids('?is_read=maybe'))->toBe([$unread->id, $read->id]);
});
