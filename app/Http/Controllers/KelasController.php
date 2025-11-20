<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKelasRequest;
use App\Http\Requests\UpdateKelasRequest;
use App\Models\Kelas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Pail\Handler;

class KelasController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Kelas::filter($request->only(['search', 'nama', 'tingkat']));
        $data = $this->paginate($query, $request->query('limit') ?? 15, $request->query('paginate'), $request->query('order_by') ?? "created_at", $request->query('direction') ?? "desc");

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
            $kelas = Kelas::create($request->validated());

            DB::commit();

            return response()->json([
                'message' => 'Successfully store kelas data.',
                'data' => $kelas
            ], 201);
        } catch (Handler $e) {
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
            $kelas->update($request->validated());

            DB::commit();

            return response()->json([
                'message' => 'Successfully update kelas data.',
                'data' => $kelas
            ], 200);
        } catch (Handler $e) {
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
        } catch (Handler $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete kelas data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
