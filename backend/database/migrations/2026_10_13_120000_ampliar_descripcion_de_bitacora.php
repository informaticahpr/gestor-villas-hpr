<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La edicion de una villa queda en la bitacora con cada campo que cambio (antes -> despues), y eso
 * no cabe en 255 caracteres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bitacora', function (Blueprint $table) {
            $table->text('descripcion')->change();
        });
    }

    public function down(): void
    {
        Schema::table('bitacora', function (Blueprint $table) {
            $table->string('descripcion', 255)->change();
        });
    }
};
