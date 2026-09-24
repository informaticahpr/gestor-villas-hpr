<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('CLIE1', function (Blueprint $table) {
            $table->boolean('CUOTA_ESPECIAL')->default(false)->after('APLICOBRO');
            $table->decimal('MONTO_CUOTA_ESPECIAL', 12, 2)->nullable()->after('CUOTA_ESPECIAL');
        });
    }

    public function down(): void
    {
        Schema::table('CLIE1', function (Blueprint $table) {
            $table->dropColumn(['CUOTA_ESPECIAL', 'MONTO_CUOTA_ESPECIAL']);
        });
    }
};
