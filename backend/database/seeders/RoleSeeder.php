<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::insert([
            ['nombre' => Role::DIRECTOR, 'descripcion' => 'Acceso total al sistema'],
            ['nombre' => Role::ADMIN, 'descripcion' => 'Acceso total al sistema'],
            ['nombre' => Role::SUPERVISOR, 'descripcion' => 'Puede aplicar cargos, abonos y generar reportes'],
        ]);
    }
}
