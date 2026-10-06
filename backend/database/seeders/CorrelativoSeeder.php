<?php

namespace Database\Seeders;

use App\Models\Correlativo;
use Illuminate\Database\Seeder;

class CorrelativoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['CA', 'CR'] as $tipo) {
            Correlativo::firstOrCreate(['tipo' => $tipo], ['siguiente' => 1]);
        }
    }
}
