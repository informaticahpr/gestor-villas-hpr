<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entidad', 40); // usuario, concepto, forma_pago, cuota_especial
            $table->string('accion', 20); // crear, editar, activar, desactivar, eliminar
            $table->string('descripcion', 255);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entidad']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora');
    }
};
