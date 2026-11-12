<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinalizeGajiRequest;
use App\Http\Requests\ReportFilterRequest;
use App\Models\Mentor;
use App\Models\Notification;
use App\Models\PenyesuaianGaji;
use App\Models\PeriodeGaji;
use App\Services\GajiCalculator;
use App\Support\Laporan;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
     * adjustments of the same filter (all months, or ?bulan=) and the totals
     * over every row of that filter (not just the page), for the admin and
     * the mentor alike. Finalized months come from the stored snapshot.
     */
    public function show(ReportFilterRequest $request, $mentorId)
    {
        $this->authorizeOwner($mentorId);

        if ($request->query('format')) {
            return $this->slip($request, (int) $mentorId);
        }

        $rows = $this->gaji->rincianKelas((int) $mentorId, $request->only(['bulan', 'from', 'to']))
            ->map(fn ($row) => self::numbers($row, ['kelas_id', 'tahun', 'bulan', 'jumlah_kehadiran'], ['tarif', 'nominal']));

        return response()->json([
            'message' => 'Successfully get report gaji detail.',
            'data' => $this->paginateCollection($rows, $request->query('limit') ?? 10, $request->query('paginate')),
            'penyesuaian' => $this->gaji->penyesuaian((int) $mentorId, $request->query('bulan')),
            'total' => $this->gaji->total(['mentor_id' => (int) $mentorId] + $request->only(['bulan', 'from', 'to'])),
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

        // There is no unfinalize, so a month whose sessions may still get
        // absensi cannot be closed.
        if (! Carbon::parse($periode)->isBefore(Carbon::today()->startOfMonth())) {
            $mulai = Carbon::parse($periode)->addMonth()->locale('id')->translatedFormat('j F Y');
            throw ValidationException::withMessages(['bulan' => "Periode {$label} belum berakhir. Finalisasi dapat dilakukan mulai {$mulai}."]);
        }

        $belumDiabsen = $this->gaji->jadwalBelumDiabsen($bulan);
        if ($belumDiabsen > 0 && ! $request->boolean('lanjutkan')) {
            throw ValidationException::withMessages([
                'lanjutkan' => "Peringatan: Terdapat {$belumDiabsen} sesi absensi yang belum terisi pada {$label}. Apakah Anda yakin ingin melanjutkan?",
            ]);
        }

        try {
            DB::transaction(function () use ($bulan, $periode, $label, $request) {
                // Writes to the month that already passed ensureOpen commit
                // first; later ones wait and then see the month as final.
                PeriodeGaji::lockForFinalize($periode);
                if (PeriodeGaji::finalFor($periode)) {
                    throw ValidationException::withMessages(['bulan' => "Periode gaji {$label} sudah difinalisasi."]);
                }

                $draft = $this->gaji->periode($bulan);
                $this->refuseUnfinalizable($draft, $label, $request);

                $final = PeriodeGaji::create([
                    'periode' => $periode,
                    'finalized_at' => now(),
                    'finalized_by' => $request->user()->id,
                    'jumlah_mentor' => $draft['total']['jumlah_mentor'],
                    'total_gaji' => round($draft['total']['total'], 2),
                ]);

                foreach ($draft['mentor'] as $row) {
                    $final->reports()->create([
                        'mentor_id' => $row['mentor_id'],
                        'mentor_nama' => $row['mentor'],
                        'bulan' => $periode,
                        'jumlah_kehadiran' => $row['total_kehadiran'],
                        'gaji_pokok' => round($row['gaji_pokok'], 2),
                        'bonus' => round($row['bonus'], 2),
                        'potongan' => round($row['potongan'], 2),
                        'total_gaji' => round($row['total'], 2),
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

    /**
     * REQ-061: the salary slip of one mentor for one month as a PDF, for the
     * admin (any mentor) and the mentor (own, checked in show()). Without
     * ?bulan= it is the current month.
     */
    private function slip(ReportFilterRequest $request, int $mentorId)
    {
        if ($request->query('format') !== 'pdf') {
            throw ValidationException::withMessages(['format' => 'Slip gaji hanya tersedia dalam format pdf.']);
        }

        $mentor = Mentor::with('user')->findOrFail($mentorId);
        $bulan = $request->query('bulan') ?? Carbon::today()->format('Y-m');
        $slip = $this->gaji->slip($mentorId, (string) $mentor->user?->name, $bulan);
        $slip['periode']['finalized_label'] = $slip['periode']['finalized_at']
            ? Carbon::parse($slip['periode']['finalized_at'])->locale('id')->translatedFormat('j F Y')
            : null;

        return Laporan::pdf('pdf.slip-gaji', $slip + [
            'dicetak' => Carbon::now()->locale('id')->translatedFormat('j F Y H:i'),
        ])->download('slip-gaji-'.Str::slug($slip['mentor']['mentor']).'-'.$bulan.'.pdf');
    }

    /**
     * Laporan gaji as PDF or Excel with the same filters as index(): one row
     * per mentor per month with gaji pokok, bonus, potongan, total, status.
     */
    public function export(ReportFilterRequest $request)
    {
        $request->validate(['format' => ['required', 'in:'.implode(',', Laporan::FORMATS)]], [
            'format.required' => 'Pilih format unduhan: pdf atau xlsx.',
            'format.in' => 'Format unduhan harus pdf atau xlsx.',
        ]);

        $filters = $request->only(['mentor_id', 'search', 'bulan', 'from', 'to']);
        $rows = $this->gaji->rekap($filters)
            ->orderByDesc('tahun')->orderByDesc('bulan')->orderBy('mentor')
            ->limit(Laporan::limit($request->query('format')))
            ->get()
            ->map(fn ($row) => [
                $row->mentor,
                PeriodeGaji::labelOf(sprintf('%04d-%02d-01', $row->tahun, $row->bulan)),
                (int) $row->total_kehadiran_murid,
                (float) $row->gaji_pokok,
                (float) $row->bonus,
                (float) $row->potongan,
                (float) $row->nominal,
                $row->status === 'final' ? 'Final' : 'Draf',
            ])->all();

        $sum = fn (int $i) => array_sum(array_column($rows, $i));

        return Laporan::table(
            $request->query('format'),
            'laporan-gaji'.(isset($filters['bulan']) ? '-'.$filters['bulan'] : ''),
            'Laporan Gaji Guru/Mentor',
            self::keterangan($filters),
            ['Guru/Mentor', 'Periode', 'Kehadiran', 'Gaji Pokok', 'Bonus', 'Potongan', 'Total', 'Status'],
            $rows,
            ['Total', '', $sum(2), $sum(3), $sum(4), $sum(5), $sum(6), ''],
            [3, 4, 5, 6],
        );
    }

    /** The active filters, in words, for the export header. */
    private static function keterangan(array $filters): array
    {
        return array_values(array_filter([
            isset($filters['bulan']) ? 'Periode: '.PeriodeGaji::labelOf($filters['bulan'].'-01') : null,
            isset($filters['from'], $filters['to']) ? "Tanggal: {$filters['from']} s.d. {$filters['to']}" : null,
            isset($filters['search']) ? "Pencarian: {$filters['search']}" : null,
        ])) ?: ['Periode: semua'];
    }

    /**
     * A finalized month can never change, so refuse one with nothing in it, a
     * negative salary, or figures that moved since the admin's preview.
     */
    private function refuseUnfinalizable(array $draft, string $label, FinalizeGajiRequest $request): void
    {
        if ($draft['mentor'] === []) {
            throw ValidationException::withMessages([
                'bulan' => "Tidak ada data gaji pada periode {$label}, sehingga belum ada yang dapat difinalisasi.",
            ]);
        }

        $negatif = collect($draft['mentor'])->where('total', '<', 0)->pluck('mentor');
        if ($negatif->isNotEmpty()) {
            throw ValidationException::withMessages([
                'bulan' => "Total gaji {$negatif->implode(', ')} pada {$label} negatif. Periksa potongan sebelum finalisasi.",
            ]);
        }

        $dilihat = $request->validated('total');
        if ($dilihat !== null && abs(round((float) $dilihat, 2) - round($draft['total']['total'], 2)) >= 0.005) {
            throw ValidationException::withMessages([
                'total' => "Data gaji {$label} berubah sejak pratinjau (total sekarang Rp"
                    .number_format($draft['total']['total'], 0, ',', '.').'). Muat ulang pratinjau sebelum finalisasi.',
            ]);
        }
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
