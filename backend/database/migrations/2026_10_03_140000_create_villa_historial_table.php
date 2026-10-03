<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de propietarios y encargados de cada villa. Cuando una villa cambia de propietario o de
 * encargado, se guarda aqui una "foto" de la persona que sale (sus datos tal como estaban registrados),
 * el periodo en que lo fue y quien hizo el cambio. Es una copia: no depende de que el registro original
 * siga existiendo o sin cambios (el encargado anterior se borra; el propietario se puede editar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('villa_historial', function (Blueprint $table) {
            $table->id();
            $table->string('CLV_CLIE', 5)->index();
            $table->foreign('CLV_CLIE')->references('CLV_CLIE')->on('villas')->cascadeOnDelete();
            $table->string('TIPO', 15); // 'propietario' | 'encargado'
            // solo para propietarios: el registro original, por si sigue existiendo (tiene otras villas)
            $table->foreignId('PROPIETARIO_ID')->nullable()->constrained('propietarios')->nullOnDelete();

            $table->string('NOMBRES', 60)->nullable();
            $table->string('APELLIDOS', 60)->nullable();
            $table->string('DNI', 30)->nullable();
            $table->string('TELF', 20)->nullable();
            $table->string('CELULAR', 20)->nullable();
            $table->string('OTRO_TEL', 20)->nullable();
            $table->string('MAIL', 60)->nullable();
            $table->string('MAIL2', 60)->nullable();
            $table->date('FECHA_NAC')->nullable();

            $table->date('DESDE')->nullable(); // desde cuando fue propietario/encargado de la villa
            $table->date('HASTA');             // fecha del cambio
            $table->foreignId('USUARIO_ID')->nullable()->constrained('users')->nullOnDelete(); // quien hizo el cambio
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('villa_historial');
    }
};
