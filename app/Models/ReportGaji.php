<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The finalized salary of one mentor for one month (REQ-058). `rincian`
 * keeps the per-kelas lines and the adjustments as they were at
 * finalization, so the report and the slip never recompute a closed month.
 */
class ReportGaji extends Model
{
    protected $table = 'report_gaji';

    protected $fillable = [
        'periode_gaji_id',
        'mentor_id',
        'mentor_nama',
        'bulan',
        'jumlah_kehadiran',
        'gaji_pokok',
        'bonus',
        'potongan',
        'total_gaji',
        'rincian',
    ];

    protected function casts(): array
    {
        return [
            'bulan' => 'date:Y-m-d',
            'jumlah_kehadiran' => 'integer',
            'gaji_pokok' => 'float',
            'bonus' => 'float',
            'potongan' => 'float',
            'total_gaji' => 'float',
            'rincian' => 'array',
        ];
    }

    public function periodeGaji()
    {
        return $this->belongsTo(PeriodeGaji::class);
    }

    public function mentor()
    {
        return $this->belongsTo(Mentor::class);
    }
}
