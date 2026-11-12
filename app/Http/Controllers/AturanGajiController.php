<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAturanGajiRequest;
use App\Http\Requests\UpdateAturanGajiRequest;
use App\Models\Absensi;
use App\Models\AturanGaji;
use App\Models\PeriodeGaji;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AturanGajiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = AturanGaji::with('kelas')
            ->join('kelas', 'aturan_gaji.kelas_id', '=', 'kelas.id')
            ->select(['aturan_gaji.*', 'kelas.nama as kelas_nama'])
            ->filter($request->only(['search', 'kelas_id']));
        $data = $this->paginate($query, $request->query('limit') ?? 10, $request->query('paginate'), $request->query('order_by') ?? 'created_at', $request->query('direction') ?? 'desc');

        return response()->json([
            'message' => 'Data aturan gaji berhasil diambil.',
            'data' => $data,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAturanGajiRequest $request)
    {
        // UC-11: a bonus/potongan rule is a penyesuaian gaji for one mentor and month.
        if ($request->isPenyesuaian()) {
            return response()->json([
                'message' => 'Penyesuaian gaji berhasil disimpan.',
                'data' => PenyesuaianGajiController::create($request->validated(), $request->user()->id),
            ], 201);
        }

        DB::beginTransaction();

        try {
            $aturanGaji = AturanGaji::create($request->safe()->except('jenis'));

            DB::commit();

            return response()->json([
                'message' => 'Data aturan gaji berhasil ditambahkan.',
                'data' => $aturanGaji,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Data aturan gaji gagal disimpan. Silakan coba lagi.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(AturanGaji $aturanGaji)
    {
        return response()->json([
            'message' => 'Data aturan gaji berhasil diambil.',
            'data' => $aturanGaji,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAturanGajiRequest $request, AturanGaji $aturanGaji)
    {
        // UC-10 step 9: a tarif that paid a finalized month is closed with it.
        $changes = collect($request->validated())->filter(fn ($value, $key) => (string) $value !== (string) $aturanGaji->{$key});
        if ($changes->isNotEmpty()) {
            foreach (array_unique(array_filter([$aturanGaji->kelas_id, $changes->get('kelas_id')])) as $kelasId) {
                if ($periode = PeriodeGaji::usingKelas((int) $kelasId)) {
                    throw ValidationException::withMessages([
                        'aturan_gaji' => "Aturan gaji ini sudah dipakai pada periode gaji {$periode->label()} yang sudah difinalisasi, sehingga tidak dapat diubah.",
                    ]);
                }
            }
        }

        DB::beginTransaction();

        try {
            $aturanGaji->update($request->validated());

            DB::commit();

            return response()->json([
                'message' => 'Data aturan gaji berhasil diperbarui.',
                'data' => $aturanGaji,
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Data aturan gaji gagal diperbarui. Silakan coba lagi.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AturanGaji $aturanGaji)
    {
        // Every `hadir` of the kelas is paid at this tarif; without it those
        // sessions would silently drop out of the salary report.
        $hasKehadiran = Absensi::where('status', 'h')
            ->whereHas('jadwal', fn ($query) => $query->where('kelas_id', $aturanGaji->kelas_id))
            ->exists();

        if ($aturanGaji->kelas_id !== null && $hasKehadiran) {
            throw ValidationException::withMessages([
                'aturan_gaji' => 'Aturan gaji tidak dapat dihapus karena kelas ini sudah memiliki kehadiran yang dihitung dalam gaji.',
            ]);
        }

        DB::beginTransaction();

        try {
            $aturanGaji->delete();

            DB::commit();

            return response()->json([
                'message' => 'Data aturan gaji berhasil dihapus.',
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Data aturan gaji gagal dihapus. Silakan coba lagi.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
