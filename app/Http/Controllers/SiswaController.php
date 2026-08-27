<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSiswaRequest;
use App\Http\Requests\UpdateSiswaRequest;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SiswaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Siswa::with(['kelas'])
            ->filter($request->only(['search', 'nama', 'kelas', 'kelas_id', 'no_kelas', 'from', 'to']));
        $data = $this->paginate($query, $request->query('limit') ?? 10, $request->query('paginate'), $request->query('order_by') ?? "created_at", $request->query('direction') ?? "desc");

        return response()->json([
            'message' => 'Successfully get siswa data.',
            'data' => $data
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSiswaRequest $request)
    {
        DB::beginTransaction();

        try {
            $siswa = Siswa::create($request->validated());

            DB::commit();

            return response()->json([
                'message' => 'Successfully store siswa$siswa data.',
                'data' => $siswa
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to store siswa data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Siswa $siswa)
    {
        return response()->json([
            'message' => 'Successfully get siswa data.',
            'data' => $siswa
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSiswaRequest $request, Siswa $siswa)
    {
        DB::beginTransaction();

        try {
            $siswa->update($request->validated());

            DB::commit();

            return response()->json([
                'message' => 'Successfully update siswa data.',
                'data' => $siswa
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update siswa data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Siswa $siswa)
    {
        DB::beginTransaction();

        try {
            $siswa->delete();

            DB::commit();

            return response()->json([
                'message' => 'Successfully delete siswa data.'
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete siswa data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
