<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User; // <-- PENTING: Import model User
use Illuminate\Support\Facades\Hash; // <-- PENTING: Import Hash

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Buat pengguna admin
        User::create([
            'name' => 'Admin',
            'email' => 'admin@renewa.com',
            'password' => Hash::make('password'), // Ganti 'password' dengan password yang aman
            'role' => 'admin',
            'status' => 'approved',
            'email_verified_at' => now(), // Langsung set email sebagai terverifikasi
        ]);
    }
}