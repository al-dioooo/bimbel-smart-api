<?php

namespace App\Http\Controllers;

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
     * to the current month.
     */
    public function stats(Request $request)
    {
        $from = $request->query('from')
            ? Carbon::parse($request->query('from'))->startOfDay()
            : Carbon::now()->startOfMonth();
        $to = $request->query('to')
            ? Carbon::parse($request->query('to'))->endOfDay()
            : Carbon::now()->endOfMonth();

        $breakdown = DB::table('absensi')
            ->whereBetween('absensi.tanggal', [$from->toDateString(), $to->toDateString()])
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
        $izin  = (int) ($breakdown->izin ?? 0);
        $alpa  = (int) ($breakdown->alpa ?? 0);
        $total = (int) ($breakdown->total ?? 0);

        // Earnings mirror ReportGaji: each `hadir` earns that kelas's tarif.
        $penghasilan = (float) DB::table('absensi')
            ->join('jadwal', 'jadwal.id', '=', 'absensi.jadwal_id')
            ->leftJoin('aturan_gaji', 'aturan_gaji.kelas_id', '=', 'jadwal.kelas_id')
            ->where('absensi.status', 'h')
            ->whereBetween('absensi.tanggal', [$from->toDateString(), $to->toDateString()])
            ->sum('aturan_gaji.tarif');

        return response()->json([
            'message' => 'Successfully get dashboard stats.',
            'data' => [
                'periode' => [
                    'from' => $from->toDateString(),
                    'to'   => $to->toDateString(),
                ],
                'total_siswa'     => Siswa::count(),
                'total_kehadiran' => $hadir,
                'penghasilan'     => $penghasilan,
                'persentase_kehadiran' => $total > 0 ? round(($hadir / $total) * 100) : 0,
                'kehadiran' => [
                    'hadir' => $hadir,
                    'sakit' => $sakit,
                    'izin'  => $izin,
                    'alpa'  => $alpa,
                    'total' => $total,
                ],
            ],
        ]);
    }
}
