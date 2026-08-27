<?php

namespace Database\Seeders;

use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'user_id' => 1,
                'icon' => 'bell',
                'title' => 'Hello Alice!',
                'message' => 'Welcome to bimbel smart portal!',
                'link' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'user_id' => 2,
                'icon' => 'bell',
                'title' => 'Hello Uyoy!',
                'message' => 'Welcome to bimbel smart portal!',
                'link' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]
        ];

        Notification::insert($data);
    }
}
