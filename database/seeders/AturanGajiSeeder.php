<?php

namespace Database\Seeders;

use App\Models\AturanGaji;
use App\Models\Kelas;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AturanGajiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $kelas = Kelas::all();

        foreach ($kelas as $row) {
            AturanGaji::create([
                'kelas_id' => $row->id,
                'tarif' => 30000
            ]);
        }
    }
}
