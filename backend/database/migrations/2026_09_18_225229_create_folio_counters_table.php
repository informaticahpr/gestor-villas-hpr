<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folio_counters', function (Blueprint $table) {
            $table->string('tipo', 2)->primary();
            $table->unsignedInteger('siguiente')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folio_counters');
    }
};
