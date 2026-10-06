<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El numero de cada cargo/abono se llama "correlativo" en la base de datos: la tabla de contadores
 * folio_counters pasa a correlativos y la columna FOLIO de movimientos a CORRELATIVO. Los datos se
 * conservan (solo cambian los nombres). En los documentos impresos sigue diciendo "Folio".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('folio_counters', 'correlativos');
        Schema::table('movimientos', function (Blueprint $table) {
            $table->renameColumn('FOLIO', 'CORRELATIVO');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->renameColumn('CORRELATIVO', 'FOLIO');
        });
        Schema::rename('correlativos', 'folio_counters');
    }
};
