<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKelasRequest;
use App\Http\Requests\UpdateKelasRequest;
use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KelasController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Kelas::with(['mentor.user'])
            ->filter($request->only(['search', 'nama', 'tingkat', 'from', 'to']));
        $data = $this->paginate($query, $request->query('limit') ?? 10, $request->query('paginate'), $request->query('order_by') ?? "created_at", $request->query('direction') ?? "desc");

        return response()->json([
            'message' => 'Successfully get kelas data.',
            'data' => $data
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreKelasRequest $request)
    {
        DB::beginTransaction();

        try {
            $kelas = Kelas::create($request->validatedExcept('siswa'));

            if ($request->has('siswa')) {
                $ids = $request->input('siswa', []);

                Siswa::whereIn('id', $ids)->update(['kelas_id' => $kelas->id]);
                // dan mungkin clear siswa lain yang sebelumnya di kelas ini
                Siswa::where('kelas_id', $kelas->id)
                    ->whereNotIn('id', $ids)
                    ->update(['kelas_id' => null]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Successfully store kelas data.',
                'data' => $kelas
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to store kelas data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Kelas $kelas)
    {
        $kelas->load(['siswa']);

        return response()->json([
            'message' => 'Successfully get kelas data.',
            'data' => $kelas
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateKelasRequest $request, Kelas $kelas)
    {
        DB::beginTransaction();

        try {
            $kelas->update($request->validatedExcept('siswa'));

            if ($request->has('siswa')) {
                $ids = $request->input('siswa', []);

                Siswa::whereIn('id', $ids)->update(['kelas_id' => $kelas->id]);
                // dan mungkin clear siswa lain yang sebelumnya di kelas ini
                Siswa::where('kelas_id', $kelas->id)
                    ->whereNotIn('id', $ids)
                    ->update(['kelas_id' => null]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Successfully update kelas data.',
                'data' => $kelas
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update kelas data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Kelas $kelas)
    {
        DB::beginTransaction();

        try {
            $kelas->delete();

            DB::commit();

            return response()->json([
                'message' => 'Successfully delete kelas data.'
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete kelas data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
