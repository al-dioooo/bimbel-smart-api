<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePengajuanJadwalRequest;
use App\Http\Requests\UpdatePengajuanJadwalRequest;
use App\Models\Jadwal;
use App\Models\PengajuanJadwal;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PengajuanJadwalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = PengajuanJadwal::with(['jadwal.kelas.mentor.user'])->filter($request->only(['search', 'mentor_id', 'status', 'from', 'to']));
        $data = $this->paginate($query, $request->query('limit') ?? 10, $request->query('paginate'), $request->query('order_by') ?? "created_at", $request->query('direction') ?? "desc");

        return response()->json([
            'message' => 'Successfully get pengajuan jadwal data.',
            'data' => $data
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePengajuanJadwalRequest $request)
    {
        $validated = $request->validated();
        $jadwal = Jadwal::with('kelas')->findOrFail($validated['jadwal_id']);

        $this->authorizeOwner($jadwal->kelas?->mentor_id);

        // A mentor's request always starts pending, and "before" is whatever
        // the jadwal actually says rather than what the client claims.
        if (! $request->user()->isAdmin()) {
            $validated = array_merge($validated, [
                'status' => 'pending',
                'tanggal_sebelum' => Carbon::parse($jadwal->tanggal)->toDateString(),
                'waktu_mulai_sebelum' => $jadwal->waktu_mulai,
                'waktu_selesai_sebelum' => $jadwal->waktu_selesai,
            ]);
        }

        DB::beginTransaction();

        try {
            $pengajuanJadwal = PengajuanJadwal::create($validated);

            DB::commit();

            return response()->json([
                'message' => 'Successfully store pengajuan jadwal data.',
                'data' => $pengajuanJadwal
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to store pengajuan jadwal data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(PengajuanJadwal $pengajuanJadwal)
    {
        $this->authorizeOwner($pengajuanJadwal->jadwal?->kelas?->mentor_id);

        return response()->json([
            'message' => 'Successfully get pengajuan jadwal data.',
            'data' => $pengajuanJadwal
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePengajuanJadwalRequest $request, PengajuanJadwal $pengajuanJadwal)
    {
        DB::beginTransaction();

        try {
            $pengajuanJadwal->update($request->validated());

            DB::commit();

            return response()->json([
                'message' => 'Successfully update pengajuan jadwal data.',
                'data' => $pengajuanJadwal
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update pengajuan jadwal data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, PengajuanJadwal $pengajuanJadwal)
    {
        // Mentors may only cancel their own request, and only while pending.
        if (! $request->user()->isAdmin()) {
            $this->authorizeOwner($pengajuanJadwal->jadwal?->kelas?->mentor_id);
            abort_unless($pengajuanJadwal->status === 'pending', 403, 'Only pending requests can be cancelled.');
        }

        DB::beginTransaction();

        try {
            $pengajuanJadwal->delete();

            DB::commit();

            return response()->json([
                'message' => 'Successfully delete pengajuan jadwal data.'
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete pengajuan jadwal data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
