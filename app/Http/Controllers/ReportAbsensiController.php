<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportAbsensiController extends Controller
{
    /**
     * Rekap absensi per kelas per bulan.
     *
     * There is no `report_absensi` table (the model pointed at one that was
     * never migrated), so this is derived from `absensi` on read.
     */
    public function index(Request $request)
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
                $q->where('kelas.nama', 'like', '%' . $search . '%')
                    ->orWhere('users.name', 'like', '%' . $search . '%');
            });
        }

        if ($kelasId = $request->query('kelas_id')) {
            $query->where('kelas.id', $kelasId);
        }

        if (($from = $request->query('from')) && ($to = $request->query('to'))) {
            $query->whereDate('absensi.tanggal', '>=', $from)
                ->whereDate('absensi.tanggal', '<=', $to);
        }

        $allowedSorts = ['tahun', 'bulan', 'kelas', 'hadir', 'sakit', 'izin', 'alpa'];
        $orderBy = in_array($request->query('order_by'), $allowedSorts, true)
            ? $request->query('order_by')
            : 'tahun';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $data = $this->paginate(
            $query,
            $request->query('limit') ?? 10,
            $request->query('paginate'),
            $orderBy,
            $direction
        );

        return response()->json([
            'message' => 'Successfully get report absensi data.',
            'data' => $data
        ], 200);
    }

    /**
     * Per-siswa breakdown for one kelas.
     */
    public function show(Request $request, $kelasId)
    {
        $query = DB::table('absensi')
            ->join('jadwal', 'jadwal.id', '=', 'absensi.jadwal_id')
            ->join('siswa', 'siswa.id', '=', 'absensi.siswa_id')
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
            $query->where('siswa.nama', 'like', '%' . $search . '%');
        }

        if (($from = $request->query('from')) && ($to = $request->query('to'))) {
            $query->whereDate('absensi.tanggal', '>=', $from)
                ->whereDate('absensi.tanggal', '<=', $to);
        }

        $kelas = DB::table('kelas')
            ->leftJoin('mentor', 'mentor.id', '=', 'kelas.mentor_id')
            ->leftJoin('users', 'users.id', '=', 'mentor.user_id')
            ->where('kelas.id', $kelasId)
            ->select('kelas.id', 'kelas.nama', 'kelas.tingkat', 'users.name as mentor')
            ->first();

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
            'message' => 'Successfully get report absensi detail.',
            'data' => [
                'kelas' => $kelas,
                'rekap' => $data,
            ]
        ], 200);
    }
}
