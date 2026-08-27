<?php

namespace App\Models;

use App\Support\Sql;
use Illuminate\Database\Eloquent\Model;

class PengajuanJadwal extends Model
{
    protected $table = 'pengajuan_jadwal';

    public const STATUS_PENDING  = 'pending';
    public const STATUS_DITERIMA = 'diterima';
    public const STATUS_DITOLAK  = 'ditolak';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_DITERIMA,
        self::STATUS_DITOLAK,
    ];

    protected $fillable = [
        'jadwal_id',

        'tanggal_sebelum',
        'tanggal_sesudah',
        'waktu_mulai_sebelum',
        'waktu_mulai_sesudah',
        'waktu_selesai_sebelum',
        'waktu_selesai_sesudah',
        'alasan',
        'status'
    ];

    protected function casts(): array
    {
        return [
            'tanggal_sebelum' => 'date:Y-m-d',
            'tanggal_sesudah' => 'date:Y-m-d',
        ];
    }

    public function jadwal()
    {
        return $this->belongsTo(Jadwal::class);
    }

    /**
     * Resource filter function.
     *
     * The previous version filtered on pengajuan_jadwal.kelas_id and .tanggal
     * (neither column exists) and on jadwal.mentor_id (mentor hangs off kelas,
     * not jadwal), and silently ignored `search` and `status` even though the
     * controller passes both.
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
            $query->whereHas('jadwal.kelas', function ($query) use ($value, $like) {
                $query->where('nama', $like, '%' . $value . '%')
                    ->orWhere('tingkat', $like, '%' . $value . '%');
            })->orWhereHas('jadwal.kelas.mentor.user', function ($query) use ($value, $like) {
                $query->where('name', $like, '%' . $value . '%');
            });
        })->when($filters['status'] ?? null, function ($query, $value) use ($table) {
            $query->where("{$table}.status", strtolower($value));
        })->when($filters['mentor_id'] ?? null, function ($query, $value) {
            $query->whereHas('jadwal.kelas', function ($query) use ($value) {
                $query->where('mentor_id', $value);
            });
        })->when($filters['kelas_id'] ?? null, function ($query, $value) {
            $query->whereRelation('jadwal', 'kelas_id', $value);
        })->when(($filters['from'] ?? null) && ($filters['to'] ?? null), function ($query) use ($filters, $table) {
            $query->whereDate("{$table}.tanggal_sesudah", '>=', $filters['from'])
                ->whereDate("{$table}.tanggal_sesudah", '<=', $filters['to']);
        });
    }
}
