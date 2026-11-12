<?php

namespace Tests\Support;

use App\Models\Absensi;
use App\Models\AturanGaji;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Mentor;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * A small, readable data set shared by the feature tests.
 *
 * Budi teaches Matematika 7A and 7B, Sari teaches Fisika 8B, Rina teaches
 * nothing. Dates are relative to today (`day(-1)` is yesterday) except the
 * September 2026 sessions, which give the salary report a fixed month:
 * Budi earns 2 × 30.000 and Sari 1 × 40.000 there.
 *
 * Every password is `rahasia-uji`.
 */
class BimbelFixture
{
    public const PASSWORD = 'rahasia-uji';

    public User $admin;

    public User $budi;

    public User $sari;

    public User $rina;

    public Mentor $mBudi;

    public Mentor $mSari;

    public Mentor $mRina;

    public Kelas $k7a;

    public Kelas $k7b;

    public Kelas $k8b;

    public AturanGaji $aturan7a;

    public AturanGaji $aturan7b;

    public AturanGaji $aturan8b;

    /** @var array<string, Siswa> keyed by name */
    public array $siswa = [];

    /** K-7A, yesterday, no absensi yet. */
    public Jadwal $jLalu;

    /** K-7A, a week ago, Andi h and Bela s. */
    public Jadwal $jIsi;

    /** K-7A, in three days. */
    public Jadwal $jDepan;

    /** K-8B, in two days. */
    public Jadwal $j8b;

    /** K-7A, 2026-09-07, Andi h and Bela h. */
    public Jadwal $jSep7a;

    /** K-8B, 2026-09-08, Dimas h. */
    public Jadwal $jSep8b;

    public static function make(): self
    {
        $f = new self;

        $user = fn (string $name, string $username, int $role) => User::create([
            'name' => $name,
            'username' => $username,
            'email' => "$username@uji.test",
            'password' => self::PASSWORD,
            'role' => $role,
        ]);

        $f->admin = $user('Admin', 'admin', 1);
        $f->budi = $user('Budi Santoso', 'budi', 0);
        $f->sari = $user('Sari Dewi', 'sari', 0);
        $f->rina = $user('Rina Kartika', 'rina', 0);
        $f->mBudi = Mentor::create(['user_id' => $f->budi->id, 'kontak' => '081234567890']);
        $f->mSari = Mentor::create(['user_id' => $f->sari->id, 'kontak' => '081298765432']);
        $f->mRina = Mentor::create(['user_id' => $f->rina->id]);

        $f->k7a = Kelas::create(['nama' => 'Matematika 7A', 'tingkat' => 'SMP', 'mentor_id' => $f->mBudi->id]);
        $f->k7b = Kelas::create(['nama' => 'Matematika 7B', 'tingkat' => 'SMP', 'mentor_id' => $f->mBudi->id]);
        $f->k8b = Kelas::create(['nama' => 'Fisika 8B', 'tingkat' => 'SMP', 'mentor_id' => $f->mSari->id]);
        $f->aturan7a = AturanGaji::create(['kelas_id' => $f->k7a->id, 'tarif' => 30000]);
        $f->aturan7b = AturanGaji::create(['kelas_id' => $f->k7b->id, 'tarif' => 30000]);
        $f->aturan8b = AturanGaji::create(['kelas_id' => $f->k8b->id, 'tarif' => 40000]);

        foreach (['Andi' => $f->k7a, 'Bela' => $f->k7a, 'Gita' => $f->k7b, 'Dimas' => $f->k8b] as $nama => $kelas) {
            $f->siswa[$nama] = Siswa::create(['nama' => $nama, 'kelas_id' => $kelas->id]);
        }

        $f->jLalu = $f->jadwal($f->k7a, self::day(-1));
        $f->jIsi = $f->jadwal($f->k7a, self::day(-7), ['Andi' => 'h', 'Bela' => 's']);
        $f->jDepan = $f->jadwal($f->k7a, self::day(3));
        $f->j8b = $f->jadwal($f->k8b, self::day(2), [], '13:00:00', '15:00:00');
        $f->jSep7a = $f->jadwal($f->k7a, '2026-09-07', ['Andi' => 'h', 'Bela' => 'h']);
        $f->jSep8b = $f->jadwal($f->k8b, '2026-09-08', ['Dimas' => 'h']);

        return $f;
    }

    /** A date relative to today, as Y-m-d. */
    public static function day(int $offset): string
    {
        return Carbon::today()->addDays($offset)->toDateString();
    }

    /**
     * @param  array<string, string>  $statuses  siswa name => h/i/s/a
     */
    public function jadwal(Kelas $kelas, string $tanggal, array $statuses = [], string $mulai = '08:00:00', string $selesai = '10:00:00'): Jadwal
    {
        $jadwal = Jadwal::create([
            'kelas_id' => $kelas->id,
            'tanggal' => $tanggal,
            'waktu_mulai' => $mulai,
            'waktu_selesai' => $selesai,
            'materi' => 'Materi uji',
        ]);

        foreach ($statuses as $nama => $status) {
            Absensi::create([
                'jadwal_id' => $jadwal->id,
                'siswa_id' => $this->siswa[$nama]->id,
                'tanggal' => $tanggal,
                'status' => $status,
            ]);
        }

        return $jadwal;
    }
}
