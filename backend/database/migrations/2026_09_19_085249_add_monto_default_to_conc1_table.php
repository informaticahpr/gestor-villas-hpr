<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('CONC1', function (Blueprint $table) {
            // Monto fijo que controla Director/Admin desde Configuracion. Si esta
            // definido, los usuarios no pueden escribir un valor distinto al
            // aplicar este concepto (ver MovimientoController::montoParaConcepto()).
            $table->decimal('MONTO_DEFAULT', 12, 2)->nullable()->after('ACTIVO');
        });
    }

    public function down(): void
    {
        Schema::table('CONC1', function (Blueprint $table) {
            $table->dropColumn('MONTO_DEFAULT');
        });
    }
};
