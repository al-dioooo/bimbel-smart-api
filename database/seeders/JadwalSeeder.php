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
            Jadwal::insert([
                [
                    'kelas_id' => $row->id,

                    'tanggal' => Carbon::now()->addDays(0),
                    'waktu_mulai' => Carbon::now()->toTimeString(),
                    'waktu_selesai' => Carbon::now()->addHour()->toTimeString(),

                    'materi' => fake()->sentence(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ],
                [
                    'kelas_id' => $row->id,

                    'tanggal' => Carbon::now()->addDays(7),
                    'waktu_mulai' => Carbon::now()->toTimeString(),
                    'waktu_selesai' => Carbon::now()->addHour()->toTimeString(),

                    'materi' => fake()->sentence(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ],
                [
                    'kelas_id' => $row->id,

                    'tanggal' => Carbon::now()->addDays(14),
                    'waktu_mulai' => Carbon::now()->toTimeString(),
                    'waktu_selesai' => Carbon::now()->addHour()->toTimeString(),

                    'materi' => fake()->sentence(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ],
                [
                    'kelas_id' => $row->id,

                    'tanggal' => Carbon::now()->addDays(21),
                    'waktu_mulai' => Carbon::now()->toTimeString(),
                    'waktu_selesai' => Carbon::now()->addHour()->toTimeString(),

                    'materi' => fake()->sentence(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ],
                [
                    'kelas_id' => $row->id,

                    'tanggal' => Carbon::now()->addDays(28),
                    'waktu_mulai' => Carbon::now()->toTimeString(),
                    'waktu_selesai' => Carbon::now()->addHour()->toTimeString(),

                    'materi' => fake()->sentence(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ],
                [
                    'kelas_id' => $row->id,

                    'tanggal' => Carbon::now()->addDays(35),
                    'waktu_mulai' => Carbon::now()->toTimeString(),
                    'waktu_selesai' => Carbon::now()->addHour()->toTimeString(),

                    'materi' => fake()->sentence(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]
            ]);
        }
    }
}
