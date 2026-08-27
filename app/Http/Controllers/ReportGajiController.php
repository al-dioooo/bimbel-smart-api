<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportGajiController extends Controller
{
    /**
     * Gaji per mentor per bulan.
     *
     * A mentor's rate lives on aturan_gaji, keyed by kelas, and is earned per
     * student attendance. So: for every `hadir` row under a jadwal of a kelas
     * the mentor teaches, add that kelas's tarif.
     *
     * Note jadwal has no mentor_id — the mentor hangs off kelas.
     */
    private function baseQuery(Request $request)
    {
        $query = DB::table('jadwal')
            ->join('kelas', 'kelas.id', '=', 'jadwal.kelas_id')
            ->join('mentor', 'mentor.id', '=', 'kelas.mentor_id')
            ->join('users', 'users.id', '=', 'mentor.user_id')
            ->leftJoin('aturan_gaji', 'aturan_gaji.kelas_id', '=', 'jadwal.kelas_id')
            ->leftJoin('absensi', function ($join) {
                $join->on('absensi.jadwal_id', '=', 'jadwal.id')
                    ->where('absensi.status', '=', 'h');
            })
            ->selectRaw("
                mentor.id as mentor_id,
                users.name as mentor,
                {$this->yearExpr('jadwal.tanggal')} as tahun,
                {$this->monthExpr('jadwal.tanggal')} as bulan,
                COUNT(absensi.id) as total_kehadiran_murid,
                COALESCE(SUM(CASE WHEN absensi.id IS NOT NULL THEN aturan_gaji.tarif ELSE 0 END), 0) as nominal
            ")
            ->groupBy('mentor.id', 'users.name', 'tahun', 'bulan');

        if ($mentorId = $request->query('mentor_id')) {
            $query->where('mentor.id', $mentorId);
        }

        if ($search = $request->query('search')) {
            $query->where('users.name', 'like', '%' . $search . '%');
        }

        // ?bulan=YYYY-MM
        $bulan = $request->query('bulan');
        if ($bulan && preg_match('/^(\d{4})-(\d{2})$/', $bulan, $m)) {
            $query->whereYear('jadwal.tanggal', (int) $m[1])
                ->whereMonth('jadwal.tanggal', (int) $m[2]);
        }

        if (($from = $request->query('from')) && ($to = $request->query('to'))) {
            $query->whereDate('jadwal.tanggal', '>=', $from)
                ->whereDate('jadwal.tanggal', '<=', $to);
        }

        return $query;
    }

    public function index(Request $request)
    {
        $allowedSorts = ['tahun', 'bulan', 'mentor', 'total_kehadiran_murid', 'nominal'];
        $orderBy = in_array($request->query('order_by'), $allowedSorts, true)
            ? $request->query('order_by')
            : 'tahun';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        // paginate() takes ($query, $limit, $usePagination, $orderBy, $direction) —
        // the previous call passed only four arguments, so $orderBy landed in
        // $usePagination and the sort was silently ignored.
        $data = $this->paginate(
            $this->baseQuery($request),
            $request->query('limit') ?? 10,
            $request->query('paginate'),
            $orderBy,
            $direction
        );

        return response()->json([
            'message' => 'Successfully get report gaji data.',
            'data'    => $data,
        ], 200);
    }

    /**
     * Per-kelas breakdown behind one mentor's monthly total.
     */
    public function show(Request $request, $mentorId)
    {
        $query = DB::table('jadwal')
            ->join('kelas', 'kelas.id', '=', 'jadwal.kelas_id')
            ->join('mentor', 'mentor.id', '=', 'kelas.mentor_id')
            ->join('users', 'users.id', '=', 'mentor.user_id')
            ->leftJoin('aturan_gaji', 'aturan_gaji.kelas_id', '=', 'jadwal.kelas_id')
            ->leftJoin('absensi', function ($join) {
                $join->on('absensi.jadwal_id', '=', 'jadwal.id')
                    ->where('absensi.status', '=', 'h');
            })
            ->where('mentor.id', $mentorId)
            ->selectRaw("
                kelas.id as kelas_id,
                kelas.nama as kelas,
                users.name as mentor,
                {$this->yearExpr('jadwal.tanggal')} as tahun,
                {$this->monthExpr('jadwal.tanggal')} as bulan,
                COALESCE(MAX(aturan_gaji.tarif), 0) as tarif,
                COUNT(absensi.id) as jumlah_kehadiran,
                COALESCE(SUM(CASE WHEN absensi.id IS NOT NULL THEN aturan_gaji.tarif ELSE 0 END), 0) as nominal
            ")
            ->groupBy('kelas.id', 'kelas.nama', 'users.name', 'tahun', 'bulan');

        $bulan = $request->query('bulan');
        if ($bulan && preg_match('/^(\d{4})-(\d{2})$/', $bulan, $m)) {
            $query->whereYear('jadwal.tanggal', (int) $m[1])
                ->whereMonth('jadwal.tanggal', (int) $m[2]);
        }

        $data = $this->paginate(
            $query,
            $request->query('limit') ?? 10,
            $request->query('paginate'),
            'tahun',
            'desc'
        );

        return response()->json([
            'message' => 'Successfully get report gaji detail.',
            'data'    => $data,
        ], 200);
    }
}
