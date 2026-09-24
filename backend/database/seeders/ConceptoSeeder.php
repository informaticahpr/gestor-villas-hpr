<?php

namespace Database\Seeders;

use App\Models\Concepto;
use Illuminate\Database\Seeder;

class ConceptoSeeder extends Seeder
{
    public function run(): void
    {
        Concepto::insert([
            ['NUM_CPTO' => 1, 'DESCR' => 'Cuota de mantenimiento', 'ES_CARGO' => true, 'ES_MANTENIMIENTO' => true],
            ['NUM_CPTO' => 2, 'DESCR' => 'Cargo extraordinario', 'ES_CARGO' => true, 'ES_MANTENIMIENTO' => false],
            ['NUM_CPTO' => 3, 'DESCR' => 'Mora', 'ES_CARGO' => true, 'ES_MANTENIMIENTO' => false],
            ['NUM_CPTO' => 4, 'DESCR' => 'Abono / Pago', 'ES_CARGO' => false, 'ES_MANTENIMIENTO' => false],
        ]);
    }
}
