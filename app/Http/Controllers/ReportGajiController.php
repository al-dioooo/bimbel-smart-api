<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Services\GajiCalculator;

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
            'data' => $this->mapRows($data, fn ($row) => self::numbers($row, ['tahun', 'bulan', 'total_kehadiran_murid'], ['gaji_pokok', 'bonus', 'potongan', 'nominal'])),
        ], 200);
    }

    /**
     * Per-kelas breakdown behind one mentor's monthly total, with the
     * adjustments of the same filter (all months, or ?bulan=).
     */
    public function show(ReportFilterRequest $request, $mentorId)
    {
        $this->authorizeOwner($mentorId);

        $data = $this->paginate(
            $this->gaji->rincianKelas((int) $mentorId, $request->only(['bulan', 'from', 'to'])),
            $request->query('limit') ?? 10,
            $request->query('paginate'),
            'tahun',
            'desc'
        );

        return response()->json([
            'message' => 'Successfully get report gaji detail.',
            'data' => $this->mapRows($data, fn ($row) => self::numbers($row, ['tahun', 'bulan', 'jumlah_kehadiran'], ['tarif', 'nominal'])),
            'penyesuaian' => $this->gaji->penyesuaian((int) $mentorId, $request->query('bulan')),
        ], 200);
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
