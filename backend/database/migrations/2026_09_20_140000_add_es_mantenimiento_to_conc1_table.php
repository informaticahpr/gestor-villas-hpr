<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('CONC1', function (Blueprint $table) {
            // Marca al concepto de la cuota de mantenimiento mensual (solo uno). Su monto
            // (MONTO_DEFAULT) lo define Director/Admin en Configuracion > Cuotas y nadie
            // puede escribirlo al aplicar el cargo; es tambien el unico concepto al que
            // se le aplica la cuota especial de cada villa. No se puede editar por la API.
            $table->boolean('ES_MANTENIMIENTO')->default(false)->after('MONTO_DEFAULT');
        });

        // marca el concepto que ya existe con ese nombre
        $id = DB::table('CONC1')->whereRaw('LOWER("DESCR") = ?', ['cuota de mantenimiento'])->orderBy('NUM_CPTO')->value('NUM_CPTO');
        if ($id !== null) {
            DB::table('CONC1')->where('NUM_CPTO', $id)->update(['ES_MANTENIMIENTO' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('CONC1', function (Blueprint $table) {
            $table->dropColumn('ES_MANTENIMIENTO');
        });
    }
};
