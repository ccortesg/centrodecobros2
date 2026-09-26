<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ROLE_ID = 4;
    private const ROLE_NAME = 'Consulta de respuestas';

    public function up(): void
    {
        $role = DB::table('roles')->where('id', self::ROLE_ID)->first();

        if ($role && (string) $role->nombre !== self::ROLE_NAME) {
            throw new \RuntimeException('No se puede crear el rol Consulta de respuestas: el id 4 ya esta ocupado.');
        }

        if (!Schema::hasColumn('users', 'idusuario_vinculado')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedInteger('idusuario_vinculado')->nullable()->index()->after('idrol');
            });
        }

        if (!$role) {
            DB::table('roles')->insert([
                'id' => self::ROLE_ID,
                'nombre' => self::ROLE_NAME,
                'descripcion' => 'Consulta de respuestas y pagos recibidos sin permisos de operacion',
                'condicion' => 1,
            ]);
        } else {
            DB::table('roles')->where('id', self::ROLE_ID)->update([
                'descripcion' => 'Consulta de respuestas y pagos recibidos sin permisos de operacion',
                'condicion' => 1,
            ]);
        }
    }

    public function down(): void
    {
        if (DB::table('users')->where('idrol', self::ROLE_ID)->exists()) {
            throw new \RuntimeException('No se puede revertir el rol Consulta de respuestas mientras tenga usuarios asignados.');
        }

        if (Schema::hasColumn('users', 'idusuario_vinculado')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('idusuario_vinculado');
            });
        }

        DB::table('roles')
            ->where('id', self::ROLE_ID)
            ->where('nombre', self::ROLE_NAME)
            ->delete();
    }
};
