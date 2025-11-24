<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengajuanJadwal extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pengajuan_jadwal';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
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

    public function jadwal()
    {
        return $this->belongsTo(Jadwal::class);
    }

    public function mentor()
    {
        return $this->jadwal->mentor();
    }

    /**
     * Resource filter function.
     *
     * @param  mixed  $query
     * @param  array  $filters
     * @return mixed $query
     */
    public function scopeFilter($query, array $filters)
    {
        $table = $this->getTable();

        $query->when($filters['mentor_id'] ?? null, function ($query, $value) use ($table) {
            $query->whereHas('jadwal', function ($query) use ($value) {
                $query->where('mentor_id', $value);
            });
        })->when($filters['kelas_id'] ?? null, function ($query, $value) use ($table) {
            $query->where("{$table}.kelas_id",  $value);
        })->when(($filters['from'] ?? null) && ($filters['to'] ?? null), function ($query) use ($filters, $table) {
            $query->whereDate("{$table}.tanggal", '>=', $filters['from'])
                ->whereDate("{$table}.tanggal", '<=', $filters['to']);
        });
    }
}
