<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePenyesuaianGajiRequest;
use App\Http\Requests\UpdatePenyesuaianGajiRequest;
use App\Models\PenyesuaianGaji;
use App\Models\PeriodeGaji;
use App\Services\GajiCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manual bonus and potongan per mentor per month (REQ-064, UC-10 step 5).
 * Admin only; they are included in the laporan gaji and the slip.
 */
class PenyesuaianGajiController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['bulan' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']], StorePenyesuaianGajiRequest::penyesuaianMessages());

        $query = PenyesuaianGaji::with('mentor.user')
            ->when($request->query('mentor_id'), fn ($query, $mentorId) => $query->where('mentor_id', $mentorId))
            ->when($request->query('bulan'), fn ($query, $bulan) => $query->whereDate('periode', PenyesuaianGaji::periodeOf($bulan)))
            ->when($request->query('jenis'), fn ($query, $jenis) => $query->where('jenis', $jenis));

        $data = $this->paginate($query, $request->query('limit') ?? 10, $request->query('paginate'), 'periode', 'desc');

        return response()->json([
            'message' => 'Data penyesuaian gaji berhasil diambil.',
            'data' => $data,
        ]);
    }

    public function store(StorePenyesuaianGajiRequest $request)
    {
        $penyesuaian = self::create($request->validated(), $request->user()->id);

        return response()->json([
            'message' => 'Penyesuaian gaji berhasil disimpan.',
            'data' => $penyesuaian,
        ], 201);
    }

    public function show(PenyesuaianGaji $penyesuaianGaji)
    {
        return response()->json([
            'message' => 'Data penyesuaian gaji berhasil diambil.',
            'data' => $penyesuaianGaji->load('mentor.user'),
        ]);
    }

    public function update(UpdatePenyesuaianGajiRequest $request, PenyesuaianGaji $penyesuaianGaji)
    {
        // In one transaction, so the month's lock is held until commit.
        DB::transaction(function () use ($request, $penyesuaianGaji) {
            PeriodeGaji::ensureOpen($penyesuaianGaji->periode, 'bulan', 'penyesuaian gaji ini');
            if ($request->filled('bulan')) {
                PeriodeGaji::ensureOpen(PenyesuaianGaji::periodeOf($request->input('bulan')), 'bulan', 'penyesuaian gaji periode tersebut');
            }

            $attributes = self::attributes($request->validated());
            self::refuseNegativeTotal($attributes + $penyesuaianGaji->only(['mentor_id', 'periode', 'jenis', 'nominal']), $penyesuaianGaji);

            $penyesuaianGaji->update($attributes);
        });

        return response()->json([
            'message' => 'Penyesuaian gaji berhasil diperbarui.',
            'data' => $penyesuaianGaji->fresh('mentor.user'),
        ]);
    }

    public function destroy(PenyesuaianGaji $penyesuaianGaji)
    {
        DB::transaction(function () use ($penyesuaianGaji) {
            PeriodeGaji::ensureOpen($penyesuaianGaji->periode, 'bulan', 'penyesuaian gaji ini');

            $penyesuaianGaji->delete();
        });

        return response()->json([
            'message' => 'Penyesuaian gaji berhasil dihapus.',
        ]);
    }

    /** Also used by the aturan gaji route for jenis bonus/potongan (UC-11). */
    public static function create(array $validated, ?int $userId): PenyesuaianGaji
    {
        return DB::transaction(function () use ($validated, $userId) {
            PeriodeGaji::ensureOpen(PenyesuaianGaji::periodeOf($validated['bulan']), 'bulan', 'penyesuaian gaji periode tersebut');

            $attributes = self::attributes($validated);
            self::refuseNegativeTotal($attributes);

            return PenyesuaianGaji::create($attributes + ['created_by' => $userId]);
        })->load('mentor.user');
    }

    /**
     * A potongan may not take the month's salary below zero. $replacing is
     * the stored adjustment an update changes; its old amount is taken out.
     */
    private static function refuseNegativeTotal(array $attributes, ?PenyesuaianGaji $replacing = null): void
    {
        if ($attributes['jenis'] !== 'potongan') {
            return;
        }

        $bulan = substr(Carbon::parse($attributes['periode'])->toDateString(), 0, 7);
        $total = (float) (app(GajiCalculator::class)->rekap(['mentor_id' => $attributes['mentor_id'], 'bulan' => $bulan])->first()?->nominal ?? 0);

        if ($replacing && (int) $replacing->mentor_id === (int) $attributes['mentor_id']
            && Carbon::parse($replacing->periode)->isSameMonth(Carbon::parse($attributes['periode']))) {
            $total += $replacing->jenis === 'potongan' ? $replacing->nominal : -$replacing->nominal;
        }

        if ($total - (float) $attributes['nominal'] < 0) {
            throw ValidationException::withMessages([
                'nominal' => 'Potongan melebihi gaji bulan ini (Rp '.number_format(max($total, 0), 0, ',', '.')
                    .'). Total gaji tidak boleh negatif.',
            ]);
        }
    }

    /** The API speaks YYYY-MM; the column stores the first day of the month. */
    private static function attributes(array $validated): array
    {
        if (isset($validated['bulan'])) {
            $validated['periode'] = PenyesuaianGaji::periodeOf($validated['bulan']);
        }

        return Arr::except($validated, ['bulan']);
    }
}
