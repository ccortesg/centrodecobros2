<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('incoming_api_requests')) {
            return;
        }

        Schema::create('incoming_api_requests', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at')->index();
            $table->string('method', 10);
            $table->string('path', 255)->index();
            $table->string('route_action', 255)->nullable()->index();
            $table->string('ip_address', 80)->nullable()->index();
            $table->string('user_agent', 500)->nullable();
            $table->unsignedSmallInteger('status_code')->nullable()->index();
            $table->boolean('success')->default(false)->index();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->longText('request_headers')->nullable();
            $table->longText('request_payload')->nullable();
            $table->longText('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('idusuario')->nullable()->index();
            $table->unsignedBigInteger('idtransaccion')->nullable()->index();
            $table->string('correlation_reference', 120)->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incoming_api_requests');
    }
};
