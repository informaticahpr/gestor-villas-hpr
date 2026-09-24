<?php

namespace Database\Seeders;

use App\Models\FolioCounter;
use Illuminate\Database\Seeder;

class FolioCounterSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['CA', 'CR'] as $tipo) {
            FolioCounter::firstOrCreate(['tipo' => $tipo], ['siguiente' => 1]);
        }
    }
}
