<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cargo Consultor (Recepcion y quien revisa los estados de cuenta): solo lectura. Ve las villas, el
 * estado de cuenta por villa y la reimpresion de recibos y notas de cargo; no modifica, carga, anula
 * ni cobra nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->insertOrIgnore([
            'nombre' => 'Consultor',
            'descripcion' => 'Solo lectura: villas, estados de cuenta y reimpresión',
        ]);
    }

    public function down(): void
    {
        // los usuarios con este cargo se quedan sin cargo (rol_id nullOnDelete)
        DB::table('roles')->where('nombre', 'Consultor')->delete();
    }
};
