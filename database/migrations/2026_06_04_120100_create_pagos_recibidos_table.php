<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pagos_recibidos')) {
            return;
        }

        Schema::create('pagos_recibidos', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');
            $table->string('status', 20)->default('activo');
            $table->unsignedBigInteger('idusuario')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_id'], 'pagos_recibidos_source_unique');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_recibidos');
    }
};
