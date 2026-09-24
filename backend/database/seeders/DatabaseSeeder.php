<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            ConceptoSeeder::class,
            FormaPagoSeeder::class,
            FolioCounterSeeder::class,
            VillaSeeder::class,
            MovimientoSeeder::class,
        ]);
    }
}
