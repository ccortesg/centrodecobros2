<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->string('channel', 20)->default('generic')->after('active')->index();
        });

        Schema::table('transacciones', function (Blueprint $table) {
            $table->string('domiciliation_status', 30)->nullable()->after('intentos')->index();
            $table->string('cancellation_reason', 50)->nullable()->after('domiciliation_status');
            $table->string('cancellation_idempotency_key', 191)->nullable()->unique()->after('cancellation_reason');
            $table->unsignedSmallInteger('cancellation_attempts')->default(0)->after('cancellation_idempotency_key');
            $table->dateTime('cancellation_requested_at')->nullable()->after('cancellation_attempts');
            $table->dateTime('cancellation_last_attempt_at')->nullable()->after('cancellation_requested_at');
            $table->dateTime('cancelled_at')->nullable()->after('cancellation_last_attempt_at');
        });

        DB::table('transacciones')
            ->where('tipo', 2)
            ->whereNull('domiciliation_status')
            ->update([
                'domiciliation_status' => DB::raw("CASE WHEN condicion = '2' THEN 'cancelled' WHEN condicion = '1' THEN 'active' ELSE 'unknown' END"),
            ]);
    }

    public function down(): void
    {
        Schema::table('transacciones', function (Blueprint $table) {
            $table->dropUnique(['cancellation_idempotency_key']);
            $table->dropColumn([
                'domiciliation_status', 'cancellation_reason', 'cancellation_idempotency_key',
                'cancellation_attempts', 'cancellation_requested_at', 'cancellation_last_attempt_at',
                'cancelled_at',
            ]);
        });

        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->dropColumn('channel');
        });
    }
};
