<?php

namespace Database\Seeders;

use App\Models\Siswa;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SiswaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'nama' => 'Budi Santoso',
                'kelas_id' => 1,
                'kontak' => '081298765432',
                'alamat' => 'Jl. Merdeka No. 10, Jakarta',
                'asal_sekolah' => 'SMP Negeri 1 Jakarta',
                'nama_wali' => 'Slamet Santoso',
                'kontak_wali' => '081212345678',
                'pekerjaan_wali' => 'Karyawan Swasta',
                'alamat_wali' => 'Jl. Merdeka No. 10, Jakarta',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'nama' => 'Siti Aminah',
                'kelas_id' => 2,
                'kontak' => '081234567890',
                'alamat' => 'Jl. Sudirman No. 5, Bandung',
                'asal_sekolah' => 'SMP Negeri 2 Bandung',
                'nama_wali' => 'Haji Aminah',
                'kontak_wali' => '081298765432',
                'pekerjaan_wali' => 'Ibu Rumah Tangga',
                'alamat_wali' => 'Jl. Sudirman No. 5, Bandung',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        Siswa::insert($data);
    }
}
