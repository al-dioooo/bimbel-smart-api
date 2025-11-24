<?php

namespace Database\Seeders;

use App\Models\Jadwal;
use App\Models\Kelas;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class JadwalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $kelas = Kelas::all();

        foreach ($kelas as $row) {
            Jadwal::create([
                'kelas_id' => $row->id,

                'tanggal' => fake()->date(),
                'waktu_mulai' => Carbon::now()->toTimeString(),
                'waktu_selesai' => Carbon::now()->addHour()->toTimeString(),

                'materi' => fake()->randomLetter()
            ]);
        }
    }
}
