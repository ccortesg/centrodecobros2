<?php

namespace Tests\Support;

use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use RuntimeException;

trait UsesIsolatedCentroCobrosDatabase
{
    protected function setUpIsolatedDatabase(string $database = ':memory:'): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $drivers = implode(', ', PDO::getAvailableDrivers());
            $message = 'DB local bloqueada para Feature tests aislados: el PHP CLI no tiene pdo_sqlite. Drivers disponibles: ' . $drivers;

            if (method_exists($this, 'markTestSkipped')) {
                $this->markTestSkipped($message);
            }

            throw new RuntimeException($message);
        }

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => $database,
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
            'services.pagadetodo.mock' => true,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createCentroCobrosSchema();
        $this->seedCentroCobrosData();
    }

    protected function adminUser(): User
    {
        return User::findOrFail(1);
    }

    protected function clientAUser(): User
    {
        return User::findOrFail(2);
    }

    protected function clientBUser(): User
    {
        return User::findOrFail(3);
    }

    protected function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    private function createCentroCobrosSchema(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre')->nullable();
            $table->string('descripcion')->nullable();
            $table->integer('condicion')->default(1);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('usuario')->unique();
            $table->string('password')->nullable();
            $table->integer('condicion')->default(1);
            $table->integer('idrol');
            $table->string('token')->nullable();
            $table->string('IntegrationID')->nullable();
            $table->string('BusinessID')->nullable();
            $table->integer('productivo')->default(1);
            $table->integer('notificaPago')->default(0);
            $table->string('ligaPago')->nullable();
            $table->integer('recurrente')->default(0);
            $table->string('ligaRecurrente')->nullable();
            $table->rememberToken();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('personas', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre')->nullable();
            $table->string('tipo_documento')->nullable();
            $table->string('num_documento')->nullable();
            $table->string('direccion')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('estados', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre')->nullable();
            $table->integer('condicion')->default(1);
        });

        Schema::create('ciudades', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('idestado')->nullable();
            $table->string('nombre')->nullable();
            $table->integer('condicion')->default(1);
        });

        Schema::create('clientes', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('idciudad')->nullable();
            $table->string('rfc')->nullable();
            $table->string('razon_social')->nullable();
            $table->string('contacto')->nullable();
            $table->string('telefono_contacto')->nullable();
            $table->string('email_contacto')->nullable();
            $table->string('banco')->nullable();
            $table->string('cuenta')->nullable();
            $table->string('clabe')->nullable();
            $table->string('cuenta_sucursal')->nullable();
            $table->string('cuenta_ciudad')->nullable();
            $table->string('forma_pago')->nullable();
            $table->string('plazo')->nullable();
            $table->string('regimen')->nullable();
            $table->integer('idusuario')->nullable();
        });

        Schema::create('archivos', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('idpersona')->nullable();
            $table->string('nombre')->nullable();
            $table->string('extension')->nullable();
            $table->string('hashname')->nullable();
            $table->timestamps();
        });

        Schema::create('transacciones', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('folio')->nullable();
            $table->dateTime('fecha')->nullable();
            $table->string('User')->nullable();
            $table->string('Password')->nullable();
            $table->string('IntegrationID')->nullable();
            $table->string('BusinessID')->nullable();
            $table->string('PaymentTypes')->nullable();
            $table->string('IdReference')->nullable();
            $table->string('Description')->nullable();
            $table->integer('Amount')->nullable();
            $table->string('Reference')->nullable();
            $table->date('ExpirationDate')->nullable();
            $table->string('ClientReference')->nullable();
            $table->text('response')->nullable();
            $table->string('url')->nullable();
            $table->string('code')->nullable();
            $table->string('message')->nullable();
            $table->string('responseReference')->nullable();
            $table->string('referenceEmisor')->nullable();
            $table->string('Error')->nullable();
            $table->date('Date')->nullable();
            $table->string('Clabe')->nullable();
            $table->string('codeQR')->nullable();
            $table->integer('idusuario')->nullable();
            $table->integer('idcliente')->nullable();
            $table->integer('tipo')->nullable();
            $table->integer('frecuencia')->nullable();
            $table->date('ProximoCargo')->nullable();
            $table->date('ProximoCargoBase')->nullable();
            $table->integer('intentos')->default(0);
            $table->integer('condicion')->default(1);
            $table->integer('status')->nullable();
            $table->integer('productivo')->default(1);
            $table->timestamps();
        });

        Schema::create('respuestas', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('idtransaccion')->nullable();
            $table->dateTime('fecha')->nullable();
            $table->string('reference')->nullable();
            $table->string('status')->nullable();
            $table->string('foliocpagos')->nullable();
            $table->string('auth')->nullable();
            $table->string('cd_response')->nullable();
            $table->string('cd_error')->nullable();
            $table->string('nb_error')->nullable();
            $table->string('time')->nullable();
            $table->date('date')->nullable();
            $table->string('nb_company')->nullable();
            $table->string('nb_merchant')->nullable();
            $table->string('cc_type')->nullable();
            $table->string('tp_operation')->nullable();
            $table->string('cc_name')->nullable();
            $table->string('cc_number')->nullable();
            $table->string('cc_expmonth')->nullable();
            $table->string('cc_expyear')->nullable();
            $table->integer('amount')->nullable();
            $table->string('id_url')->nullable();
            $table->string('email')->nullable();
            $table->string('payment_type')->nullable();
            $table->string('promocion')->nullable();
            $table->string('number_tkn')->nullable();
            $table->string('cc_mask')->nullable();
            $table->text('response')->nullable();
            $table->integer('enviada')->default(0);
            $table->timestamps();
        });

        Schema::create('transaccionesDom', function (Blueprint $table) {
            $table->increments('id');
            $table->dateTime('fecha')->nullable();
            $table->integer('folio')->nullable();
            $table->integer('idtransaccion')->nullable();
            $table->integer('idcliente')->nullable();
            $table->string('User')->nullable();
            $table->string('Password')->nullable();
            $table->string('IntegrationID')->nullable();
            $table->string('BusinessID')->nullable();
            $table->string('Token')->nullable();
            $table->string('Reference')->nullable();
            $table->integer('Amount')->nullable();
            $table->string('ExpMonth')->nullable();
            $table->string('ExpYear')->nullable();
            $table->text('response')->nullable();
            $table->string('code')->nullable();
            $table->string('message')->nullable();
            $table->string('response_reference')->nullable();
            $table->string('status')->nullable();
            $table->string('foliocpagos')->nullable();
            $table->string('auth')->nullable();
            $table->string('cd_response')->nullable();
            $table->string('cd_error')->nullable();
            $table->string('nb_error')->nullable();
            $table->string('time')->nullable();
            $table->date('date')->nullable();
            $table->string('nb_company')->nullable();
            $table->string('nb_merchant')->nullable();
            $table->string('nb_street')->nullable();
            $table->string('cc_type')->nullable();
            $table->string('tp_operation')->nullable();
            $table->string('cc_name')->nullable();
            $table->string('cc_number')->nullable();
            $table->string('cc_expmonth')->nullable();
            $table->string('cc_expyear')->nullable();
            $table->integer('response_amount')->nullable();
            $table->string('voucher')->nullable();
            $table->string('payment_type')->nullable();
            $table->string('response_token')->nullable();
            $table->integer('idusuario')->nullable();
            $table->integer('productivo')->default(1);
            $table->timestamps();
        });

        foreach (['consultaspei', 'pagospei', 'cancelaspei'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) use ($tableName) {
                $table->increments('id');
                $table->integer('idtransaccion')->nullable();
                $table->dateTime('fecha')->nullable();
                $table->string($tableName === 'consultaspei' ? 'reference' : 'clabe')->nullable();
                $table->dateTime('fecha_peticion')->nullable();
                $table->integer('monto')->nullable();
                $table->string('transaccion')->nullable();
                $table->string('codigo')->nullable();
                $table->string('autorizacion')->nullable();
                $table->string('mensaje')->nullable();
                $table->integer('parcial')->nullable();
                $table->text('response')->nullable();
                $table->integer('condicion')->default(1);
                $table->integer('enviada')->default(0);
                $table->timestamps();
            });
        }

        Schema::create('cancelacionesDom', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('folio')->nullable();
            $table->dateTime('fecha')->nullable();
            $table->string('User')->nullable();
            $table->string('Password')->nullable();
            $table->string('IntegrationID')->nullable();
            $table->string('BusinessID')->nullable();
            $table->string('Token')->nullable();
            $table->string('Tkn_reference')->nullable();
            $table->text('response')->nullable();
            $table->string('code')->nullable();
            $table->string('message')->nullable();
            $table->integer('idusuario')->nullable();
            $table->integer('productivo')->default(1);
            $table->timestamps();
        });

        Schema::create('pagos_recibidos', function (Blueprint $table) {
            $table->increments('id');
            $table->string('source_type')->nullable();
            $table->integer('source_id')->nullable();
            $table->string('status')->default('activo');
            $table->integer('idusuario')->nullable();
            $table->timestamps();
        });
    }

    private function seedCentroCobrosData(): void
    {
        DB::table('roles')->insert([
            ['id' => 1, 'nombre' => 'Administrador', 'condicion' => 1],
            ['id' => 2, 'nombre' => 'Cliente', 'condicion' => 1],
        ]);

        DB::table('users')->insert([
            ['id' => 1, 'usuario' => 'admin', 'password' => bcrypt('secret'), 'idrol' => 1, 'condicion' => 1, 'token' => 'admin-token', 'IntegrationID' => '117', 'BusinessID' => '000030', 'productivo' => 1],
            ['id' => 2, 'usuario' => 'client-a', 'password' => bcrypt('secret'), 'idrol' => 2, 'condicion' => 1, 'token' => 'token-a', 'IntegrationID' => '117', 'BusinessID' => '000031', 'productivo' => 1],
            ['id' => 3, 'usuario' => 'client-b', 'password' => bcrypt('secret'), 'idrol' => 2, 'condicion' => 1, 'token' => 'token-b', 'IntegrationID' => '117', 'BusinessID' => '000032', 'productivo' => 1],
        ]);

        DB::table('personas')->insert([
            ['id' => 1, 'nombre' => 'Admin Persona', 'tipo_documento' => 'USER', 'num_documento' => '1', 'email' => 'admin@example.com', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'nombre' => 'Client A Persona', 'tipo_documento' => 'USER', 'num_documento' => '2', 'email' => 'client-a@example.com', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'nombre' => 'Client B Persona', 'tipo_documento' => 'USER', 'num_documento' => '3', 'email' => 'client-b@example.com', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 10, 'nombre' => 'Cliente A', 'tipo_documento' => 'CLIENTE', 'num_documento' => '10', 'email' => 'cliente-a@example.com', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 20, 'nombre' => 'Cliente B', 'tipo_documento' => 'CLIENTE', 'num_documento' => '20', 'email' => 'cliente-b@example.com', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('estados')->insert(['id' => 1, 'nombre' => 'Estado', 'condicion' => 1]);
        DB::table('ciudades')->insert(['id' => 1, 'idestado' => 1, 'nombre' => 'Ciudad', 'condicion' => 1]);

        DB::table('clientes')->insert([
            ['id' => 10, 'idciudad' => 1, 'razon_social' => 'Cliente A SA', 'rfc' => 'A010101AAA', 'idusuario' => 2],
            ['id' => 20, 'idciudad' => 1, 'razon_social' => 'Cliente B SA', 'rfc' => 'B010101BBB', 'idusuario' => 3],
        ]);

        DB::table('archivos')->insert([
            ['id' => 1, 'idpersona' => 10, 'nombre' => 'a.pdf', 'extension' => 'pdf', 'hashname' => 'a.pdf', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'idpersona' => 20, 'nombre' => 'b.pdf', 'extension' => 'pdf', 'hashname' => 'b.pdf', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('transacciones')->insert([
            $this->transactionRow(100, 2, 10, 1, 'LIGA-A', 'RESP-A'),
            $this->transactionRow(101, 3, 20, 1, 'LIGA-B', 'RESP-B'),
            $this->transactionRow(200, 2, 10, 2, 'DOM-A', 'RESP-DOM-A'),
            $this->transactionRow(201, 3, 20, 2, 'DOM-B', 'RESP-DOM-B'),
            $this->transactionRow(300, 2, 10, 3, 'SPEI-A', 'RESP-SPEI-A'),
            $this->transactionRow(301, 3, 20, 3, 'SPEI-B', 'RESP-SPEI-B'),
        ]);

        DB::table('respuestas')->insert([
            $this->responseRow(1, 100, 'RESP-A'),
            $this->responseRow(2, 101, 'RESP-B'),
            $this->responseRow(3, 200, 'RESP-DOM-A', 'TOKEN-A'),
            $this->responseRow(4, 201, 'RESP-DOM-B', 'TOKEN-B'),
        ]);

        DB::table('transaccionesDom')->insert([
            $this->domRow(1, 200, 2, 10, 'DOM-CHARGE-A'),
            $this->domRow(2, 201, 3, 20, 'DOM-CHARGE-B'),
        ]);

        DB::table('consultaspei')->insert([
            ['id' => 1, 'idtransaccion' => 300, 'fecha' => now(), 'reference' => 'SPEI-A', 'codigo' => '00', 'mensaje' => 'ok', 'parcial' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'idtransaccion' => 301, 'fecha' => now(), 'reference' => 'SPEI-B', 'codigo' => '00', 'mensaje' => 'ok', 'parcial' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('pagospei')->insert([
            ['id' => 1, 'idtransaccion' => 300, 'fecha' => now(), 'clabe' => '012345678901234567', 'monto' => 10000, 'transaccion' => 'PAY-A', 'codigo' => '00', 'mensaje' => 'ok', 'condicion' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'idtransaccion' => 301, 'fecha' => now(), 'clabe' => '012345678901234568', 'monto' => 20000, 'transaccion' => 'PAY-B', 'codigo' => '00', 'mensaje' => 'ok', 'condicion' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('cancelaspei')->insert([
            ['id' => 1, 'idtransaccion' => 300, 'fecha' => now(), 'clabe' => '012345678901234567', 'monto' => 10000, 'transaccion' => 'CAN-A', 'codigo' => '00', 'mensaje' => 'ok', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'idtransaccion' => 301, 'fecha' => now(), 'clabe' => '012345678901234568', 'monto' => 20000, 'transaccion' => 'CAN-B', 'codigo' => '00', 'mensaje' => 'ok', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function transactionRow(int $id, int $idusuario, int $idcliente, int $tipo, string $clientReference, string $responseReference): array
    {
        return [
            'id' => $id,
            'folio' => $id,
            'fecha' => now(),
            'User' => 'mock',
            'Password' => 'mock',
            'IntegrationID' => '117',
            'BusinessID' => '000031',
            'PaymentTypes' => '41',
            'IdReference' => str_pad($id, 10, '0', STR_PAD_LEFT),
            'Description' => 'Transaccion ' . $clientReference,
            'Amount' => 10000,
            'Reference' => str_pad($id, 15, '0', STR_PAD_LEFT),
            'ExpirationDate' => now()->addDay()->toDateString(),
            'ClientReference' => $clientReference,
            'response' => '{}',
            'url' => 'https://example.com/' . $id,
            'code' => 'success',
            'message' => 'ok',
            'responseReference' => $responseReference,
            'referenceEmisor' => 'EMISOR-' . $id,
            'idusuario' => $idusuario,
            'idcliente' => $idcliente,
            'tipo' => $tipo,
            'frecuencia' => $tipo === 2 ? 2 : null,
            'ProximoCargo' => $tipo === 2 ? now()->addMonth()->toDateString() : null,
            'ProximoCargoBase' => $tipo === 2 ? now()->addMonth()->toDateString() : null,
            'intentos' => 0,
            'condicion' => 1,
            'productivo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function responseRow(int $id, int $idtransaccion, string $reference, string $token = 'TOKEN'): array
    {
        return [
            'id' => $id,
            'idtransaccion' => $idtransaccion,
            'fecha' => now(),
            'reference' => $reference,
            'status' => 'approved',
            'foliocpagos' => 'FOLIO-' . $id,
            'auth' => 'AUTH-' . $id,
            'amount' => 10000,
            'number_tkn' => $token,
            'cc_expmonth' => '12',
            'cc_expyear' => '30',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function domRow(int $id, int $idtransaccion, int $idusuario, int $idcliente, string $reference): array
    {
        return [
            'id' => $id,
            'fecha' => now(),
            'folio' => $id,
            'idtransaccion' => $idtransaccion,
            'idcliente' => $idcliente,
            'Token' => 'TOKEN-' . $id,
            'Reference' => $reference,
            'Amount' => 10000,
            'response' => '{}',
            'code' => '00',
            'message' => 'ok',
            'response_reference' => $reference,
            'status' => 'approved',
            'idusuario' => $idusuario,
            'productivo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
