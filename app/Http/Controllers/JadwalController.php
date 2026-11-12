<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJadwalRequest;
use App\Http\Requests\UpdateJadwalRequest;
use App\Models\Absensi;
use App\Models\Jadwal;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            'message' => 'Successfully get jadwal data.',
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
                'message' => 'Successfully store jadwal data.',
                'data' => $jadwal,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to store jadwal data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Jadwal $jadwal)
    {
        $this->authorizeOwner($jadwal->kelas?->mentor_id);

        return response()->json([
            'message' => 'Successfully get jadwal data.',
            'data' => $jadwal,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateJadwalRequest $request, Jadwal $jadwal)
    {
        // Moving a recorded session to another kelas would move its salary
        // and free the old kelas for deletion (REQ-029).
        if ($request->has('kelas_id') && (int) $request->input('kelas_id') !== (int) $jadwal->kelas_id
            && Absensi::where('jadwal_id', $jadwal->id)->exists()) {
            throw ValidationException::withMessages([
                'kelas_id' => 'Kelas jadwal tidak dapat diubah karena jadwal ini sudah memiliki catatan absensi.',
            ]);
        }

        DB::beginTransaction();

        try {
            $jadwal->update($request->validated());

            // REQ-045: attendance carries its jadwal's date.
            if ($jadwal->wasChanged('tanggal')) {
                Absensi::where('jadwal_id', $jadwal->id)
                    ->update(['tanggal' => Carbon::parse($jadwal->tanggal)->toDateString()]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Successfully update jadwal data.',
                'data' => $jadwal,
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update jadwal data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Jadwal $jadwal)
    {
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
                'message' => 'Successfully delete jadwal data.',
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            // A row inserted after the check above: the foreign key refuses it.
            if ($this->isForeignKeyViolation($e)) {
                throw ValidationException::withMessages(['jadwal' => 'Jadwal tidak dapat dihapus karena sudah memiliki catatan absensi.']);
            }

            return response()->json([
                'message' => 'Failed to delete jadwal data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
