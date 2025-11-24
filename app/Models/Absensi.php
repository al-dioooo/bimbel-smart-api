<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Absensi extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'absensi';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'jadwal_id',
        'siswa_id',

        'tanggal',
        'status',

        'is_open'
    ];
}
