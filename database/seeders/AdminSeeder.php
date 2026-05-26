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
            ['email' => 'admin@igomanager.com'],
            [
                'tipo'                 => 'registrado',
                'nombre'               => 'Administrador IGO',
                'email'                => 'admin@igomanager.com',
                'password'             => Hash::make('Admin1234!'),
                'consentimiento'       => true,
                'fecha_consentimiento' => now(),
                'version_politica'     => '1.0',
            ]
        );
    }
}
