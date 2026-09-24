<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('CONC1', function (Blueprint $table) {
            $table->boolean('ACTIVO')->default(true)->after('ES_CARGO');
        });

        Schema::table('formas_pago', function (Blueprint $table) {
            $table->boolean('activo')->default(true)->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('CONC1', function (Blueprint $table) {
            $table->dropColumn('ACTIVO');
        });

        Schema::table('formas_pago', function (Blueprint $table) {
            $table->dropColumn('activo');
        });
    }
};
