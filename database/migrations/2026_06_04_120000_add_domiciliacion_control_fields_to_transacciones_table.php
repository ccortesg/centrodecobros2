<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transacciones', function (Blueprint $table) {
            if (!Schema::hasColumn('transacciones', 'ProximoCargoBase')) {
                $table->date('ProximoCargoBase')->nullable()->after('ProximoCargo');
            }

            if (!Schema::hasColumn('transacciones', 'intentos')) {
                $table->integer('intentos')->default(0)->after('ProximoCargoBase');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transacciones', function (Blueprint $table) {
            if (Schema::hasColumn('transacciones', 'intentos')) {
                $table->dropColumn('intentos');
            }

            if (Schema::hasColumn('transacciones', 'ProximoCargoBase')) {
                $table->dropColumn('ProximoCargoBase');
            }
        });
    }
};
