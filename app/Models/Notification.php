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

        'icon',
        'title',
        'message',
        'link',
        'is_read'
    ];

    /**
     * Without this, PDO hands back 1/0 instead of true/false on some drivers.
     */
    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
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

        // `when($filters['is_read'])` skipped "0"/"false" as falsy, so unread
        // could never be asked for. Parse it instead; an absent, empty or
        // unrecognised value means no filter.
        $isRead = ($filters['is_read'] ?? '') === ''
            ? null
            : filter_var($filters['is_read'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        $query->when($filters['user_id'] ?? null, function ($query, $value) use ($table) {
            $query->where("{$table}.user_id", $value);
        })->when($isRead !== null, function ($query) use ($table, $isRead) {
            $query->where("{$table}.is_read", $isRead);
        });
    }
}
