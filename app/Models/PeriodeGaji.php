<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A finalized salary month (UC-10 step 9). While it exists, nothing that
 * would change that month's salary may be edited.
 */
class PeriodeGaji extends Model
{
    protected $table = 'periode_gaji';

    /** First key of the advisory locks on a salary month (second: yyyymm). */
    private const LOCK_NAMESPACE = 7301;

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
        // Inside the writer's transaction this waits for a finalization of
        // the month in progress, and holds it off until the write commits.
        if ($date !== null && DB::transactionLevel() > 0) {
            self::advisoryLock('pg_advisory_xact_lock_shared', $date);
        }

        if ($periode = static::finalFor($date)) {
            throw ValidationException::withMessages([
                $field => "Periode gaji {$periode->label()} sudah difinalisasi, sehingga {$what} tidak dapat diubah.",
            ]);
        }
    }

    /**
     * Taken by finalize, inside its transaction: waits until every write to
     * the month that already passed ensureOpen has committed, and makes later
     * writers wait until the finalization is committed (they then see it).
     */
    public static function lockForFinalize(Carbon|string $date): void
    {
        self::advisoryLock('pg_advisory_xact_lock', $date);
    }

    /** PostgreSQL only; SQLite serialises writers by itself. */
    private static function advisoryLock(string $function, Carbon|string $date): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $month = Carbon::parse($date);
        DB::select("SELECT {$function}(?, ?)", [self::LOCK_NAMESPACE, $month->year * 100 + $month->month]);
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
