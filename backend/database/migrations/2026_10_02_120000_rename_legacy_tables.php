<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Renombra las tablas heredadas del sistema anterior a nombres legibles:
 * CLIE1 -> propietarios, CONC1 -> conceptos, CUEN1 -> movimientos.
 * Solo cambia el nombre de la tabla: datos, columnas y llaves foraneas se conservan.
 */
return new class extends Migration
{
    private const TABLAS = [
        'CLIE1' => 'propietarios',
        'CONC1' => 'conceptos',
        'CUEN1' => 'movimientos',
    ];

    public function up(): void
    {
        foreach (self::TABLAS as $anterior => $nueva) {
            Schema::rename($anterior, $nueva);
        }
    }

    public function down(): void
    {
        foreach (self::TABLAS as $anterior => $nueva) {
            Schema::rename($nueva, $anterior);
        }
    }
};
