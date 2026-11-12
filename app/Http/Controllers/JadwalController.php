<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJadwalRequest;
use App\Http\Requests\UpdateJadwalRequest;
use App\Models\Absensi;
use App\Models\Jadwal;
use App\Models\PeriodeGaji;
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
            // UC-10 step 9: a finalized month gets no new sessions.
            PeriodeGaji::ensureOpen($request->validated('tanggal'), 'tanggal', 'jadwal', 'tidak dapat ditambahkan pada periode tersebut');

            $jadwal = Jadwal::create($request->validated());

            DB::commit();

            return response()->json([
                'message' => 'Data jadwal berhasil ditambahkan.',
                'data' => $jadwal,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($e instanceof ValidationException) {
                throw $e;
            }

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
            // Again inside the transaction, where it holds the month's lock until commit.
            PeriodeGaji::ensureOpen($jadwal->tanggal, 'jadwal', 'jadwal ini');
            if ($request->filled('tanggal')) {
                PeriodeGaji::ensureOpen($request->input('tanggal'), 'tanggal', 'jadwal pada periode tersebut');
            }

            $jadwal->update($request->validated());

            // REQ-045: attendance carries its jadwal's date.
            if ($jadwal->wasChanged('tanggal')) {
                Absensi::where('jadwal_id', $jadwal->id)
                    ->update(['tanggal' => Carbon::parse($jadwal->tanggal)->toDateString()]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Data jadwal berhasil diperbarui.',
                'data' => $jadwal,
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($e instanceof ValidationException) {
                throw $e;
            }

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
            // Again inside the transaction, where it holds the month's lock until commit.
            PeriodeGaji::ensureOpen($jadwal->tanggal, 'jadwal', 'jadwal ini');

            $jadwal->delete();

            DB::commit();

            return response()->json([
                'message' => 'Data jadwal berhasil dihapus.',
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($e instanceof ValidationException) {
                throw $e;
            }

            // A row inserted after the check above: the foreign key refuses it.
            if ($this->isForeignKeyViolation($e)) {
                throw ValidationException::withMessages(['jadwal' => 'Jadwal tidak dapat dihapus karena sudah memiliki catatan absensi.']);
            }

            return $this->serverError('Data jadwal gagal dihapus. Silakan coba lagi.', $e);
        }
    }
}
