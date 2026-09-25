<?php

namespace Database\Seeders;

use App\Enums\RolStaff;
use App\Models\UsuarioStaff;
use Illuminate\Database\Seeder;

class UsuarioStaffSeeder extends Seeder
{
    /**
     * Seed one moderator and one admin staff account with fixed test credentials.
     *
     * Password is the same for both and known to the whole team — this is a
     * hackathon project, not production, so a fixed password beats a random
     * one that only exists in a terminal that scrolled away.
     */
    public function run(): void
    {
        $passwordDePrueba = 'centinela2026';

        $cuentas = [
            ['nombre' => 'Moderador', 'email' => 'moderador@centinela-formosa.test', 'rol' => RolStaff::Moderador],
            ['nombre' => 'Admin', 'email' => 'admin@centinela-formosa.test', 'rol' => RolStaff::Admin],
        ];

        foreach ($cuentas as $cuenta) {
            UsuarioStaff::updateOrCreate(
                ['email' => $cuenta['email']],
                ['nombre' => $cuenta['nombre'], 'rol' => $cuenta['rol'], 'password' => $passwordDePrueba],
            );
        }

        $this->command->info("Staff sembrado. Password de prueba para ambas cuentas: {$passwordDePrueba}");
    }
}