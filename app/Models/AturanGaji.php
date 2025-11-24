<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AturanGaji extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'aturan_gaji';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'kelas_id',
        'tarif'
    ];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }
}
