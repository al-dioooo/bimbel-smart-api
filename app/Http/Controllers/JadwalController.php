<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJadwalRequest;
use App\Http\Requests\UpdateJadwalRequest;
use App\Models\Absensi;
use App\Models\Jadwal;
use App\Models\PeriodeGaji;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JadwalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Jadwal::with('kelas.mentor.user')->filter($request->only(['search', 'kelas_id', 'mentor_id', 'from', 'to']));
        $data = $this->paginate($query, $request->query('limit') ?? 10, $request->query('paginate'), $request->query('order_by') ?? 'tanggal', $request->query('direction') ?? 'desc');

        return response()->json([
            'message' => 'Data jadwal berhasil diambil.',
            'data' => $data,
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
                'message' => 'Data jadwal berhasil ditambahkan.',
                'data' => $jadwal,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->serverError('Data jadwal gagal disimpan. Silakan coba lagi.', $e);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Jadwal $jadwal)
    {
        $this->authorizeOwner($jadwal->kelas?->mentor_id);

        return response()->json([
            'message' => 'Data jadwal berhasil diambil.',
            'data' => $jadwal,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateJadwalRequest $request, Jadwal $jadwal)
    {
        // UC-10 step 9: neither a session of a finalized month nor a move into one.
        PeriodeGaji::ensureOpen($jadwal->tanggal, 'jadwal', 'jadwal ini');
        if ($request->filled('tanggal')) {
            PeriodeGaji::ensureOpen($request->input('tanggal'), 'tanggal', 'jadwal pada periode tersebut');
        }

        DB::beginTransaction();

        try {
            $jadwal->update($request->validated());

            DB::commit();

            return response()->json([
                'message' => 'Data jadwal berhasil diperbarui.',
                'data' => $jadwal,
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->serverError('Data jadwal gagal diperbarui. Silakan coba lagi.', $e);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Jadwal $jadwal)
    {
        PeriodeGaji::ensureOpen($jadwal->tanggal, 'jadwal', 'jadwal ini');

        // REQ-040: attendance hangs off the jadwal and feeds the salary report.
        if (Absensi::where('jadwal_id', $jadwal->id)->exists()) {
            throw ValidationException::withMessages([
                'jadwal' => 'Jadwal tidak dapat dihapus karena sudah memiliki catatan absensi.',
            ]);
        }

        DB::beginTransaction();

        try {
            $jadwal->delete();

            DB::commit();

            return response()->json([
                'message' => 'Data jadwal berhasil dihapus.',
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->serverError('Data jadwal gagal dihapus. Silakan coba lagi.', $e);
        }
    }
}
