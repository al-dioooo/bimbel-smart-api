<?php

namespace Database\Seeders;

use App\Models\Kelas;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $administrator = User::factory()->create([
            'username' => 'aliceevr',
            'name' => 'Alice',
            'email' => 'hello@aliceevr.com',
            'role' => 1,
            'password' => bcrypt('password')
        ]);

        $mentor = User::factory()->create([
            'username' => 'jeaansly',
            'name' => 'Uyoy',
            'email' => 'hello@imjeaansly.com',
            'role' => 0,
            'password' => bcrypt('password')
        ]);

        $this->call([
            MentorSeeder::class,
            KelasSeeder::class,
            SiswaSeeder::class,
            JadwalSeeder::class,
            AturanGajiSeeder::class,
            NotificationSeeder::class,
            AbsensiSeeder::class,
            PengajuanJadwalSeeder::class
        ]);
    }
}
