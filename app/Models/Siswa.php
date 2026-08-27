<?php

namespace App\Models;

use App\Support\Sql;
use Illuminate\Database\Eloquent\Model;

class Siswa extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'siswa';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'nama',

        'kelas_id',

        'kontak',
        'alamat',
        'tempat_tanggal_lahir',
        'asal_sekolah',
        'nama_wali',
        'kontak_wali',
        'pekerjaan_wali',
        'alamat_wali',
        
        'tanggal_bergabung'
    ];

    /**
     * The kelas relationship (belongsTo).
     *
     * @var BelongsTo
     */
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
        // ILIKE on PostgreSQL; MySQL's default collation is already case insensitive.
        $like = Sql::like();

        $query->when($filters['search'] ?? null, function ($query, $value) use ($table, $like) {
            $splittedValue = explode(' ', $value);
            $mappedValueArray = [];

            foreach ($splittedValue as $row) {
                // array_push($mappedValueArray, "+$row");
                array_push($mappedValueArray, $row);
            }

            $mappedValue = implode("%", $mappedValueArray);

            $query->where("{$table}.nama", $like, '%' . $mappedValue . '%')->orWhere("{$table}.kontak", $like, '%' . $mappedValue . '%');
        })->when($filters['nama'] ?? null, function ($query, $value) use ($table, $like) {
            $query->where("{$table}.nama", $like, '%' . $value . '%');
        })->when($filters['kelas'] ?? null, function ($query, $value) use ($table, $like) {
            $query->whereHas('kelas', function ($query) use ($value, $like) {
                $query->where('id')->where("nama", $like, '%' . $value . '%')->orWhere("tingkat", $like, '%' . $value . '%');
            });
        })->when($filters['kelas_id'] ?? null, function ($query, $value) {
            $query->whereRelation('kelas', 'id', $value);
        })->when($filters['no_kelas'] ?? null, function ($query) use ($table) {
            $query->whereNull("{$table}.kelas_id");
        })->when(($filters['from'] ?? null) && ($filters['to'] ?? null), function ($query) use ($filters, $table) {
            $query->whereDate("{$table}.created_at", '>=', $filters['from'])
                ->whereDate("{$table}.created_at", '<=', $filters['to']);
        });
    }
}
