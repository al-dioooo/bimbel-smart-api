<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',

        'title',
        'message',
        'is_read'
    ];

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

        $query->when($filters['user_id'] ?? null, function ($query, $value) use ($table) {
            $query->where("{$table}.user_id", $value);
        })->when($filters['is_read'] ?? null, function ($query, $value) use ($table) {
            $query->where("{$table}.is_read", $value);
        });
    }
}
