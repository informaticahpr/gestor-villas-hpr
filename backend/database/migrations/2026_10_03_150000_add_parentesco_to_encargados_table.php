<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parentesco o vinculo del encargado con el propietario (ej. HERMANO, ADMINISTRADOR). Tambien en el
 * historial, para que los encargados anteriores lo conserven.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encargados', function (Blueprint $table) {
            $table->string('PARENTESCO', 60)->nullable();
        });
        Schema::table('villa_historial', function (Blueprint $table) {
            $table->string('PARENTESCO', 60)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('encargados', function (Blueprint $table) {
            $table->dropColumn('PARENTESCO');
        });
        Schema::table('villa_historial', function (Blueprint $table) {
            $table->dropColumn('PARENTESCO');
        });
    }
};
