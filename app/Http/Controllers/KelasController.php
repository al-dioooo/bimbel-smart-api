<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKelasRequest;
use App\Http\Requests\UpdateKelasRequest;
use App\Models\AturanGaji;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KelasController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Kelas::with(['mentor.user'])
            ->filter($request->only(['search', 'nama', 'tingkat', 'mentor_id', 'from', 'to']));
        $data = $this->paginate($query, $request->query('limit') ?? 10, $request->query('paginate'), $request->query('order_by') ?? 'created_at', $request->query('direction') ?? 'desc');

        return response()->json([
            'message' => 'Data kelas berhasil diambil.',
            'data' => $data,
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
            $aturanGaji = AturanGaji::create([
                'kelas_id' => $kelas->id,
                'tarif' => 30000,
            ]);

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
                'message' => 'Data kelas berhasil ditambahkan.',
                'data' => $kelas,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->serverError('Data kelas gagal disimpan. Silakan coba lagi.', $e);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Kelas $kelas)
    {
        $this->authorizeOwner($kelas->mentor_id);

        $kelas->load(['siswa']);

        return response()->json([
            'message' => 'Data kelas berhasil diambil.',
            'data' => $kelas,
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
                'message' => 'Data kelas berhasil diperbarui.',
                'data' => $kelas,
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->serverError('Data kelas gagal diperbarui. Silakan coba lagi.', $e);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Kelas $kelas)
    {
        // REQ-029: every absensi row hangs off a jadwal, so this covers both.
        if (Jadwal::where('kelas_id', $kelas->id)->exists()) {
            throw ValidationException::withMessages([
                'kelas' => 'Kelas tidak dapat dihapus karena masih memiliki jadwal atau absensi.',
            ]);
        }

        DB::beginTransaction();

        try {
            $kelas->delete();

            DB::commit();

            return response()->json([
                'message' => 'Data kelas berhasil dihapus.',
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->serverError('Data kelas gagal dihapus. Silakan coba lagi.', $e);
        }
    }
}
