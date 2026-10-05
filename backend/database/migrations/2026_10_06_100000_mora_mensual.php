<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mora mensual automatica (ver App\Services\MoraService):
 * - conceptos.ES_MORA marca el concepto con el que se cargan las moras (el "Mora" del seeder; si no
 *   existe se crea), igual que ES_MANTENIMIENTO marca el de la cuota.
 * - movimientos.MORA_PERIODO ("AAAA-MM") identifica la mora automatica de cada villa y mes, para no
 *   aplicarla dos veces y saber cual anular cuando paga.
 * - parametros: valores configurables del sistema; aqui el porcentaje de mora (0.83 %).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conceptos', function (Blueprint $table) {
            $table->boolean('ES_MORA')->default(false);
        });
        Schema::table('movimientos', function (Blueprint $table) {
            $table->string('MORA_PERIODO', 7)->nullable()->index();
        });
        Schema::create('parametros', function (Blueprint $table) {
            $table->string('clave', 60)->primary();
            $table->string('valor', 255)->nullable();
            $table->timestamps();
        });

        $mora = DB::table('conceptos')->whereRaw('LOWER(TRIM('.DB::getQueryGrammar()->wrap('DESCR').')) = ?', ['mora'])->orderBy('NUM_CPTO')->first();
        if ($mora) {
            DB::table('conceptos')->where('NUM_CPTO', $mora->NUM_CPTO)->update(['ES_MORA' => true, 'ES_CARGO' => true]);
        } else {
            DB::table('conceptos')->insert(['DESCR' => 'Mora', 'ES_CARGO' => true, 'ACTIVO' => true, 'ES_MANTENIMIENTO' => false, 'ES_MORA' => true]);
        }

        DB::table('parametros')->insert(['clave' => 'mora_porcentaje', 'valor' => '0.83', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('parametros');
        Schema::table('movimientos', function (Blueprint $table) {
            $table->dropIndex(['MORA_PERIODO']);
            $table->dropColumn('MORA_PERIODO');
        });
        Schema::table('conceptos', function (Blueprint $table) {
            $table->dropColumn('ES_MORA');
        });
    }
};
