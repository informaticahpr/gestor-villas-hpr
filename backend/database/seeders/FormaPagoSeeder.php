<?php

namespace Database\Seeders;

use App\Models\FormaPago;
use Illuminate\Database\Seeder;

class FormaPagoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Efectivo', 'Transferencia', 'Depósito', 'Cheque', 'Otro'] as $nombre) {
            FormaPago::firstOrCreate(['nombre' => $nombre]);
        }
    }
}
