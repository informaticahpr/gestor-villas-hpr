<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('CUEN1', function (Blueprint $table) {
            $table->increments('ID_MOV');
            $table->string('CLV_CLIE', 5);
            $table->foreign('CLV_CLIE')->references('CLV_CLIE')->on('CLIE1')->cascadeOnDelete();
            $table->unsignedInteger('NUM_CPTO');
            $table->foreign('NUM_CPTO')->references('NUM_CPTO')->on('CONC1');
            $table->decimal('IMPORTE', 12, 2);
            $table->date('FECHA_APLI');
            $table->date('FECHA_VENC')->nullable(); // fecha de vencimiento (control de antiguedad de deuda)
            $table->unsignedSmallInteger('ANIO');
            $table->unsignedTinyInteger('MES');
            $table->unsignedInteger('REFER')->nullable(); // ID_MOV del cargo que este abono esta pagando
            $table->string('OBS')->nullable();
            $table->foreignId('USUARIO_ID')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('CUEN1');
    }
};
