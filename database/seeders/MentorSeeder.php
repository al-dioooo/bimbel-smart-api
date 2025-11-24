<?php

namespace Database\Seeders;

use App\Models\Mentor;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MentorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mentor = Mentor::create([
            'user_id' => 2,
            'tempat_tanggal_lahir' => 'Bandung, 10 Oktober 1995',
            'kontak' => '081234567890',
            'nik' => '3276011010950001',
            'npwp' => '09.123.456.7-890.000'
        ]);
    }
}
