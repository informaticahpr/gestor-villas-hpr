<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // upsert: el Consultor ya lo crea su migracion
        Role::upsert([
            ['nombre' => Role::DIRECTOR, 'descripcion' => 'Acceso total al sistema'],
            ['nombre' => Role::ADMIN, 'descripcion' => 'Acceso total al sistema'],
            ['nombre' => Role::SUPERVISOR, 'descripcion' => 'Puede aplicar cargos, abonos y generar reportes'],
            ['nombre' => Role::CONSULTOR, 'descripcion' => 'Solo lectura: villas, estados de cuenta y reimpresión'],
        ], ['nombre'], ['descripcion']);
    }
}
