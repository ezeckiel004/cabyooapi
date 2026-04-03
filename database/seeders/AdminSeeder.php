<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Administrateur SIMCAR',
            'email' => 'admin@simcar.com',
            'phone' => '+1234567890',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->command->info('Administrateur créé avec succès!');
        $this->command->info('Email: admin@simcar.com');
        $this->command->info('Mot de passe: admin123');
    }
}
