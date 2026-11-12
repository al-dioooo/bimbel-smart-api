<?php

namespace App\Services;

use App\Models\Jadwal;
use App\Models\PenyesuaianGaji;
use App\Models\PeriodeGaji;
use App\Models\ReportGaji;
use App\Support\Sql;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The salary calculation (REQ-054, REQ-055, REQ-064) in one place, so the
 * laporan gaji, the per-kelas detail, the period preview and the slip all
 * agree.
 *
 * A mentor's rate lives on aturan_gaji, keyed by kelas, and is earned per
 * student attendance: every `hadir` under a jadwal of a kelas the mentor
 * teaches adds that kelas's tarif (gaji pokok). Manual adjustments of the
 * month are then added (bonus) or subtracted (potongan).
 *
 * Note jadwal has no mentor_id; the mentor hangs off kelas.
 *
 * A finalized month (UC-10 step 9) is never recomputed: it is read from the
 * report_gaji snapshot, so later changes such as moving a kelas to another
 * mentor or a new tarif cannot rewrite a closed payroll. Every finalized month
 * the filters touch is swapped for its whole stored month, also when a
 * from/to range only cuts through it (as adjustments already count per month).
 *
 * Filters, all optional: mentor_id, search (mentor name), bulan (YYYY-MM),
 * from and to (Y-m-d, together).
 */
class GajiCalculator
{
    /**
     * One row per mentor per month: mentor_id, mentor, tahun, bulan,
     * total_kehadiran_murid, gaji_pokok, bonus, potongan, nominal, status
     * (draft or final).
     */
    public function rekap(array $filters = []): Builder
    {
        $final = $this->finalPeriods($filters);

        $rows = $this->kehadiranPerBulan($filters, $final)
            ->unionAll($this->penyesuaianPerBulan($filters, $final))
            ->unionAll($this->snapshotPerBulan($filters, $final));

        return DB::query()
            ->fromSub($rows, 'rekap')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('rekap.mentor', Sql::like(), '%'.$search.'%'))
            ->selectRaw('
                rekap.mentor_id,
                rekap.mentor,
                rekap.tahun,
                rekap.bulan,
                SUM(rekap.total_kehadiran_murid) as total_kehadiran_murid,
                SUM(rekap.gaji_pokok) as gaji_pokok,
                SUM(rekap.bonus) as bonus,
                SUM(rekap.potongan) as potongan,
                SUM(rekap.gaji_pokok) + SUM(rekap.bonus) - SUM(rekap.potongan) as nominal,
                MAX(rekap.status) as status
            ')
            ->groupBy('rekap.mentor_id', 'rekap.mentor', 'rekap.tahun', 'rekap.bulan');
    }

    /**
     * One row per kelas per month for a mentor, newest month first:
     * kelas_id, kelas, mentor, tahun, bulan, tarif, jumlah_kehadiran,
     * nominal, status.
     *
     * @return Collection<int, object>
     */
    public function rincianKelas(int $mentorId, array $filters = []): Collection
    {
        $final = $this->finalPeriods($filters);

        $live = $this->rincianKelasLive($mentorId, $filters, $final)->get()
            ->map(fn ($row) => (object) ((array) $row + ['status' => 'draft']));

        $snapshot = ReportGaji::query()
            ->where('mentor_id', $mentorId)
            ->whereIn('periode_gaji_id', self::whereDateIn(PeriodeGaji::query(), 'periode', $final)->select('id'))
            ->get()
            ->flatMap(fn (ReportGaji $report) => collect($report->rincian['kelas'] ?? [])->map(fn ($kelas) => (object) [
                'kelas_id' => $kelas['kelas_id'],
                'kelas' => $kelas['kelas'],
                'mentor' => $report->mentor_nama,
                'tahun' => $report->bulan->year,
                'bulan' => $report->bulan->month,
                'tarif' => $kelas['tarif'],
                'jumlah_kehadiran' => $kelas['jumlah_kehadiran'],
                'nominal' => $kelas['nominal'],
                'status' => 'final',
            ]));

        return $live->concat($snapshot)
            ->sortBy([['tahun', 'desc'], ['bulan', 'desc'], ['kelas', 'asc']])
            ->values();
    }

