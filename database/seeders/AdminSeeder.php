<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            [
                'email' => 'admin@isp.com',
            ],
            [
                'name' => 'Super Admin',
                'phone' => '01700000000',
                'password' => Hash::make('12345678'),
                'role' => 'admin',
                'status' => true,
            ]
        );
    }
}
