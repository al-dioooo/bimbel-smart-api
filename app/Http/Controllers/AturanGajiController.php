<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAturanGajiRequest;
use App\Http\Requests\UpdateAturanGajiRequest;
use App\Models\AturanGaji;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AturanGajiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = AturanGaji::with('kelas')
            ->join('kelas', 'aturan_gaji.kelas_id', '=', 'kelas.id')->select(['aturan_gaji.*', 'kelas.nama as kelas_nama']);
        $data = $this->paginate($query, $request->query('limit') ?? 10, $request->query('paginate'), $request->query('order_by') ?? "created_at", $request->query('direction') ?? "desc");

        return response()->json([
            'message' => 'Successfully get aturan gaji data.',
            'data' => $data
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAturanGajiRequest $request)
    {
        DB::beginTransaction();

        try {
            $aturanGaji = AturanGaji::create($request->validated());

            DB::commit();

            return response()->json([
                'message' => 'Successfully store aturan gaji data.',
                'data' => $aturanGaji
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to store aturan gaji data.',
                'error' => $e->getMessage()
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
            'data' => $aturanGaji
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAturanGajiRequest $request, AturanGaji $aturanGaji)
    {
        DB::beginTransaction();

        try {
            $aturanGaji->update($request->validated());

            DB::commit();

            return response()->json([
                'message' => 'Successfully update aturan gaji data.',
                'data' => $aturanGaji
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update aturan gaji data.',
                'error' => $e->getMessage()
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
            $aturanGaji->delete();

            DB::commit();

            return response()->json([
                'message' => 'Successfully delete aturan gaji data.'
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete aturan gaji data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
