<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // "Eliminar" un usuario nunca borra la fila -- solo marca deleted_at
            // (soft delete), asi se conserva el historial de movimientos y de la
            // bitacora que hizo ese usuario. Solo se puede "eliminar" si ya esta
            // deshabilitado (ver UserController::destroy()).
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
