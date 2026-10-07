<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Las villas alquiladas ya no son solo de la produccion de España: el concepto pasa a ser
 * "Villas alquiladas a producción" (de cualquier pais). Las exenciones ya guardadas cambian de texto.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('exenciones_cuota')
            ->where('MOTIVO', 'Exenta por alquiler de España')
            ->update(['MOTIVO' => 'Exenta por alquiler a producción']);
    }

    public function down(): void
    {
        DB::table('exenciones_cuota')
            ->where('MOTIVO', 'Exenta por alquiler a producción')
            ->update(['MOTIVO' => 'Exenta por alquiler de España']);
    }
};
