<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('CONC1', function (Blueprint $table) {
            $table->increments('NUM_CPTO');
            $table->string('DESCR', 40);
            $table->boolean('ES_CARGO'); // true = cargo, false = abono
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('CONC1');
    }
};
