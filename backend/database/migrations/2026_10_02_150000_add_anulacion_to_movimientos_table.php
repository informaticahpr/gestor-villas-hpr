<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anulacion de cargos/abonos capturados por error: el movimiento no se borra (conserva su folio y
 * queda el rastro de quien, cuando y por que), pero deja de contar en saldos, estados de cuenta,
 * reportes y dashboard. Ver el scope global "vigentes" en App\Models\Movimiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->timestamp('ANULADO_EN')->nullable();
            $table->foreignId('ANULADO_POR')->nullable()->constrained('users')->nullOnDelete();
            $table->string('MOTIVO_ANULACION', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->dropForeign(['ANULADO_POR']);
            $table->dropColumn(['ANULADO_EN', 'ANULADO_POR', 'MOTIVO_ANULACION']);
        });
    }
};
