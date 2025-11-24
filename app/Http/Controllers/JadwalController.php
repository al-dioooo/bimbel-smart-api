<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJadwalRequest;
use App\Http\Requests\UpdateJadwalRequest;
use App\Models\Jadwal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JadwalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Jadwal::filter($request->only(['search', 'mentor_id', 'kelas_id', 'from', 'to']));
        $data = $this->paginate($query, $request->query('limit') ?? 10, $request->query('paginate'), $request->query('order_by') ?? "created_at", $request->query('direction') ?? "desc");

        return response()->json([
            'message' => 'Successfully get jadwal data.',
            'data' => $data
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreJadwalRequest $request)
    {
        DB::beginTransaction();

        try {
            $jadwal = Jadwal::create($request->validated());

            DB::commit();

            return response()->json([
                'message' => 'Successfully store jadwal data.',
                'data' => $jadwal
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to store jadwal data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Jadwal $jadwal)
    {
        return response()->json([
            'message' => 'Successfully get jadwal data.',
            'data' => $jadwal
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateJadwalRequest $request, Jadwal $jadwal)
    {
        DB::beginTransaction();

        try {
            $jadwal->update($request->validated());

            DB::commit();

            return response()->json([
                'message' => 'Successfully update jadwal data.',
                'data' => $jadwal
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update jadwal data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Jadwal $jadwal)
    {
        DB::beginTransaction();

        try {
            $jadwal->delete();

            DB::commit();

            return response()->json([
                'message' => 'Successfully delete jadwal data.'
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete jadwal data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
