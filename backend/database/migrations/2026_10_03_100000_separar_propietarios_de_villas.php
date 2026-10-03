<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separa los datos del propietario de los de la villa:
 * - La tabla "propietarios" (antes CLIE1) en realidad guarda VILLAS -- su llave es el numero de villa
 *   y los movimientos cuelgan de ella -- asi que se renombra a "villas".
 * - Se crea una tabla "propietarios" nueva con los datos de la persona (nombres, DNI, telefonos,
 *   correos, fecha de nacimiento). Un propietario puede tener varias villas.
 * - Cada villa existente pasa sus datos de propietario a un registro nuevo (uno por villa) y queda
 *   apuntando a el con PROPIETARIO_ID.
 * - Se crea "encargados": la persona que atiende la villa en nombre del propietario (opcional, uno por
 *   villa, mismos datos que el propietario pero todos opcionales).
 * - La villa gana CLAVE_CATASTRAL y DESCRIPCION_IP. DIR (ubicacion), FCONTRUC (fecha de entrega) y
 *   NOMED (medidor ENEE) conservan su nombre de columna.
 */
return new class extends Migration
{
    private const COLUMNAS_PROPIETARIO = ['NOMBRES', 'APELLIDOS', 'TELF', 'CELULAR', 'OTRO_TEL', 'MAIL', 'MAIL2', 'FECHA_NAC'];

    public function up(): void
    {
        Schema::rename('propietarios', 'villas');

        Schema::create('propietarios', function (Blueprint $table) {
            $table->id();
            $table->string('NOMBRES', 60);
            $table->string('APELLIDOS', 60)->default('');
            $table->string('DNI', 30)->nullable()->unique(); // DNI o pasaporte
            $table->string('TELF', 20)->nullable();
            $table->string('CELULAR', 20)->nullable();
            $table->string('OTRO_TEL', 20)->nullable();
            $table->string('MAIL', 60)->nullable();
            $table->string('MAIL2', 60)->nullable();
            $table->date('FECHA_NAC')->nullable();
            $table->timestamps();
        });

        Schema::table('villas', function (Blueprint $table) {
            $table->foreignId('PROPIETARIO_ID')->nullable()->constrained('propietarios')->restrictOnDelete();
            $table->string('CLAVE_CATASTRAL', 40)->nullable();
            $table->string('DESCRIPCION_IP', 255)->nullable();
        });

        Schema::create('encargados', function (Blueprint $table) {
            $table->id();
            $table->string('CLV_CLIE', 5)->unique();
            $table->foreign('CLV_CLIE')->references('CLV_CLIE')->on('villas')->cascadeOnDelete();
            $table->string('NOMBRES', 60)->nullable();
            $table->string('APELLIDOS', 60)->nullable();
            $table->string('DNI', 30)->nullable();
            $table->string('TELF', 20)->nullable();
            $table->string('CELULAR', 20)->nullable();
            $table->string('OTRO_TEL', 20)->nullable();
            $table->string('MAIL', 60)->nullable();
            $table->string('MAIL2', 60)->nullable();
            $table->date('FECHA_NAC')->nullable();
            $table->timestamps();
        });

        foreach (DB::table('villas')->orderBy('CLV_CLIE')->get() as $villa) {
            $id = DB::table('propietarios')->insertGetId([
                'NOMBRES' => $villa->NOMBRES,
                'APELLIDOS' => $villa->APELLIDOS ?? '',
                'TELF' => $villa->TELF,
                'CELULAR' => $villa->CELULAR,
                'OTRO_TEL' => $villa->OTRO_TEL,
                'MAIL' => $villa->MAIL,
                'MAIL2' => $villa->MAIL2,
                'FECHA_NAC' => $villa->FECHA_NAC,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('villas')->where('CLV_CLIE', $villa->CLV_CLIE)->update(['PROPIETARIO_ID' => $id]);
        }

        Schema::table('villas', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNAS_PROPIETARIO);
        });
    }

    public function down(): void
    {
        Schema::drop('encargados');

        Schema::table('villas', function (Blueprint $table) {
            $table->string('NOMBRES', 60)->default('');
            $table->string('APELLIDOS', 60)->default('');
            $table->string('TELF', 20)->nullable();
            $table->string('CELULAR', 20)->nullable();
            $table->string('OTRO_TEL', 20)->nullable();
            $table->string('MAIL', 60)->nullable();
            $table->string('MAIL2', 60)->nullable();
            $table->date('FECHA_NAC')->nullable();
        });

        foreach (DB::table('villas')->whereNotNull('PROPIETARIO_ID')->get() as $villa) {
            $p = DB::table('propietarios')->find($villa->PROPIETARIO_ID);
            if ($p) {
                DB::table('villas')->where('CLV_CLIE', $villa->CLV_CLIE)->update(
                    collect(self::COLUMNAS_PROPIETARIO)->mapWithKeys(fn ($c) => [$c => $p->{$c}])->all()
                );
            }
        }

        Schema::table('villas', function (Blueprint $table) {
            $table->dropForeign(['PROPIETARIO_ID']);
            $table->dropColumn(['PROPIETARIO_ID', 'CLAVE_CATASTRAL', 'DESCRIPCION_IP']);
        });

        Schema::drop('propietarios');
        Schema::rename('villas', 'propietarios');
    }
};
