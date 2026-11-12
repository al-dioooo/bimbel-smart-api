<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A manual bonus or potongan on one mentor's salary for one month (REQ-064).
 */
class PenyesuaianGaji extends Model
{
    public const JENIS = ['bonus', 'potongan'];

    protected $table = 'penyesuaian_gaji';

    protected $fillable = [
        'mentor_id',
        'periode',
        'jenis',
        'nominal',
        'keterangan',
        'created_by',
    ];

    protected $appends = ['bulan'];

    protected function casts(): array
    {
        return [
            'periode' => 'date:Y-m-d',
            'nominal' => 'float',
        ];
    }

    public function mentor()
    {
        return $this->belongsTo(Mentor::class);
    }

    /** The salary month as YYYY-MM, the format every report filter uses. */
    protected function bulan(): Attribute
    {
        return Attribute::get(fn () => $this->periode?->format('Y-m'));
    }

    /** First day of a YYYY-MM month, as stored in `periode`. */
    public static function periodeOf(string $bulan): string
    {
        return Carbon::createFromFormat('!Y-m', $bulan)->toDateString();
    }

    /** Signed amount: bonus adds, potongan subtracts. */
    public function signedNominal(): float
    {
        return $this->jenis === 'potongan' ? -$this->nominal : $this->nominal;
    }
}
