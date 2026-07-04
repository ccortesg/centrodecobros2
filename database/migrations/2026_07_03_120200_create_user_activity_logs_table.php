<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_activity_logs')) {
            return;
        }

        Schema::create('user_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at')->index();
            $table->unsignedBigInteger('idusuario')->nullable()->index();
            $table->string('usuario', 191)->nullable()->index();
            $table->unsignedInteger('idrol')->nullable()->index();
            $table->string('action', 60)->index();
            $table->boolean('success')->default(true)->index();
            $table->integer('module_key')->nullable()->index();
            $table->string('module_name', 160)->nullable()->index();
            $table->string('route_path', 255)->nullable();
            $table->string('ip_address', 80)->nullable()->index();
            $table->string('user_agent', 500)->nullable();
            $table->string('session_id_hash', 128)->nullable()->index();
            $table->longText('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_logs');
    }
};
