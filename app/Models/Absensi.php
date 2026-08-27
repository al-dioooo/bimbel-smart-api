<?php

namespace App\Models;

use App\Support\Sql;
use Illuminate\Database\Eloquent\Model;

class Absensi extends Model
{
    protected $table = 'absensi';

    protected $fillable = [
        'jadwal_id',
        'siswa_id',

        'tanggal',
        'status',

        'is_open'
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date:Y-m-d',
            'is_open' => 'boolean',
        ];
    }

    public function jadwal()
    {
        return $this->belongsTo(Jadwal::class);
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }

    /**
     * Resource filter function.
     *
     * AbsensiController::index already called ->filter(...) and ->with(['jadwal','siswa']),
     * but neither the scope nor the relations existed, so the endpoint threw.
     *
     * @param  mixed  $query
     * @param  array  $filters
     * @return mixed $query
     */
    public function scopeFilter($query, array $filters)
    {
        $table = $this->getTable();
        // ILIKE on PostgreSQL; MySQL's default collation is already case insensitive.
        $like = Sql::like();

        $query->when($filters['search'] ?? null, function ($query, $value) use ($like) {
            $query->whereRelation('siswa', 'nama', $like, '%' . $value . '%');
        })->when($filters['siswa_id'] ?? null, function ($query, $value) use ($table) {
            $query->where("{$table}.siswa_id", $value);
        })->when($filters['jadwal_id'] ?? null, function ($query, $value) use ($table) {
            $query->where("{$table}.jadwal_id", $value);
        })->when($filters['kelas_id'] ?? null, function ($query, $value) {
            $query->whereRelation('jadwal', 'kelas_id', $value);
        })->when($filters['status'] ?? null, function ($query, $value) use ($table) {
            $query->where("{$table}.status", strtolower($value));
        })->when(($filters['from'] ?? null) && ($filters['to'] ?? null), function ($query) use ($filters, $table) {
            $query->whereDate("{$table}.tanggal", '>=', $filters['from'])
                ->whereDate("{$table}.tanggal", '<=', $filters['to']);
        });
    }
}
