<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Villas alquiladas a las producciones de España: mientras la villa este marcada (Configuracion ->
 * Villas alquiladas), al aplicar la cuota de mantenimiento a todas no se le cobra; en su lugar queda
 * una exencion de ese mes, que el estado de cuenta muestra como "Exenta por alquiler de España".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('villas', function (Blueprint $table) {
            $table->boolean('ALQUILADA')->default(false)->after('APLICOBRO');
        });

        Schema::create('exenciones_cuota', function (Blueprint $table) {
            $table->id();
            $table->string('CLV_CLIE', 5);
            $table->foreign('CLV_CLIE')->references('CLV_CLIE')->on('villas')->cascadeOnDelete();
            $table->date('FECHA'); // dia en que se aplico la cuota de ese mes
            $table->smallInteger('ANIO');
            $table->smallInteger('MES');
            $table->string('MOTIVO', 100);
            $table->foreignId('USUARIO_ID')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['CLV_CLIE', 'ANIO', 'MES']); // una exencion por villa y mes
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exenciones_cuota');
        Schema::table('villas', function (Blueprint $table) {
            $table->dropColumn('ALQUILADA');
        });
    }
};
