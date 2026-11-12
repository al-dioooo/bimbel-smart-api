<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePenyesuaianGajiRequest;
use App\Http\Requests\UpdatePenyesuaianGajiRequest;
use App\Models\PenyesuaianGaji;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Manual bonus and potongan per mentor per month (REQ-064, UC-10 step 5).
 * Admin only; they are included in the laporan gaji and the slip.
 */
class PenyesuaianGajiController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['bulan' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']], StorePenyesuaianGajiRequest::penyesuaianMessages());

        $query = PenyesuaianGaji::with('mentor.user')
            ->when($request->query('mentor_id'), fn ($query, $mentorId) => $query->where('mentor_id', $mentorId))
            ->when($request->query('bulan'), fn ($query, $bulan) => $query->whereDate('periode', PenyesuaianGaji::periodeOf($bulan)))
            ->when($request->query('jenis'), fn ($query, $jenis) => $query->where('jenis', $jenis));

        $data = $this->paginate($query, $request->query('limit') ?? 10, $request->query('paginate'), 'periode', 'desc');

        return response()->json([
            'message' => 'Data penyesuaian gaji berhasil diambil.',
            'data' => $data,
        ]);
    }

    public function store(StorePenyesuaianGajiRequest $request)
    {
        $penyesuaian = self::create($request->validated(), $request->user()->id);

        return response()->json([
            'message' => 'Penyesuaian gaji berhasil disimpan.',
            'data' => $penyesuaian,
        ], 201);
    }

    public function show(PenyesuaianGaji $penyesuaianGaji)
    {
        return response()->json([
            'message' => 'Data penyesuaian gaji berhasil diambil.',
            'data' => $penyesuaianGaji->load('mentor.user'),
        ]);
    }

    public function update(UpdatePenyesuaianGajiRequest $request, PenyesuaianGaji $penyesuaianGaji)
    {
        $penyesuaianGaji->update(self::attributes($request->validated()));

        return response()->json([
            'message' => 'Penyesuaian gaji berhasil diperbarui.',
            'data' => $penyesuaianGaji->fresh('mentor.user'),
        ]);
    }

    public function destroy(PenyesuaianGaji $penyesuaianGaji)
    {
        $penyesuaianGaji->delete();

        return response()->json([
            'message' => 'Penyesuaian gaji berhasil dihapus.',
        ]);
    }

    /** Also used by the aturan gaji route for jenis bonus/potongan (UC-11). */
    public static function create(array $validated, ?int $userId): PenyesuaianGaji
    {
        return PenyesuaianGaji::create(self::attributes($validated) + ['created_by' => $userId])->load('mentor.user');
    }

    /** The API speaks YYYY-MM; the column stores the first day of the month. */
    private static function attributes(array $validated): array
    {
        if (isset($validated['bulan'])) {
            $validated['periode'] = PenyesuaianGaji::periodeOf($validated['bulan']);
        }

        return Arr::except($validated, ['bulan']);
    }
}
