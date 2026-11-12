<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSiswaRequest;
use App\Http\Requests\UpdateSiswaRequest;
use App\Models\Absensi;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SiswaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Siswa::with(['kelas'])
            ->filter($request->only(['search', 'nama', 'kelas', 'kelas_id', 'mentor_id', 'no_kelas', 'from', 'to']));
        $data = $this->paginate($query, $request->query('limit') ?? 10, $request->query('paginate'), $request->query('order_by') ?? 'created_at', $request->query('direction') ?? 'desc');

        return response()->json([
            'message' => 'Data siswa berhasil diambil.',
            'data' => $data,
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
                'message' => 'Data siswa berhasil ditambahkan.',
                'data' => $siswa,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Data siswa gagal disimpan. Silakan coba lagi.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Siswa $siswa)
    {
        $this->authorizeOwner($siswa->kelas?->mentor_id);

        return response()->json([
            'message' => 'Data siswa berhasil diambil.',
            'data' => $siswa,
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
                'message' => 'Data siswa berhasil diperbarui.',
                'data' => $siswa,
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Data siswa gagal diperbarui. Silakan coba lagi.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Siswa $siswa)
    {
        // REQ-029: the attendance history (and the salary it paid) would lose its siswa.
        if (Absensi::where('siswa_id', $siswa->id)->exists()) {
            throw ValidationException::withMessages([
                'siswa' => 'Siswa tidak dapat dihapus karena sudah memiliki catatan absensi.',
            ]);
        }

        DB::beginTransaction();

        try {
            $siswa->delete();

            DB::commit();

            return response()->json([
                'message' => 'Data siswa berhasil dihapus.',
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Data siswa gagal dihapus. Silakan coba lagi.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
