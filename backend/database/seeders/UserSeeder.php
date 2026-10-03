<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::pluck('id', 'nombre');

        User::create([
            'name' => 'Director',
            'usuario' => 'director',
            'email' => 'director@hpr.test',
            'password' => 'passwd',
            'rol_id' => $roles[Role::DIRECTOR],
        ]);

        User::create([
            'name' => 'Administrador',
            'usuario' => 'administrador',
            'email' => 'admin@hpr.test',
            'password' => 'passwd',
            'rol_id' => $roles[Role::ADMIN],
        ]);

        User::create([
            'name' => 'Supervisor',
            'usuario' => 'supervisor',
            'email' => 'supervisor@hpr.test',
            'password' => 'passwd',
            'rol_id' => $roles[Role::SUPERVISOR],
        ]);
    }
}
