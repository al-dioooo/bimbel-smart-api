<?php

namespace App\Models;

use App\Support\Sql;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Jadwal extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'jadwal';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'kelas_id',

        'tanggal',
        'waktu_mulai',
        'waktu_selesai',

        'materi',
    ];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }

    /**
     * Times are stored as H:i:s whatever the client sent (the API accepts H:i),
     * so they compare correctly as text on SQLite as well as on PostgreSQL.
     */
    protected function waktuMulai(): Attribute
    {
        return Attribute::set(fn ($value) => self::normalizeTime($value));
    }

    protected function waktuSelesai(): Attribute
    {
        return Attribute::set(fn ($value) => self::normalizeTime($value));
    }

    public static function normalizeTime(?string $value): ?string
    {
        return $value === null ? null : Carbon::parse($value)->format('H:i:s');
    }

    /**
     * REQ-032 / REQ-034: the first other session on the same date that overlaps
     * [mulai, selesai) and belongs to the same kelas or to a kelas of the same
     * mentor. Sessions that only touch (one ends 10:00, the next starts 10:00)
     * do not overlap.
     */
    public static function findConflict(int $kelasId, string $tanggal, string $mulai, string $selesai, ?int $ignoreId = null): ?self
    {
        $mentorId = Kelas::whereKey($kelasId)->value('mentor_id');

        return static::query()
            ->with('kelas')
            ->whereDate('tanggal', Carbon::parse($tanggal)->toDateString())
            ->where('waktu_mulai', '<', self::normalizeTime($selesai))
            ->where('waktu_selesai', '>', self::normalizeTime($mulai))
            ->where(function ($query) use ($kelasId, $mentorId) {
                $query->where('kelas_id', $kelasId)
                    ->when($mentorId, fn ($query) => $query->orWhereIn('kelas_id', Kelas::where('mentor_id', $mentorId)->select('id')));
            })
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->orderBy('waktu_mulai')
            ->first();
    }

    /**
     * Resource filter function.
     *
     * @param  mixed  $query
     * @return mixed $query
     */
    public function scopeFilter($query, array $filters)
    {
        $table = $this->getTable();
        // ILIKE on PostgreSQL; MySQL's default collation is already case insensitive.
        $like = Sql::like();

        $query->when($filters['search'] ?? null, function ($query, $value) use ($like) {
            $splittedValue = explode(' ', $value);
            $mappedValueArray = [];

            foreach ($splittedValue as $row) {
                // array_push($mappedValueArray, "+$row");
                array_push($mappedValueArray, $row);
            }

            $mappedValue = implode('%', $mappedValueArray);

            $query->whereHas('kelas', function ($query) use ($mappedValue, $like) {
                $query->where('nama', $like, '%'.$mappedValue.'%');
            });
        })->when($filters['kelas_id'] ?? null, function ($query, $value) use ($table) {
            $query->where("{$table}.kelas_id", $value);
        })->when($filters['mentor_id'] ?? null, function ($query, $value) {
            $query->whereRelation('kelas', 'mentor_id', $value);
        })->when(($filters['from'] ?? null) && ($filters['to'] ?? null), function ($query) use ($filters, $table) {
            $query->whereDate("{$table}.tanggal", '>=', $filters['from'])
                ->whereDate("{$table}.tanggal", '<=', $filters['to']);
        });
    }
}