    /**
     * The sums of rekap() for the same filters: gaji_pokok, bonus, potongan
     * and total (what is paid). Used where one mentor's total is shown, so
     * it is the same figure as the laporan gaji and the slip.
     *
     * @return array{gaji_pokok: float, bonus: float, potongan: float, total: float}
     */
    public function total(array $filters = []): array
    {
        $sum = DB::query()->fromSub($this->rekap($filters), 'gaji')
            ->selectRaw('COALESCE(SUM(gaji_pokok), 0) as gaji_pokok, COALESCE(SUM(bonus), 0) as bonus, COALESCE(SUM(potongan), 0) as potongan, COALESCE(SUM(nominal), 0) as total')
            ->first();

        return [
            'gaji_pokok' => (float) $sum->gaji_pokok,
            'bonus' => (float) $sum->bonus,
            'potongan' => (float) $sum->potongan,
            'total' => (float) $sum->total,
        ];
    }

    /**
     * The adjustments of one mentor, newest month first.
     *
     * @return Collection<int, PenyesuaianGaji>
     */
    public function penyesuaian(int $mentorId, ?string $bulan = null): Collection
    {
        return PenyesuaianGaji::query()
            ->where('mentor_id', $mentorId)
            ->when($bulan, fn ($query) => $query->whereDate('periode', PenyesuaianGaji::periodeOf($bulan)))
            ->orderByDesc('periode')
            ->orderBy('id')
            ->get();
    }

    /**
     * Everything UC-10 shows for one month: per mentor the sessions, tarif,
     * adjustments and total, the grand total, and how many past sessions of
     * the month still have no absensi (UC-10 3a). A finalized month comes
     * from its snapshot.
     */
    public function periode(string $bulan): array
    {
        $periode = PeriodeGaji::with('finalizer')->whereDate('periode', PenyesuaianGaji::periodeOf($bulan))->first();

        $mentor = $periode
            ? $periode->reports()->orderBy('mentor_nama')->get()->map(fn (ReportGaji $report) => [
                'mentor_id' => $report->mentor_id,
                'mentor' => $report->mentor_nama,
                'total_kehadiran' => $report->jumlah_kehadiran,
                'gaji_pokok' => $report->gaji_pokok,
                'bonus' => $report->bonus,
                'potongan' => $report->potongan,
                'total' => $report->total_gaji,
                'rincian' => $report->rincian['kelas'] ?? [],
                'penyesuaian' => $report->rincian['penyesuaian'] ?? [],
            ])
            : $this->rekap(['bulan' => $bulan])->orderBy('mentor')->get()->map(fn ($row) => [
                'mentor_id' => (int) $row->mentor_id,
                'mentor' => $row->mentor,
                'total_kehadiran' => (int) $row->total_kehadiran_murid,
                'gaji_pokok' => (float) $row->gaji_pokok,
                'bonus' => (float) $row->bonus,
                'potongan' => (float) $row->potongan,
                'total' => (float) $row->nominal,
                'rincian' => $this->rincianKelasLive((int) $row->mentor_id, ['bulan' => $bulan], [])->orderBy('kelas')->get()
                    ->map(fn ($kelas) => [
                        'kelas_id' => (int) $kelas->kelas_id,
                        'kelas' => $kelas->kelas,
                        'tarif' => (float) $kelas->tarif,
                        'jumlah_kehadiran' => (int) $kelas->jumlah_kehadiran,
                        'nominal' => (float) $kelas->nominal,
                    ])->all(),
                'penyesuaian' => $this->penyesuaian((int) $row->mentor_id, $bulan)
                    ->map(fn (PenyesuaianGaji $p) => $p->only(['id', 'jenis', 'nominal', 'keterangan']))->values()->all(),
            ]);

        return [
            'bulan' => $bulan,
            'label' => PeriodeGaji::labelOf(PenyesuaianGaji::periodeOf($bulan)),
            'status' => $periode ? 'final' : 'draft',
            'finalized_at' => $periode?->finalized_at?->toIso8601String(),
            'finalized_by' => $periode?->finalizer?->name,
            'mentor' => $mentor->values()->all(),
            'total' => [
                'jumlah_mentor' => $mentor->count(),
                'total_kehadiran' => $mentor->sum('total_kehadiran'),
                'gaji_pokok' => (float) $mentor->sum('gaji_pokok'),
                'bonus' => (float) $mentor->sum('bonus'),
                'potongan' => (float) $mentor->sum('potongan'),
                'total' => (float) $mentor->sum('total'),
            ],
            'jadwal_belum_diabsen' => $this->jadwalBelumDiabsen($bulan),
        ];
    }

