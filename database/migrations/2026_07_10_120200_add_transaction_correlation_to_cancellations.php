<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['cancelacionesDom', 'cancelacionesLector'] as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'idtransaccion')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unsignedBigInteger('idtransaccion')->nullable()->index();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['cancelacionesDom', 'cancelacionesLector'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'idtransaccion')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('idtransaccion');
                });
            }
        }
    }
};
