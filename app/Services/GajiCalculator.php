<?php

namespace App\Services;

use App\Models\PenyesuaianGaji;
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
 * Filters, all optional: mentor_id, search (mentor name), bulan (YYYY-MM),
 * from and to (Y-m-d, together).
 */
class GajiCalculator
{
    /**
     * One row per mentor per month: mentor_id, mentor, tahun, bulan,
     * total_kehadiran_murid, gaji_pokok, bonus, potongan, nominal.
     */
    public function rekap(array $filters = []): Builder
    {
        $rows = $this->kehadiranPerBulan($filters)->unionAll($this->penyesuaianPerBulan($filters));

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
                SUM(rekap.gaji_pokok) + SUM(rekap.bonus) - SUM(rekap.potongan) as nominal
            ')
            ->groupBy('rekap.mentor_id', 'rekap.mentor', 'rekap.tahun', 'rekap.bulan');
    }

    /**
     * One row per kelas per month for a mentor: kelas_id, kelas, mentor,
     * tahun, bulan, tarif, jumlah_kehadiran, nominal.
     */
    public function rincianKelas(int $mentorId, array $filters = []): Builder
    {
        return $this->filterJadwal($this->jadwalHadir(), ['mentor_id' => $mentorId] + $filters)
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

    /** Σ(hadir × tarif) per mentor per month. */
    private function kehadiranPerBulan(array $filters): Builder
    {
        return $this->filterJadwal($this->jadwalHadir(), $filters)
            ->selectRaw('
                mentor.id as mentor_id,
                users.name as mentor,
                '.Sql::year('jadwal.tanggal').' as tahun,
                '.Sql::month('jadwal.tanggal').' as bulan,
                COUNT(absensi.id) as total_kehadiran_murid,
                COALESCE(SUM(CASE WHEN absensi.id IS NOT NULL THEN aturan_gaji.tarif ELSE 0 END), 0) as gaji_pokok,
                0 as bonus,
                0 as potongan
            ')
            ->groupBy('mentor.id', 'users.name', 'tahun', 'bulan');
    }

    /** Σ bonus and Σ potongan per mentor per month. */
    private function penyesuaianPerBulan(array $filters): Builder
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
                SUM(CASE WHEN penyesuaian_gaji.jenis = 'potongan' THEN penyesuaian_gaji.nominal ELSE 0 END) as potongan
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

        return $query;
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

    private function filterJadwal(Builder $query, array $filters): Builder
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

        return $query;
    }
}
