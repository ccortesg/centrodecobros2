<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('webhook_user_settings')) {
            Schema::create('webhook_user_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('idusuario')->unique();
                $table->string('mode', 20)->default('legacy')->index();
                $table->boolean('hmac_enabled')->default(false);
                $table->longText('hmac_secret')->nullable();
                $table->string('hmac_secret_fingerprint', 16)->nullable();
                $table->timestamp('hmac_rotated_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('webhook_endpoints')) {
            Schema::create('webhook_endpoints', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('idusuario')->index();
                $table->string('name', 120);
                $table->longText('url');
                $table->string('url_hash', 64)->index();
                $table->string('host', 191)->index();
                $table->boolean('active')->default(true)->index();
                $table->string('payload_mode', 30)->default('legacy_exact');
                $table->string('ack_mode', 30)->default('legacy_code_success');
                $table->unsignedSmallInteger('rate_limit_per_minute')->default(25);
                $table->timestamps();
                $table->softDeletes();
                $table->index(['idusuario', 'active']);
            });
        }

        if (!Schema::hasTable('webhook_endpoint_subscriptions')) {
            Schema::create('webhook_endpoint_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('webhook_endpoint_id')->index();
                $table->string('event_type', 120)->index();
                $table->string('source_filter', 20)->default('all');
                $table->boolean('active')->default(true)->index();
                $table->timestamps();
                $table->unique(
                    ['webhook_endpoint_id', 'event_type', 'source_filter'],
                    'webhook_endpoint_event_source_unique'
                );
            });
        }

        if (!Schema::hasTable('webhook_events')) {
            Schema::create('webhook_events', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->unsignedBigInteger('idusuario')->nullable()->index();
                $table->unsignedBigInteger('idtransaccion')->nullable()->index();
                $table->string('event_type', 120)->index();
                $table->string('source_type', 80)->nullable()->index();
                $table->unsignedBigInteger('source_id')->nullable()->index();
                $table->string('source_context', 30)->nullable()->index();
                $table->string('idempotency_key', 191)->unique();
                $table->longText('payload');
                $table->string('status', 30)->default('created')->index();
                $table->timestamp('occurred_at')->index();
                $table->timestamps();
                $table->index(['idusuario', 'occurred_at']);
                $table->index(['source_type', 'source_id']);
            });
        }

        if (!Schema::hasTable('webhook_deliveries')) {
            Schema::create('webhook_deliveries', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('webhook_event_id')->index();
                $table->unsignedBigInteger('webhook_endpoint_id')->index();
                $table->string('status', 30)->default('pending')->index();
                $table->unsignedSmallInteger('attempt_count')->default(0);
                $table->timestamp('next_attempt_at')->nullable()->index();
                $table->timestamp('delivered_at')->nullable();
                $table->unsignedSmallInteger('last_status_code')->nullable()->index();
                $table->text('last_error')->nullable();
                $table->longText('raw_body');
                $table->string('body_hash', 64);
                $table->boolean('is_test')->default(false)->index();
                $table->timestamps();
                $table->unique(['webhook_event_id', 'webhook_endpoint_id'], 'webhook_event_endpoint_unique');
            });
        }

        if (!Schema::hasTable('webhook_delivery_attempts')) {
            Schema::create('webhook_delivery_attempts', function (Blueprint $table) {
                $table->id();
                $table->uuid('webhook_delivery_id')->index();
                $table->timestamp('attempted_at')->index();
                $table->unsignedSmallInteger('status_code')->nullable()->index();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->boolean('success')->default(false)->index();
                $table->longText('request_headers')->nullable();
                $table->longText('request_body')->nullable();
                $table->longText('response_headers')->nullable();
                $table->longText('response_body')->nullable();
                $table->string('error_class', 180)->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('webhook_rate_limits')) {
            Schema::create('webhook_rate_limits', function (Blueprint $table) {
                $table->string('host', 191)->primary();
                $table->timestamp('window_started_at');
                $table->unsignedSmallInteger('request_count')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_rate_limits');
        Schema::dropIfExists('webhook_delivery_attempts');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('webhook_endpoint_subscriptions');
        Schema::dropIfExists('webhook_endpoints');
        Schema::dropIfExists('webhook_user_settings');
    }
};
