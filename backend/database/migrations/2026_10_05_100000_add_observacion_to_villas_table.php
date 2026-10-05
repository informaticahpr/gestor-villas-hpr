<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Observacion libre de la villa. Uso principal: cuando la villa esta a nombre de varias personas
 * (en el Excel: "Victor Gonzales / Luisa Reyes"), el propietario registrado es el primero y aqui se
 * anota a nombre de quienes esta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('villas', function (Blueprint $table) {
            $table->string('OBSERVACION', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('villas', function (Blueprint $table) {
            $table->dropColumn('OBSERVACION');
        });
    }
};
