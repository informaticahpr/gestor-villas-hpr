<?php

use App\Models\RolPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuracion -> Permisos: que puede hacer cada cargo. Una fila = el cargo tiene ese permiso. El
 * Administrador no tiene filas: siempre tiene todos (ver User::tienePermiso). Arranca con los mismos
 * accesos que el sistema tenia fijos por cargo, para que nada cambie al migrar. En una base nueva los
 * cargos aun no existen aqui; ahi los pone el RoleSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rol_permisos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rol_id')->constrained('roles')->cascadeOnDelete();
            $table->string('permiso', 50);
            $table->unique(['rol_id', 'permiso']);
        });

        RolPermiso::sembrarIniciales();
    }

    public function down(): void
    {
        Schema::dropIfExists('rol_permisos');
    }
};
