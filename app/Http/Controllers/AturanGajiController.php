<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAturanGajiRequest;
use App\Http\Requests\UpdateAturanGajiRequest;
use App\Models\Absensi;
use App\Models\AturanGaji;
use App\Models\Kelas;
use App\Support\UniqueViolation;
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
            'message' => 'Successfully get aturan gaji data.',
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
                'message' => 'Successfully store aturan gaji data.',
                'data' => $aturanGaji,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            UniqueViolation::rethrow($e);

            return response()->json([
                'message' => 'Failed to store aturan gaji data.',
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
            'message' => 'Successfully get aturan gaji data.',
            'data' => $aturanGaji,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAturanGajiRequest $request, AturanGaji $aturanGaji)
    {
        // Moving the tarif to another kelas would leave this kelas's hadir
        // without a tarif, which a delete is refused for.
        if ($request->has('kelas_id') && (int) $request->input('kelas_id') !== (int) $aturanGaji->kelas_id
            && self::kelasHasKehadiran($aturanGaji->kelas_id)) {
            throw ValidationException::withMessages([
                'kelas_id' => 'Aturan gaji tidak dapat dipindahkan ke kelas lain karena kelas ini sudah memiliki kehadiran yang dihitung dalam gaji.',
            ]);
        }

        DB::beginTransaction();

        try {
            $aturanGaji->update($request->validated());

            DB::commit();

            return response()->json([
                'message' => 'Successfully update aturan gaji data.',
                'data' => $aturanGaji,
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            UniqueViolation::rethrow($e);

            return response()->json([
                'message' => 'Failed to update aturan gaji data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AturanGaji $aturanGaji)
    {
        DB::beginTransaction();

        try {
            // Every `hadir` of the kelas is paid at this tarif; without it those
            // sessions would silently drop out of the salary report. The kelas
            // row is locked so absensi stored meanwhile (which takes a shared
            // lock on it) cannot slip in between the check and the delete.
            if (self::kelasHasKehadiran($aturanGaji->kelas_id, lock: true)) {
                DB::rollBack();

                throw ValidationException::withMessages([
                    'aturan_gaji' => 'Aturan gaji tidak dapat dihapus karena kelas ini sudah memiliki kehadiran yang dihitung dalam gaji.',
                ]);
            }

            $aturanGaji->delete();

            DB::commit();

            return response()->json([
                'message' => 'Successfully delete aturan gaji data.',
            ], 200);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete aturan gaji data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /** Whether the kelas has `hadir` attendance; optionally locking the kelas row first. */
    private static function kelasHasKehadiran(?int $kelasId, bool $lock = false): bool
    {
        if ($kelasId === null) {
            return false;
        }

        if ($lock) {
            Kelas::whereKey($kelasId)->lockForUpdate()->first();
        }

        return Absensi::where('status', 'h')
            ->whereHas('jadwal', fn ($query) => $query->where('kelas_id', $kelasId))
            ->exists();
    }
}
