<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Models\PeriodeGaji;
use App\Support\Laporan;
use App\Support\Sql;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReportAbsensiController extends Controller
{
    /**
     * Rekap absensi per kelas per bulan.
     *
     * There is no `report_absensi` table (the model pointed at one that was
     * never migrated), so this is derived from `absensi` on read.
     */
    public function index(ReportFilterRequest $request)
    {
        $allowedSorts = ['tahun', 'bulan', 'kelas', 'hadir', 'sakit', 'izin', 'alpa'];
        $orderBy = in_array($request->query('order_by'), $allowedSorts, true)
            ? $request->query('order_by')
            : 'tahun';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $data = $this->paginate(
            $this->rekapQuery($request),
            $request->query('limit') ?? 10,
            $request->query('paginate'),
            $orderBy,
            $direction
        );

        return response()->json([
            'message' => 'Data laporan absensi berhasil diambil.',
            'data' => $data,
        ], 200);
    }

    /**
     * Per-siswa breakdown for one kelas. With ?format=pdf|xlsx, a download.
     */
    public function show(ReportFilterRequest $request, $kelasId)
    {
        [$query, $kelas] = $this->detailQuery($request, (int) $kelasId);

        if ($request->query('format')) {
            return $this->exportDetail($request, $query, $kelas);
        }

        $allowedSorts = ['siswa', 'hadir', 'sakit', 'izin', 'alpa', 'total'];
        $orderBy = in_array($request->query('order_by'), $allowedSorts, true)
            ? $request->query('order_by')
            : 'siswa';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        $data = $this->paginate(
            $query,
            $request->query('limit') ?? 10,
            $request->query('paginate'),
            $orderBy,
            $direction
        );

        return response()->json([
            'message' => 'Rincian laporan absensi berhasil diambil.',
            'data' => [
                'kelas' => $kelas,
                'rekap' => $data,
            ],
        ], 200);
    }

    /**
     * Rekap absensi as PDF or Excel with the same filters as index(). Mentors
     * get their own kelas only (mentor.scope pins mentor_id).
     */
    public function export(ReportFilterRequest $request)
    {
        $format = self::format($request);

        $rows = $this->rekapQuery($request)
            ->orderByDesc('tahun')->orderByDesc('bulan')->orderBy('kelas')
            ->get()
            ->map(fn ($row) => [
                $row->kelas,
                $row->tingkat,
                $row->mentor ?? '-',
                PeriodeGaji::labelOf(sprintf('%04d-%02d-01', $row->tahun, $row->bulan)),
                (int) $row->hadir,
                (int) $row->sakit,
                (int) $row->izin,
                (int) $row->alpa,
                (int) $row->total,
                self::persen($row->hadir, $row->total),
            ])->all();

        $sum = fn (int $i) => array_sum(array_column($rows, $i));

        return Laporan::table(
            $format,
            'rekap-absensi',
            'Rekap Absensi Siswa',
            self::keterangan($request),
            ['Kelas', 'Tingkat', 'Guru/Mentor', 'Periode', 'Hadir', 'Sakit', 'Izin', 'Alpa', 'Total', 'Kehadiran'],
            $rows,
            ['Total', '', '', '', $sum(4), $sum(5), $sum(6), $sum(7), $sum(8), self::persen($sum(4), $sum(8))],
        );
    }

    private function exportDetail(ReportFilterRequest $request, Builder $query, ?object $kelas)
    {
        $format = self::format($request);
        abort_if($kelas === null, 404, 'Kelas tidak ditemukan.');

        $rows = $query->orderBy('siswa')->get()->map(fn ($row) => [
            $row->siswa,
            (int) $row->hadir,
            (int) $row->sakit,
            (int) $row->izin,
            (int) $row->alpa,
            (int) $row->total,
            self::persen($row->hadir, $row->total),
        ])->all();

        $sum = fn (int $i) => array_sum(array_column($rows, $i));

        return Laporan::table(
            $format,
            'rekap-absensi-'.Str::slug($kelas->nama),
            "Rekap Absensi {$kelas->nama} ({$kelas->tingkat})",
            ['Guru/Mentor: '.($kelas->mentor ?? '-'), ...self::keterangan($request)],
            ['Siswa', 'Hadir', 'Sakit', 'Izin', 'Alpa', 'Total', 'Kehadiran'],
            $rows,
            ['Total', $sum(1), $sum(2), $sum(3), $sum(4), $sum(5), self::persen($sum(1), $sum(5))],
        );
    }

    private function rekapQuery(ReportFilterRequest $request): Builder
    {
        $query = DB::table('absensi')
            ->join('jadwal', 'jadwal.id', '=', 'absensi.jadwal_id')
            ->join('kelas', 'kelas.id', '=', 'jadwal.kelas_id')
            ->leftJoin('mentor', 'mentor.id', '=', 'kelas.mentor_id')
            ->leftJoin('users', 'users.id', '=', 'mentor.user_id')
            ->selectRaw("
                kelas.id as kelas_id,
                kelas.nama as kelas,
                kelas.tingkat as tingkat,
                users.name as mentor,
                {$this->yearExpr('absensi.tanggal')} as tahun,
                {$this->monthExpr('absensi.tanggal')} as bulan,
                SUM(CASE WHEN absensi.status = 'h' THEN 1 ELSE 0 END) as hadir,
                SUM(CASE WHEN absensi.status = 's' THEN 1 ELSE 0 END) as sakit,
                SUM(CASE WHEN absensi.status = 'i' THEN 1 ELSE 0 END) as izin,
                SUM(CASE WHEN absensi.status = 'a' THEN 1 ELSE 0 END) as alpa,
                COUNT(absensi.id) as total
            ")
            ->groupBy('kelas.id', 'kelas.nama', 'kelas.tingkat', 'users.name', 'tahun', 'bulan');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('kelas.nama', Sql::like(), '%'.$search.'%')
                    ->orWhere('users.name', Sql::like(), '%'.$search.'%');
            });
        }

        if ($kelasId = $request->query('kelas_id')) {
            $query->where('kelas.id', $kelasId);
        }

        if ($mentorId = $request->query('mentor_id')) {
            $query->where('kelas.mentor_id', $mentorId);
        }

        if (($from = $request->query('from')) && ($to = $request->query('to'))) {
            $query->whereDate('absensi.tanggal', '>=', $from)
                ->whereDate('absensi.tanggal', '<=', $to);
        }

        return $query;
    }

    /**
     * @return array{0: Builder, 1: ?object} the per-siswa query and the kelas
     */
    private function detailQuery(ReportFilterRequest $request, int $kelasId): array
    {
        $query = DB::table('absensi')
            ->join('jadwal', 'jadwal.id', '=', 'absensi.jadwal_id')
            ->join('siswa', 'siswa.id', '=', 'absensi.siswa_id')
            ->join('kelas', 'kelas.id', '=', 'jadwal.kelas_id')
            ->where('jadwal.kelas_id', $kelasId)
            ->selectRaw("
                siswa.id as siswa_id,
                siswa.nama as siswa,
                SUM(CASE WHEN absensi.status = 'h' THEN 1 ELSE 0 END) as hadir,
                SUM(CASE WHEN absensi.status = 's' THEN 1 ELSE 0 END) as sakit,
                SUM(CASE WHEN absensi.status = 'i' THEN 1 ELSE 0 END) as izin,
                SUM(CASE WHEN absensi.status = 'a' THEN 1 ELSE 0 END) as alpa,
                COUNT(absensi.id) as total
            ")
            ->groupBy('siswa.id', 'siswa.nama');

        if ($search = $request->query('search')) {
            $query->where('siswa.nama', Sql::like(), '%'.$search.'%');
        }

        if (($from = $request->query('from')) && ($to = $request->query('to'))) {
            $query->whereDate('absensi.tanggal', '>=', $from)
                ->whereDate('absensi.tanggal', '<=', $to);
        }

        $kelas = DB::table('kelas')
            ->leftJoin('mentor', 'mentor.id', '=', 'kelas.mentor_id')
            ->leftJoin('users', 'users.id', '=', 'mentor.user_id')
            ->where('kelas.id', $kelasId)
            ->select('kelas.id', 'kelas.nama', 'kelas.tingkat', 'users.name as mentor');

        // A kelas taught by someone else comes back as kelas: null with no rows.
        if ($mentorId = $request->query('mentor_id')) {
            $query->where('kelas.mentor_id', $mentorId);
            $kelas->where('kelas.mentor_id', $mentorId);
        }

        return [$query, $kelas->first()];
    }

    private static function format(ReportFilterRequest $request): string
    {
        $request->validate(['format' => ['required', 'in:'.implode(',', Laporan::FORMATS)]], [
            'format.required' => 'Pilih format unduhan: pdf atau xlsx.',
            'format.in' => 'Format unduhan harus pdf atau xlsx.',
        ]);

        return $request->query('format');
    }

    /** The active filters, in words, for the export header. */
    private static function keterangan(ReportFilterRequest $request): array
    {
        $from = $request->query('from');
        $to = $request->query('to');

        return array_values(array_filter([
            $from && $to ? "Tanggal: {$from} s.d. {$to}" : 'Periode: semua',
            $request->query('search') ? 'Pencarian: '.$request->query('search') : null,
        ]));
    }

    private static function persen($hadir, $total): string
    {
        return $total > 0 ? round($hadir / $total * 100).'%' : '-';
    }
}
