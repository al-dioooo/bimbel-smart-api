<?php

namespace Database\Seeders;

use App\Models\Jadwal;
use App\Models\PengajuanJadwal;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PengajuanJadwalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jadwal = Jadwal::all();

        foreach ($jadwal as $index => $row) {
            PengajuanJadwal::create([
                'jadwal_id' => $row->id,
                'tanggal_sebelum' => Carbon::now()->addDays($index),
                'tanggal_sesudah' => Carbon::now()->addDays($index + 1),
                'waktu_mulai_sebelum' => Carbon::now()->addHours($index)->toTimeString(),
                'waktu_mulai_sesudah' => Carbon::now()->addHours($index + 1)->toTimeString(),
                'waktu_selesai_sebelum' => Carbon::now()->subHours($index + 1)->toTimeString(),
                'waktu_selesai_sesudah' => Carbon::now()->subHours($index)->toTimeString(),
                'alasan' => fake()->sentence(),
                'status' => $index === 0 ? 'pending' : ($index === 2 ? 'ditolak' : 'diterima'),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
        }
    }
}
