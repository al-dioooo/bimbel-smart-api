<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Siswa extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'nama',

        'kelas_id'
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

        $query->when($filters['search'] ?? null, function ($query, $value) use ($table) {
            $splittedValue = explode(' ', $value);
            $mappedValueArray = [];

            foreach ($splittedValue as $row) {
                // array_push($mappedValueArray, "+$row");
                array_push($mappedValueArray, $row);
            }

            $mappedValue = implode("%", $mappedValueArray);

            // $query->whereFullText(["{$table}.name", "{$table}.long_name", "{$table}.sku"], $mappedValue, ['mode' => 'boolean', '']);
            $query->where("{$table}.nama", 'like', '%' . $mappedValue . '%');
        })->when($filters['nama'] ?? null, function ($query, $value) use ($table) {
            $query->where("{$table}.nama", 'like', '%' . $value . '%');
        })->when($filters['kelas'] ?? null, function ($query, $value) use ($table) {
            $query->whereHas('kelas', function ($query) use ($value) {
                $query->where("nama", 'like', '%' . $value . '%')->orWhere("tingkat", 'like', '%' . $value . '%');
            });
        })->when(($filters['from'] ?? null) && ($filters['to'] ?? null), function ($query) use ($filters, $table) {
            $query->whereDate("{$table}.created_at", '>=', $filters['from'])
                ->whereDate("{$table}.created_at", '<=', $filters['to']);
        })->when($filters['pivot'] ?? null, function ($query, $value) {
            if ($value === 'with') {
                $query->with(['attributeDataPivots', 'discountPivots', 'rewardPivots']);
            }
        });
    }
}
