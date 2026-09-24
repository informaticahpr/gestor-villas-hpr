<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('CLIE1', function (Blueprint $table) {
            $table->string('CLV_CLIE', 5)->primary(); // ej. "A-1"
            $table->string('NOMBRES', 60);
            $table->string('APELLIDOS', 60);
            $table->string('DIR')->nullable(); // ubicacion
            $table->string('TELF', 20)->nullable();
            $table->string('CELULAR', 20)->nullable();
            $table->string('OTRO_TEL', 20)->nullable();
            $table->string('MAIL', 60)->nullable();
            $table->string('MAIL2', 60)->nullable();
            $table->date('FCONTRUC')->nullable(); // fecha de entrega
            $table->string('NOMED', 20)->nullable(); // clave ENEE
            $table->date('FECHA_NAC')->nullable();
            $table->unsignedSmallInteger('NOHAB')->nullable();
            $table->unsignedSmallInteger('NOBATH')->nullable();
            $table->boolean('APLICOBRO')->default(true);
            $table->decimal('SALDO', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('CLIE1');
    }
};
