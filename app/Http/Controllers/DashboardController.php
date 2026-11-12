<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Mentor;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Everything the dashboard renders, in one round trip.
     *
     * Optional ?from=&to= narrows the attendance/earnings window; it defaults
     * to the current month. Optional ?mentor_id= limits every figure to the
     * kelas that mentor teaches.
     */
    public function stats(Request $request)
    {
        $from = $request->query('from')
            ? Carbon::parse($request->query('from'))->startOfDay()
            : Carbon::now()->startOfMonth();
        $to = $request->query('to')
            ? Carbon::parse($request->query('to'))->endOfDay()
            : Carbon::now()->endOfMonth();

        $mentorId = $request->query('mentor_id');
        $mentorJadwal = fn ($query) => $query->whereIn(
            'absensi.jadwal_id',
            DB::table('jadwal')
                ->join('kelas', 'kelas.id', '=', 'jadwal.kelas_id')
                ->where('kelas.mentor_id', $mentorId)
                ->select('jadwal.id')
        );

        $breakdown = DB::table('absensi')
            ->whereBetween('absensi.tanggal', [$from->toDateString(), $to->toDateString()])
            ->when($mentorId, $mentorJadwal)
            ->selectRaw("
                SUM(CASE WHEN status = 'h' THEN 1 ELSE 0 END) as hadir,
                SUM(CASE WHEN status = 's' THEN 1 ELSE 0 END) as sakit,
                SUM(CASE WHEN status = 'i' THEN 1 ELSE 0 END) as izin,
                SUM(CASE WHEN status = 'a' THEN 1 ELSE 0 END) as alpa,
                COUNT(*) as total
            ")
            ->first();

        $hadir = (int) ($breakdown->hadir ?? 0);
        $sakit = (int) ($breakdown->sakit ?? 0);
        $izin = (int) ($breakdown->izin ?? 0);
        $alpa = (int) ($breakdown->alpa ?? 0);
        $total = (int) ($breakdown->total ?? 0);

        // Earnings mirror ReportGaji: each `hadir` earns that kelas's tarif.
        $penghasilan = (float) DB::table('absensi')
            ->join('jadwal', 'jadwal.id', '=', 'absensi.jadwal_id')
            ->leftJoin('aturan_gaji', 'aturan_gaji.kelas_id', '=', 'jadwal.kelas_id')
            ->where('absensi.status', 'h')
            ->whereBetween('absensi.tanggal', [$from->toDateString(), $to->toDateString()])
            ->when($mentorId, $mentorJadwal)
            ->sum('aturan_gaji.tarif');

        return response()->json([
            'message' => 'Data dashboard berhasil diambil.',
            'data' => [
                'periode' => [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                ],
                'total_siswa' => Siswa::when($mentorId, fn ($query) => $query->whereRelation('kelas', 'mentor_id', $mentorId))->count(),
                // REQ-067. A mentor sees the kelas they teach; the mentor count is admin-only.
                'total_kelas' => Kelas::when($mentorId, fn ($query) => $query->where('mentor_id', $mentorId))->count(),
                ...($mentorId ? [] : ['total_mentor' => Mentor::count()]),
                // UC-16, REQ-070, REQ-071: reminders for the mentor on login.
                ...($mentorId ? $this->pengingatAbsensi((int) $mentorId) : []),
                'total_kehadiran' => $hadir,
                'penghasilan' => $penghasilan,
                'persentase_kehadiran' => $total > 0 ? round(($hadir / $total) * 100) : 0,
                'kehadiran' => [
                    'hadir' => $hadir,
                    'sakit' => $sakit,
                    'izin' => $izin,
                    'alpa' => $alpa,
                    'total' => $total,
                ],
            ],
        ]);
    }

    /**
     * UC-16: past sessions of the mentor's kelas that have students but no
     * attendance at all (2b), and today's sessions (2a). Both are empty when
     * neither applies, so the dashboard shows nothing (2a alternative).
     *
     * @return array{absensi_belum_diisi: array<int, array<string, mixed>>, jumlah_absensi_belum_diisi: int, jadwal_hari_ini: array<int, array<string, mixed>>}
     */
    private function pengingatAbsensi(int $mentorId): array
    {
        $today = Carbon::today()->toDateString();
        $milikMentor = fn ($query) => $query->whereHas('kelas', fn ($kelas) => $kelas->where('mentor_id', $mentorId));
        $ringkas = fn (Jadwal $jadwal) => [
            'jadwal_id' => $jadwal->id,
            'kelas_id' => $jadwal->kelas_id,
            'kelas' => $jadwal->kelas?->nama,
            'tanggal' => Carbon::parse($jadwal->tanggal)->toDateString(),
            'waktu_mulai' => substr((string) $jadwal->waktu_mulai, 0, 5),
            'waktu_selesai' => substr((string) $jadwal->waktu_selesai, 0, 5),
            'materi' => $jadwal->materi,
        ];

        $belumDiisi = Jadwal::query()
            ->with('kelas')
            ->tap($milikMentor)
            ->whereHas('kelas.siswa')
            ->whereDate('tanggal', '<', $today)
            ->doesntHave('absensi');

        $hariIni = Jadwal::query()
            ->with('kelas')
            ->withCount('absensi')
            ->tap($milikMentor)
            ->whereDate('tanggal', $today)
            ->orderBy('waktu_mulai')
            ->get();

        return [
            'jumlah_absensi_belum_diisi' => (clone $belumDiisi)->count(),
            // Newest first; the dashboard links to the absensi page for the rest.
            'absensi_belum_diisi' => $belumDiisi->orderByDesc('tanggal')->orderByDesc('waktu_mulai')->limit(10)->get()->map($ringkas)->all(),
            'jadwal_hari_ini' => $hariIni->map(fn (Jadwal $jadwal) => $ringkas($jadwal) + [
                'sudah_diabsen' => $jadwal->absensi_count > 0,
            ])->all(),
        ];
    }
}
