<?php

namespace App\Models;

use App\Support\Sql;
use Illuminate\Database\Eloquent\Model;

class AturanGaji extends Model
{
    protected $table = 'aturan_gaji';

    protected $fillable = [
        'kelas_id',
        'tarif'
    ];

    protected function casts(): array
    {
        return [
            'tarif' => 'float',
        ];
    }

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
        // ILIKE on PostgreSQL; MySQL's default collation is already case insensitive.
        $like = Sql::like();
        $query->when($filters['search'] ?? null, function ($query, $value) use ($like) {
            $query->where('kelas.nama', $like, '%' . $value . '%')
                ->orWhere('kelas.tingkat', $like, '%' . $value . '%');
        })->when($filters['kelas_id'] ?? null, function ($query, $value) {
            $query->where('aturan_gaji.kelas_id', $value);
        });
    }
}
