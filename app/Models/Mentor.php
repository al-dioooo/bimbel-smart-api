<?php

namespace App\Models;

use App\Support\Sql;
use Illuminate\Database\Eloquent\Model;

class Mentor extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mentor';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',

        'tempat_tanggal_lahir',
        'kontak',
        'nik',
        'npwp',
        'alamat'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
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

            $query->where("{$table}.kontak", $like, '%' . $mappedValue . '%')->orWhereRelation('user', 'name', $like, '%' . $value . '%');
        })->when($filters['nama'] ?? null, function ($query, $value) use ($table, $like) {
            $query->whereRelation('user', 'name', $like, '%' . $value . '%');
        })->when($filters['kontak'] ?? null, function ($query, $value) use ($table, $like) {
            $query->where("{$table}.kontak", $like, '%' . $value . '%');
        })->when(($filters['from'] ?? null) && ($filters['to'] ?? null), function ($query) use ($filters, $table) {
            $query->whereDate("{$table}.created_at", '>=', $filters['from'])
                ->whereDate("{$table}.created_at", '<=', $filters['to']);
        });
    }
}