    /**
     * UC-10 3a: sessions of the month up to today whose attendance is missing
     * or incomplete, by the same rule as the mentor's reminders.
     */
    public function jadwalBelumDiabsen(string $bulan): int
    {
        [$year, $month] = array_map('intval', explode('-', $bulan));

        return Jadwal::query()
            ->whereYear('tanggal', $year)
            ->whereMonth('tanggal', $month)
            ->whereDate('tanggal', '<=', Carbon::today()->toDateString())
            ->absensiBelumLengkap()
            ->count();
    }

    /**
     * Finalized months the filters touch, as first-day dates.
     *
     * @return array<int, string>
     */
    private function finalPeriods(array $filters): array
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        return PeriodeGaji::query()
            ->when($filters['bulan'] ?? null, fn ($query, $bulan) => $query->whereDate('periode', PenyesuaianGaji::periodeOf($bulan)))
            ->pluck('periode')
            ->map(fn ($periode) => Carbon::parse($periode)->toDateString())
            ->filter(fn (string $periode) => ! ($from && $to)
                || ($periode <= $to && Carbon::parse($periode)->endOfMonth()->toDateString() >= $from))
            ->values()
            ->all();
    }

    /** Σ(hadir × tarif) per mentor per month, open months only. */
    private function kehadiranPerBulan(array $filters, array $final): Builder
    {
        return $this->filterJadwal($this->jadwalHadir(), $filters, $final)
            ->selectRaw('
                mentor.id as mentor_id,
                users.name as mentor,
                '.Sql::year('jadwal.tanggal').' as tahun,
                '.Sql::month('jadwal.tanggal')." as bulan,
                COUNT(absensi.id) as total_kehadiran_murid,
                COALESCE(SUM(CASE WHEN absensi.id IS NOT NULL THEN aturan_gaji.tarif ELSE 0 END), 0) as gaji_pokok,
                0 as bonus,
                0 as potongan,
                'draft' as status
            ")
            ->groupBy('mentor.id', 'users.name', 'tahun', 'bulan');
    }

    /** Σ bonus and Σ potongan per mentor per month, open months only. */
    private function penyesuaianPerBulan(array $filters, array $final): Builder
    {
        $query = DB::table('penyesuaian_gaji')
            ->join('mentor', 'mentor.id', '=', 'penyesuaian_gaji.mentor_id')
            ->join('users', 'users.id', '=', 'mentor.user_id')
            ->selectRaw('
                mentor.id as mentor_id,
                users.name as mentor,
                '.Sql::year('penyesuaian_gaji.periode').' as tahun,
                '.Sql::month('penyesuaian_gaji.periode')." as bulan,
                0 as total_kehadiran_murid,
                0 as gaji_pokok,
                SUM(CASE WHEN penyesuaian_gaji.jenis = 'bonus' THEN penyesuaian_gaji.nominal ELSE 0 END) as bonus,
                SUM(CASE WHEN penyesuaian_gaji.jenis = 'potongan' THEN penyesuaian_gaji.nominal ELSE 0 END) as potongan,
                'draft' as status
            ")
            ->groupBy('mentor.id', 'users.name', 'tahun', 'bulan');

        if ($mentorId = $filters['mentor_id'] ?? null) {
            $query->where('mentor.id', $mentorId);
        }

        if ($bulan = $filters['bulan'] ?? null) {
            $query->whereDate('penyesuaian_gaji.periode', PenyesuaianGaji::periodeOf($bulan));
        }

        // A month's adjustment counts when the range touches that month.
        if (($from = $filters['from'] ?? null) && ($to = $filters['to'] ?? null)) {
            $query->whereDate('penyesuaian_gaji.periode', '>=', Carbon::parse($from)->startOfMonth()->toDateString())
                ->whereDate('penyesuaian_gaji.periode', '<=', $to);
        }

        foreach ($final as $periode) {
            $query->whereDate('penyesuaian_gaji.periode', '<>', $periode);
        }

        return $query;
    }

    /** The stored rows of finalized months. */
    private function snapshotPerBulan(array $filters, array $final): Builder
    {
        return DB::table('report_gaji')
            ->join('periode_gaji', 'periode_gaji.id', '=', 'report_gaji.periode_gaji_id')
            ->tap(fn ($query) => self::whereDateIn($query, 'periode_gaji.periode', $final))
            ->when($filters['mentor_id'] ?? null, fn ($query, $mentorId) => $query->where('report_gaji.mentor_id', $mentorId))
            ->selectRaw('
                report_gaji.mentor_id,
                report_gaji.mentor_nama as mentor,
                '.Sql::year('periode_gaji.periode').' as tahun,
                '.Sql::month('periode_gaji.periode')." as bulan,
                report_gaji.jumlah_kehadiran as total_kehadiran_murid,
                report_gaji.gaji_pokok,
                report_gaji.bonus,
                report_gaji.potongan,
                'final' as status
            ");
    }

    /** Per kelas per month for a mentor, computed from absensi, open months only. */
    private function rincianKelasLive(int $mentorId, array $filters, array $final): Builder
    {
        return $this->filterJadwal($this->jadwalHadir(), ['mentor_id' => $mentorId] + $filters, $final)
            ->selectRaw('
                kelas.id as kelas_id,
                kelas.nama as kelas,
                users.name as mentor,
                '.Sql::year('jadwal.tanggal').' as tahun,
                '.Sql::month('jadwal.tanggal').' as bulan,
                COALESCE(MAX(aturan_gaji.tarif), 0) as tarif,
                COUNT(absensi.id) as jumlah_kehadiran,
                COALESCE(SUM(CASE WHEN absensi.id IS NOT NULL THEN aturan_gaji.tarif ELSE 0 END), 0) as nominal
            ')
            ->groupBy('kelas.id', 'kelas.nama', 'users.name', 'tahun', 'bulan');
    }

    /** Every jadwal with its kelas, mentor, tarif and `hadir` rows. */
    private function jadwalHadir(): Builder
    {
        return DB::table('jadwal')
            ->join('kelas', 'kelas.id', '=', 'jadwal.kelas_id')
            ->join('mentor', 'mentor.id', '=', 'kelas.mentor_id')
            ->join('users', 'users.id', '=', 'mentor.user_id')
            ->leftJoin('aturan_gaji', 'aturan_gaji.kelas_id', '=', 'jadwal.kelas_id')
            ->leftJoin('absensi', function ($join) {
                $join->on('absensi.jadwal_id', '=', 'jadwal.id')
                    ->where('absensi.status', '=', 'h');
            });
    }

    /**
     * whereIn for date columns. SQLite keeps Eloquent dates as "Y-m-d H:i:s"
     * text, so a plain whereIn on "Y-m-d" would never match there.
     *
     * @template T of \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     *
     * @param  T  $query
     * @return T
     */
    private static function whereDateIn($query, string $column, array $dates)
    {
        return $query->where(function ($query) use ($column, $dates) {
            $query->whereRaw('1 = 0');
            foreach ($dates as $date) {
                $query->orWhereDate($column, $date);
            }
        });
    }

    private function filterJadwal(Builder $query, array $filters, array $final): Builder
    {
        if ($mentorId = $filters['mentor_id'] ?? null) {
            $query->where('mentor.id', $mentorId);
        }

        if ($bulan = $filters['bulan'] ?? null) {
            [$year, $month] = array_map('intval', explode('-', $bulan));
            $query->whereYear('jadwal.tanggal', $year)->whereMonth('jadwal.tanggal', $month);
        }

        if (($from = $filters['from'] ?? null) && ($to = $filters['to'] ?? null)) {
            $query->whereDate('jadwal.tanggal', '>=', $from)
                ->whereDate('jadwal.tanggal', '<=', $to);
        }

        foreach ($final as $periode) {
            $end = Carbon::parse($periode)->endOfMonth()->toDateString();
            $query->where(fn ($query) => $query->whereDate('jadwal.tanggal', '<', $periode)->orWhereDate('jadwal.tanggal', '>', $end));
        }

        return $query;
    }
}
