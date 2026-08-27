<?php

namespace Database\Seeders;

use App\Models\Absensi;
use App\Models\Jadwal;
use Illuminate\Database\Seeder;

class AbsensiSeeder extends Seeder
{
    /**
     * One attendance row per (jadwal, siswa) for jadwal that have already
     * happened, weighted towards `hadir`.
     *
     * This was an empty stub, which left the dashboard, both charts and both
     * report screens with nothing to render.
     */
    public function run(): void
    {
        $weighted = array_merge(
            array_fill(0, 8, 'h'),
            ['s', 'i', 'a']
        );

        Jadwal::with('kelas.siswa')
            ->whereDate('tanggal', '<=', now()->toDateString())
            ->get()
            ->each(function (Jadwal $jadwal) use ($weighted) {
                $siswa = $jadwal->kelas?->siswa ?? collect();

                foreach ($siswa as $murid) {
                    Absensi::updateOrCreate(
                        [
                            'jadwal_id' => $jadwal->id,
                            'siswa_id'  => $murid->id,
                        ],
                        [
                            'tanggal' => $jadwal->tanggal,
                            'status'  => $weighted[array_rand($weighted)],
                            'is_open' => false,
                        ]
                    );
                }
            });
    }
}
