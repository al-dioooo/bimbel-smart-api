<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * A finalized salary month (UC-10 step 9). While it exists, nothing that
 * would change that month's salary may be edited.
 */
class PeriodeGaji extends Model
{
    protected $table = 'periode_gaji';

    protected $fillable = [
        'periode',
        'finalized_at',
        'finalized_by',
        'jumlah_mentor',
        'total_gaji',
    ];

    protected function casts(): array
    {
        return [
            'periode' => 'date:Y-m-d',
            'finalized_at' => 'datetime',
            'total_gaji' => 'float',
            'jumlah_mentor' => 'integer',
        ];
    }

    public function finalizer()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function reports()
    {
        return $this->hasMany(ReportGaji::class);
    }

    /** The finalized period that contains the date, if any. */
    public static function finalFor(Carbon|string|null $date): ?self
    {
        if ($date === null) {
            return null;
        }

        return static::query()->whereDate('periode', Carbon::parse($date)->startOfMonth()->toDateString())->first();
    }

    /**
     * 422 when the date falls in a finalized month.
     *
     * @param  string  $what  what cannot change, e.g. "absensi ini"
     */
    public static function ensureOpen(Carbon|string|null $date, string $field, string $what): void
    {
        if ($periode = static::finalFor($date)) {
            throw ValidationException::withMessages([
                $field => "Periode gaji {$periode->label()} sudah difinalisasi, sehingga {$what} tidak dapat diubah.",
            ]);
        }
    }

    /** "September 2026". */
    public function label(): string
    {
        return self::labelOf($this->periode);
    }

    public static function labelOf(Carbon|string $date): string
    {
        return Carbon::parse($date)->locale('id')->translatedFormat('F Y');
    }
}
