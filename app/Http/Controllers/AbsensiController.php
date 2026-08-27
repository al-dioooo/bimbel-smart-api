<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAbsensiRequest;
use App\Http\Requests\UpdateAbsensiRequest;
use App\Models\Absensi;
use App\Models\Jadwal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AbsensiController extends Controller
{
    public function index(Request $request)
    {
        $query = Absensi::with(['jadwal.kelas', 'siswa'])
            ->filter($request->only(['search', 'siswa_id', 'jadwal_id', 'kelas_id', 'status', 'from', 'to']));

        $data = $this->paginate(
            $query,
            $request->query('limit') ?? 10,
            $request->query('paginate') ?? 'false',
            $request->query('order_by') ?? 'tanggal',
            $request->query('direction') ?? 'asc'
        );

        return response()->json([
            'message' => 'Successfully get absensi data.',
            'data' => $data
        ]);
    }

    /**
     * Bulk upsert for one jadwal — the attendance grid saves a whole column at once.
     *
     * The UI works in uppercase (H/S/I/A); the column is a lowercase char(1).
     * Normalise here so the boundary is the only place that knows about both.
     */
    public function store(StoreAbsensiRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $jadwal = Jadwal::findOrFail($validated['jadwal_id']);
            $tanggal = $validated['tanggal'] ?? $jadwal->tanggal;

            foreach ($validated['absensi'] as $row) {
                Absensi::updateOrCreate(
                    [
                        'jadwal_id' => $jadwal->id,
                        'siswa_id'  => $row['siswa_id'],
                    ],
                    [
                        'tanggal' => $tanggal,
                        'status'  => strtolower($row['status']),
                    ]
                );
            }

            DB::commit();

            $data = Absensi::with('siswa')
                ->where('jadwal_id', $jadwal->id)
                ->get();

            return response()->json([
                'message' => 'Successfully store absensi data.',
                'data' => $data
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to store absensi data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Absensi $absensi)
    {
        $absensi->load(['jadwal.kelas', 'siswa']);

        return response()->json([
            'message' => 'Successfully get absensi data.',
            'data' => $absensi
        ]);
    }

    public function update(UpdateAbsensiRequest $request, Absensi $absensi)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validated();
            if (isset($validated['status'])) {
                $validated['status'] = strtolower($validated['status']);
            }

            $absensi->update($validated);

            DB::commit();

            return response()->json([
                'message' => 'Successfully update absensi data.',
                'data' => $absensi
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update absensi data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Absensi $absensi)
    {
        DB::beginTransaction();

        try {
            $absensi->delete();

            DB::commit();

            return response()->json([
                'message' => 'Successfully delete absensi data.'
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete absensi data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
