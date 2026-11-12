<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinalizeGajiRequest;
use App\Http\Requests\ReportFilterRequest;
use App\Models\Mentor;
use App\Models\Notification;
use App\Models\PenyesuaianGaji;
use App\Models\PeriodeGaji;
use App\Services\GajiCalculator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportGajiController extends Controller
{
    public function __construct(private GajiCalculator $gaji) {}

    /**
     * Gaji per mentor per bulan (REQ-055): Σ(hadir × tarif) plus the month's
     * bonus minus potongan (REQ-064). `nominal` is the total to pay.
     */
    public function index(ReportFilterRequest $request)
    {
        $allowedSorts = ['tahun', 'bulan', 'mentor', 'total_kehadiran_murid', 'nominal'];
        $orderBy = in_array($request->query('order_by'), $allowedSorts, true)
            ? $request->query('order_by')
            : 'tahun';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $data = $this->paginate(
            $this->gaji->rekap($request->only(['mentor_id', 'search', 'bulan', 'from', 'to'])),
            $request->query('limit') ?? 10,
            $request->query('paginate'),
            $orderBy,
            $direction
        );

        return response()->json([
            'message' => 'Successfully get report gaji data.',
            'data' => $this->mapRows($data, fn ($row) => self::numbers($row, ['mentor_id', 'tahun', 'bulan', 'total_kehadiran_murid'], ['gaji_pokok', 'bonus', 'potongan', 'nominal'])),
        ], 200);
    }

    /**
     * Per-kelas breakdown behind one mentor's monthly total, with the
     * adjustments of the same filter (all months, or ?bulan=). Finalized
     * months come from the stored snapshot.
     */
    public function show(ReportFilterRequest $request, $mentorId)
    {
        $this->authorizeOwner($mentorId);

        $rows = $this->gaji->rincianKelas((int) $mentorId, $request->only(['bulan', 'from', 'to']))
            ->map(fn ($row) => self::numbers($row, ['kelas_id', 'tahun', 'bulan', 'jumlah_kehadiran'], ['tarif', 'nominal']));

        return response()->json([
            'message' => 'Successfully get report gaji detail.',
            'data' => $this->paginateCollection($rows, $request->query('limit') ?? 10, $request->query('paginate')),
            'penyesuaian' => $this->gaji->penyesuaian((int) $mentorId, $request->query('bulan')),
        ], 200);
    }

    /**
     * UC-10 steps 2–7: the draft (or, once finalized, the final) payroll of
     * one month, per mentor, with sessions, tarif, adjustments and totals.
     */
    public function periode(string $bulan)
    {
        validator(['bulan' => $bulan], ['bulan' => ['regex:/^\d{4}-(0[1-9]|1[0-2])$/']], [
            'bulan.regex' => 'Format bulan harus YYYY-MM, misalnya 2026-09.',
        ])->validate();

        return response()->json([
            'message' => 'Data proses gaji berhasil diambil.',
            'data' => $this->gaji->periode($bulan),
        ]);
    }

    /**
     * UC-10 steps 8–10: "Setujui dan Finalisasi". Stores every mentor's salary
     * of the month in report_gaji and locks the month. UC-10 3a: sessions
     * without absensi need an explicit `lanjutkan: true`.
     */
    public function finalize(FinalizeGajiRequest $request)
    {
        $bulan = $request->validated('bulan');
        $periode = PenyesuaianGaji::periodeOf($bulan);
        $label = PeriodeGaji::labelOf($periode);

        if (PeriodeGaji::finalFor($periode)) {
            throw ValidationException::withMessages(['bulan' => "Periode gaji {$label} sudah difinalisasi."]);
        }

        if (Carbon::parse($periode)->isAfter(Carbon::today()->startOfMonth())) {
            throw ValidationException::withMessages(['bulan' => "Periode {$label} belum berjalan sehingga belum dapat difinalisasi."]);
        }

        $belumDiabsen = $this->gaji->jadwalBelumDiabsen($bulan);
        if ($belumDiabsen > 0 && ! $request->boolean('lanjutkan')) {
            throw ValidationException::withMessages([
                'lanjutkan' => "Peringatan: Terdapat {$belumDiabsen} sesi absensi yang belum terisi pada {$label}. Apakah Anda yakin ingin melanjutkan?",
            ]);
        }

        try {
            DB::transaction(function () use ($bulan, $periode, $label, $request) {
                $draft = $this->gaji->periode($bulan);

                $final = PeriodeGaji::create([
                    'periode' => $periode,
                    'finalized_at' => now(),
                    'finalized_by' => $request->user()->id,
                    'jumlah_mentor' => $draft['total']['jumlah_mentor'],
                    'total_gaji' => $draft['total']['total'],
                ]);

                foreach ($draft['mentor'] as $row) {
                    $final->reports()->create([
                        'mentor_id' => $row['mentor_id'],
                        'mentor_nama' => $row['mentor'],
                        'bulan' => $periode,
                        'jumlah_kehadiran' => $row['total_kehadiran'],
                        'gaji_pokok' => $row['gaji_pokok'],
                        'bonus' => $row['bonus'],
                        'potongan' => $row['potongan'],
                        'total_gaji' => $row['total'],
                        'rincian' => ['kelas' => $row['rincian'], 'penyesuaian' => $row['penyesuaian']],
                    ]);

                    // UC-10 10a: each mentor is told the slip is ready.
                    if ($userId = Mentor::whereKey($row['mentor_id'])->value('user_id')) {
                        Notification::create([
                            'user_id' => $userId,
                            'icon' => 'banknote',
                            'title' => "Slip gaji {$label} tersedia",
                            'message' => "Gaji Anda untuk periode {$label} telah difinalisasi sebesar Rp".number_format($row['total'], 0, ',', '.').'.',
                            'link' => "/report/gaji/{$row['mentor_id']}?bulan={$bulan}",
                        ]);
                    }
                }
            });
        } catch (UniqueConstraintViolationException) {
            // Two admins finalizing the same month at once: the second loses.
            throw ValidationException::withMessages(['bulan' => "Periode gaji {$label} sudah difinalisasi."]);
        }

        return response()->json([
            'message' => "Proses gaji untuk periode {$label} telah berhasil difinalisasi.",
            'data' => $this->gaji->periode($bulan),
        ], 201);
    }

    /** PostgreSQL returns SUM() of numeric columns as strings; send numbers. */
    private static function numbers(object $row, array $ints, array $floats): object
    {
        foreach ($ints as $key) {
            $row->{$key} = (int) $row->{$key};
        }
        foreach ($floats as $key) {
            $row->{$key} = (float) $row->{$key};
        }

        return $row;
    }
}
