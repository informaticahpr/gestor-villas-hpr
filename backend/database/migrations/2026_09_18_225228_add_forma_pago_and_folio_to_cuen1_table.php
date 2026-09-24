<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('CUEN1', function (Blueprint $table) {
            $table->unsignedBigInteger('FORMA_PAGO_ID')->nullable()->after('NUM_CPTO');
            $table->foreign('FORMA_PAGO_ID')->references('id')->on('formas_pago')->nullOnDelete();
            $table->string('FOLIO', 9)->nullable()->unique()->after('ID_MOV');
        });
    }

    public function down(): void
    {
        Schema::table('CUEN1', function (Blueprint $table) {
            $table->dropForeign(['FORMA_PAGO_ID']);
            $table->dropColumn(['FORMA_PAGO_ID', 'FOLIO']);
        });
    }
};
