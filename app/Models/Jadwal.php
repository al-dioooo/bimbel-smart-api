<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

        'materi'
    ];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
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

        $query->when($filters['search'] ?? null, function ($query, $value) use ($table) {
            $splittedValue = explode(' ', $value);
            $mappedValueArray = [];

            foreach ($splittedValue as $row) {
                // array_push($mappedValueArray, "+$row");
                array_push($mappedValueArray, $row);
            }

            $mappedValue = implode("%", $mappedValueArray);

            $query->whereHas('kelas', function ($query) use ($mappedValue) {
                $query->where('nama', 'like', '%' . $mappedValue . '%');
            });
        })->when($filters['kelas_id'] ?? null, function ($query, $value) use ($table) {
            $query->where("{$table}.kelas_id",  $value);
        })->when(($filters['from'] ?? null) && ($filters['to'] ?? null), function ($query) use ($filters, $table) {
            $query->whereDate("{$table}.tanggal", '>=', $filters['from'])
                ->whereDate("{$table}.tanggal", '<=', $filters['to']);
        });
    }
}
