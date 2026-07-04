<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('outgoing_api_requests')) {
            return;
        }

        Schema::create('outgoing_api_requests', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at')->index();
            $table->string('provider', 80)->nullable()->index();
            $table->string('source_context', 120)->nullable()->index();
            $table->string('method', 10)->default('POST');
            $table->string('url', 500);
            $table->string('host', 180)->nullable()->index();
            $table->unsignedSmallInteger('status_code')->nullable()->index();
            $table->boolean('success')->default(false)->index();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->longText('request_headers')->nullable();
            $table->longText('request_payload')->nullable();
            $table->longText('response_headers')->nullable();
            $table->longText('response_body')->nullable();
            $table->string('error_class', 180)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('idusuario')->nullable()->index();
            $table->unsignedBigInteger('idtransaccion')->nullable()->index();
            $table->string('correlation_reference', 120)->nullable()->index();
            $table->tinyInteger('productivo')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outgoing_api_requests');
    }
};
