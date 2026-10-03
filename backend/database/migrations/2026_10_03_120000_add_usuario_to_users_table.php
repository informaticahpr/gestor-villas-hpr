<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separa el usuario de inicio de sesion del nombre: antes se entraba con el `name`, asi que cambiarle
 * el nombre a alguien le cambiaba tambien el usuario. Ahora `usuario` es solo para el login y `name`
 * solo para mostrar. El correo pasa a ser opcional.
 *
 * A los usuarios existentes se les asigna como usuario su nombre en minusculas, con puntos en lugar
 * de espacios (ej. "Juan Perez" -> "juan.perez"; "Administrador" -> "administrador", que sigue
 * funcionando igual porque el login no distingue mayusculas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('usuario', 50)->nullable()->after('name')->index();
            $table->string('email')->nullable()->change();
        });

        $usados = [];
        foreach (DB::table('users')->orderBy('id')->get(['id', 'name']) as $u) {
            $base = mb_strtolower(preg_replace('/\s+/', '.', trim($u->name)), 'UTF-8') ?: 'usuario';
            $usuario = $base;
            for ($i = 2; isset($usados[$usuario]); $i++) {
                $usuario = $base.$i;
            }
            $usados[$usuario] = true;

            DB::table('users')->where('id', $u->id)->update(['usuario' => $usuario]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['usuario']);
            $table->dropColumn('usuario');
        });
    }
};
