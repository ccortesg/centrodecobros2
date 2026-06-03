<?php
 
namespace App\Http\Controllers;
 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Cliente;
use App\Persona;
use App\User;
 
use App\Exports\ClienteExport;
use Excel;

use Exception;
 
class ClienteController extends Controller
{
    private function validarAdministrador(Request $request)
    {
        if (!$this->usuarioEsAdministrador()) {
            return $this->respuestaNoAutorizado($request);
        }

        return null;
    }

    public function index(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
 
        $buscar = $request->buscar;
        $criterio = $request->criterio;
        $offset = $this->offsetPaginacion($request->offset);

        $atributos = [];
        
        $query = Cliente::join('personas','clientes.id','=','personas.id')
        ->join('ciudades','clientes.idciudad','=','ciudades.id')
        ->select('personas.id','personas.nombre','personas.tipo_documento',
        'personas.num_documento','personas.direccion','personas.telefono',
        'personas.email','clientes.contacto','clientes.telefono_contacto',
        'clientes.email_contacto','clientes.idciudad','clientes.rfc', 'ciudades.idestado',
        'clientes.razon_social', 'clientes.banco', 'clientes.cuenta', 'clientes.clabe', 
        'clientes.cuenta_sucursal', 'clientes.cuenta_ciudad', 'clientes.forma_pago', 'clientes.plazo',
        'clientes.regimen');

        if (!$this->usuarioEsAdministrador()) {
            $query->where('clientes.idusuario', '=', \Auth::user()->id);
        }

        if ($buscar!=''){
            if($criterio == "nombre" || $criterio == "email" || $criterio == "telefono") 
                array_push($atributos, ['personas.'.$criterio, 'like', '%'. $buscar . '%']);
            else if($criterio == "razon_social" || $criterio == "rfc" ) 
                array_push($atributos, ['clientes.'.$criterio, 'like', '%'. $buscar . '%']);

            if (!empty($atributos)) {
                $query->where($atributos);
            }
        }
         
        $personas = $query->orderBy('personas.id', 'desc')->paginate($offset);
 
        return [
            'pagination' => [
                'total'        => $personas->total(),
                'current_page' => $personas->currentPage(),
                'per_page'     => $personas->perPage(),
                'last_page'    => $personas->lastPage(),
                'from'         => $personas->firstItem(),
                'to'           => $personas->lastItem(),
            ],
            'personas' => $personas
        ];
    }

    public function exportarCliente(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        
        return Excel::download(new ClienteExport(), 'clientes.xlsx');
    }
 
    public function selectCliente(Request $request){
        if (!$request->ajax()) return redirect('/');
 
        $filtro = $request->filtro;
        $query = Cliente::join('personas','clientes.id','=','personas.id')
                    ->select('personas.id','personas.nombre','clientes.razon_social');

        $query->where(function ($subQuery) use ($filtro) {
            $subQuery->where('personas.nombre', 'like', '%'. $filtro . '%')
                ->orWhere('personas.num_documento', 'like', '%'. $filtro . '%');
        });

        if (!$this->usuarioEsAdministrador()) {
            $query->where('clientes.idusuario', '=', \Auth::user()->id);
        }
        
        $clientes = $query->orderBy('personas.nombre', 'asc')->get();
 
        return ['clientes' => $clientes];
    }
 
    public function store(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
         
        try{
            DB::beginTransaction();

            $num_documento = 1;
            if(Persona::join('clientes','clientes.id','personas.id')
                ->where([['tipo_documento','LIKE','CLIENTE'],['idusuario','=',\Auth::user()->id]])->max(DB::raw('CAST(num_documento AS SIGNED)')) != null){
                $num_documento = Persona::join('clientes','clientes.id','personas.id')
                    ->where([['tipo_documento','LIKE','CLIENTE'],['idusuario','=',\Auth::user()->id]])->max(DB::raw('CAST(num_documento AS SIGNED)')) + 1;
            }
            
            $persona = new Persona();
            $persona->nombre = $request->nombre;
            $persona->tipo_documento = $request->tipo_documento;
            $persona->num_documento = $num_documento;
            $persona->direccion = $request->direccion;
            $persona->telefono = $request->telefono;
            $persona->email = $request->email;
            $persona->save();
                        
            $cliente = new Cliente();
            $cliente->idciudad = $request->idciudad;
            $cliente->rfc = $request->rfc;
            $cliente->razon_social = $request->razon_social;            
            $cliente->contacto = $request->contacto;
            $cliente->telefono_contacto = $request->telefono_contacto;
            $cliente->email_contacto = $request->email_contacto;
            $cliente->banco = $request->banco;
            $cliente->cuenta = $request->cuenta;
            $cliente->clabe = $request->clabe;
            $cliente->cuenta_sucursal = $request->cuenta_sucursal;
            $cliente->cuenta_ciudad = $request->cuenta_ciudad;
            $cliente->forma_pago = $request->forma_pago;
            $cliente->plazo = $request->plazo;
            $cliente->regimen = $request->regimen;
            $cliente->id = $persona->id;
            $cliente->idusuario = \Auth::user()->id;
            $cliente->save();
 
            DB::commit();
 
        } catch (Exception $e){
            DB::rollBack();
            return response()->json([
                'status' => 'Ocurrió un error!',
                'msg' => $e->getMessage()
            ], 422);
        }         
    }
 
    public function update(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
         
        try{
            DB::beginTransaction();
 
            //Buscar primero el cliente a modificar
            $cliente = Cliente::findOrFail($request->id);

            if (!$this->usuarioPuedeOperarRegistro($cliente, 'idusuario', null)) {
                DB::rollBack();
                return $this->respuestaNoAutorizado($request);
            }
 
            $persona = Persona::findOrFail($cliente->id);
 
            $persona->nombre = $request->nombre;
            $persona->tipo_documento = $request->tipo_documento;
            $persona->num_documento = $request->num_documento;
            $persona->direccion = $request->direccion;
            $persona->telefono = $request->telefono;
            $persona->email = $request->email;
            $persona->save();
 
            $cliente->idciudad = $request->idciudad;
            $cliente->rfc = $request->rfc;
            $cliente->razon_social = $request->razon_social;             
            $cliente->contacto = $request->contacto;
            $cliente->telefono_contacto = $request->telefono_contacto;
            $cliente->email_contacto = $request->email_contacto;
            $cliente->banco = $request->banco;
            $cliente->cuenta = $request->cuenta;
            $cliente->clabe = $request->clabe;
            $cliente->cuenta_sucursal = $request->cuenta_sucursal;
            $cliente->cuenta_ciudad = $request->cuenta_ciudad;
            $cliente->forma_pago = $request->forma_pago;
            $cliente->plazo = $request->plazo;      
            $cliente->regimen = $request->regimen;
            $cliente->save();
 
            DB::commit();
 
        } catch (Exception $e){
            DB::rollBack();
            return response()->json([
                'status' => 'Ocurrió un error!',
                'msg' => $e->getMessage()
            ], 422);
        }
 
    }

    public function exportar(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        $clienteExport = new ClienteExport();        
        return Excel::download($clienteExport, 'clientes.xlsx');
    }

    public function consolidarIndex(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        if ($respuesta = $this->validarAdministrador($request)) {
            return $respuesta;
        }

        $idusuario = (int) $request->idusuario;
        $buscar = trim((string) $request->buscar);
        $offset = (int) ($request->offset ?: 10);
        $offset = $offset > 0 ? $offset : 10;

        if ($idusuario <= 0 || !User::where('id', $idusuario)->exists()) {
            return [
                'pagination' => [
                    'total' => 0,
                    'current_page' => 1,
                    'per_page' => $offset,
                    'last_page' => 1,
                    'from' => null,
                    'to' => null,
                ],
                'clientes' => []
            ];
        }

        $query = Cliente::join('personas', 'clientes.id', '=', 'personas.id')
            ->leftJoin('users', 'users.id', '=', 'clientes.idusuario')
            ->select(
                'personas.id',
                'personas.nombre',
                'personas.email',
                'personas.telefono',
                'personas.created_at',
                'clientes.idusuario',
                'users.usuario',
                'clientes.razon_social',
                'clientes.rfc'
            )
            ->where('clientes.idusuario', '=', $idusuario);

        if ($buscar !== '') {
            $query->where(function ($subQuery) use ($buscar) {
                $subQuery->where('personas.nombre', 'like', '%' . $buscar . '%')
                    ->orWhere('personas.email', 'like', '%' . $buscar . '%')
                    ->orWhere('personas.telefono', 'like', '%' . $buscar . '%');
            });
        }

        $clientes = $query
            ->orderByRaw('CASE WHEN personas.created_at IS NULL THEN 1 ELSE 0 END, personas.created_at ASC, personas.id ASC')
            ->paginate($offset);

        return [
            'pagination' => [
                'total' => $clientes->total(),
                'current_page' => $clientes->currentPage(),
                'per_page' => $clientes->perPage(),
                'last_page' => $clientes->lastPage(),
                'from' => $clientes->firstItem(),
                'to' => $clientes->lastItem(),
            ],
            'clientes' => $clientes
        ];
    }

    public function consolidarCombinar(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        if ($respuesta = $this->validarAdministrador($request)) {
            return $respuesta;
        }

        $validated = $request->validate([
            'idusuario' => 'required|integer|exists:users,id',
            'cliente_ids' => 'required|array|min:2',
            'cliente_ids.*' => 'required|integer|distinct'
        ], [
            'cliente_ids.min' => 'Debes seleccionar al menos 2 clientes para combinar.'
        ]);

        $idusuario = (int) $validated['idusuario'];
        $clienteIds = collect($validated['cliente_ids'])->map(function ($id) {
            return (int) $id;
        })->unique()->values();

        if ($clienteIds->count() < 2) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Debes seleccionar al menos 2 clientes válidos para combinar.'
            ], 422);
        }

        try {
            $result = DB::transaction(function () use ($idusuario, $clienteIds) {
                $clientesSeleccionados = Cliente::join('personas', 'clientes.id', '=', 'personas.id')
                    ->where('clientes.idusuario', '=', $idusuario)
                    ->whereIn('clientes.id', $clienteIds->all())
                    ->select(
                        'clientes.id',
                        'clientes.idciudad',
                        'clientes.rfc',
                        'clientes.razon_social',
                        'clientes.contacto',
                        'clientes.telefono_contacto',
                        'clientes.email_contacto',
                        'clientes.banco',
                        'clientes.cuenta',
                        'clientes.clabe',
                        'clientes.cuenta_sucursal',
                        'clientes.cuenta_ciudad',
                        'clientes.forma_pago',
                        'clientes.plazo',
                        'clientes.regimen',
                        'personas.nombre',
                        'personas.direccion',
                        'personas.telefono',
                        'personas.email',
                        'personas.created_at'
                    )
                    ->lockForUpdate()
                    ->get();

                if ($clientesSeleccionados->count() !== $clienteIds->count()) {
                    throw new Exception('Algunos clientes ya no existen, no pertenecen al usuario seleccionado o ya fueron combinados.');
                }

                $ordenados = $clientesSeleccionados->sort(function ($a, $b) {
                    if (is_null($a->created_at) && is_null($b->created_at)) {
                        return $a->id <=> $b->id;
                    }

                    if (is_null($a->created_at)) {
                        return 1;
                    }

                    if (is_null($b->created_at)) {
                        return -1;
                    }

                    if ($a->created_at == $b->created_at) {
                        return $a->id <=> $b->id;
                    }

                    return strtotime($a->created_at) <=> strtotime($b->created_at);
                })->values();

                $keep = $ordenados->first();
                $mergeIds = $ordenados->pluck('id')->reject(function ($id) use ($keep) {
                    return $id === $keep->id;
                })->values();

                if ($mergeIds->isEmpty()) {
                    throw new Exception('No fue posible determinar clientes secundarios para combinar.');
                }

                $keepPersona = Persona::lockForUpdate()->findOrFail($keep->id);
                $keepCliente = Cliente::lockForUpdate()->findOrFail($keep->id);

                $secundarios = $ordenados->filter(function ($cliente) use ($keep) {
                    return $cliente->id !== $keep->id;
                })->values();

                $fieldsPersona = ['direccion', 'telefono', 'email'];
                foreach ($fieldsPersona as $campo) {
                    if (empty($keepPersona->{$campo})) {
                        foreach ($secundarios as $sec) {
                            if (!empty($sec->{$campo})) {
                                $keepPersona->{$campo} = $sec->{$campo};
                                break;
                            }
                        }
                    }
                }

                $fieldsCliente = [
                    'idciudad', 'rfc', 'razon_social', 'contacto', 'telefono_contacto',
                    'email_contacto', 'banco', 'cuenta', 'clabe', 'cuenta_sucursal',
                    'cuenta_ciudad', 'forma_pago', 'plazo', 'regimen'
                ];
                foreach ($fieldsCliente as $campo) {
                    if (empty($keepCliente->{$campo}) || (int) $keepCliente->{$campo} === 0) {
                        foreach ($secundarios as $sec) {
                            if (!empty($sec->{$campo}) && (string) $sec->{$campo} !== '0') {
                                $keepCliente->{$campo} = $sec->{$campo};
                                break;
                            }
                        }
                    }
                }

                $keepPersona->save();
                $keepCliente->save();

                $actualizadasTransacciones = DB::table('transacciones')
                    ->whereIn('idcliente', $mergeIds->all())
                    ->update(['idcliente' => $keep->id]);

                $actualizadasTransaccionesDom = DB::table('transaccionesDom')
                    ->whereIn('idcliente', $mergeIds->all())
                    ->update(['idcliente' => $keep->id]);

                $actualizadosArchivos = DB::table('archivos')
                    ->whereIn('idpersona', $mergeIds->all())
                    ->update(['idpersona' => $keep->id]);

                if (DB::getSchemaBuilder()->hasTable('tmp_personas_merge')) {
                    foreach ($mergeIds as $mergeId) {
                        DB::table('tmp_personas_merge')->updateOrInsert([
                            'merge_id' => $mergeId
                        ], [
                            'keep_id' => $keep->id,
                            'motivo' => 'MANUAL'
                        ]);
                    }
                }

                $pendientes = [
                    'transacciones' => DB::table('transacciones')->whereIn('idcliente', $mergeIds->all())->count(),
                    'transaccionesDom' => DB::table('transaccionesDom')->whereIn('idcliente', $mergeIds->all())->count(),
                    'archivos' => DB::table('archivos')->whereIn('idpersona', $mergeIds->all())->count(),
                ];

                if ($pendientes['transacciones'] > 0 || $pendientes['transaccionesDom'] > 0 || $pendientes['archivos'] > 0) {
                    throw new Exception('Aún existen referencias activas hacia clientes secundarios.');
                }

                Cliente::whereIn('id', $mergeIds->all())->delete();
                Persona::whereIn('id', $mergeIds->all())->delete();

                return [
                    'keep_id' => $keep->id,
                    'merge_ids' => $mergeIds->all(),
                    'actualizadas_transacciones' => $actualizadasTransacciones,
                    'actualizadas_transacciones_dom' => $actualizadasTransaccionesDom,
                    'actualizados_archivos' => $actualizadosArchivos,
                ];
            });

            return response()->json([
                'status' => 'ok',
                'msg' => 'Clientes combinados correctamente.',
                'data' => $result
            ]);
        } catch (Exception $e) {
            Log::error('Error al consolidar clientes', [
                'idusuario' => $idusuario,
                'cliente_ids' => $clienteIds->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'msg' => $e->getMessage()
            ], 422);
        }
    }

    public function depurarIndex(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        if ($respuesta = $this->validarAdministrador($request)) {
            return $respuesta;
        }

        $idusuario = (int) $request->idusuario;
        $buscar = trim((string) $request->buscar);
        $offset = (int) ($request->offset ?: 10);
        $offset = $offset > 0 ? $offset : 10;

        if ($idusuario <= 0 || !User::where('id', $idusuario)->exists()) {
            return [
                'pagination' => [
                    'total' => 0,
                    'current_page' => 1,
                    'per_page' => $offset,
                    'last_page' => 1,
                    'from' => null,
                    'to' => null,
                ],
                'clientes' => []
            ];
        }

        $query = Cliente::join('personas', 'clientes.id', '=', 'personas.id')
            ->leftJoin('users', 'users.id', '=', 'clientes.idusuario')
            ->select(
                'personas.id',
                'personas.nombre',
                'personas.email',
                'personas.telefono',
                'personas.created_at',
                'clientes.idusuario',
                'users.usuario',
                'clientes.razon_social',
                'clientes.rfc'
            )
            ->where('clientes.idusuario', '=', $idusuario)
            ->whereNotExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('transacciones')
                    ->whereColumn('transacciones.idcliente', 'clientes.id');
            })
            ->whereNotExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('transaccionesDom')
                    ->whereColumn('transaccionesDom.idcliente', 'clientes.id');
            })
            ->whereNotExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('archivos')
                    ->whereColumn('archivos.idpersona', 'clientes.id');
            })
            ->whereNotExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('users as users_bloqueo')
                    ->whereColumn('users_bloqueo.id', 'clientes.id');
            });

        if ($buscar !== '') {
            $query->where(function ($subQuery) use ($buscar) {
                $subQuery->where('personas.nombre', 'like', '%' . $buscar . '%')
                    ->orWhere('personas.email', 'like', '%' . $buscar . '%')
                    ->orWhere('personas.telefono', 'like', '%' . $buscar . '%');
            });
        }

        $clientes = $query
            ->orderByRaw('CASE WHEN personas.created_at IS NULL THEN 1 ELSE 0 END, personas.created_at ASC, personas.id ASC')
            ->paginate($offset);

        return [
            'pagination' => [
                'total' => $clientes->total(),
                'current_page' => $clientes->currentPage(),
                'per_page' => $clientes->perPage(),
                'last_page' => $clientes->lastPage(),
                'from' => $clientes->firstItem(),
                'to' => $clientes->lastItem(),
            ],
            'clientes' => $clientes
        ];
    }

    public function depurarEliminar(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        if ($respuesta = $this->validarAdministrador($request)) {
            return $respuesta;
        }

        $validated = $request->validate([
            'idusuario' => 'required|integer|exists:users,id',
            'cliente_ids' => 'required|array|min:1',
            'cliente_ids.*' => 'required|integer|distinct'
        ], [
            'cliente_ids.min' => 'Debes seleccionar al menos 1 cliente para depurar.'
        ]);

        $idusuario = (int) $validated['idusuario'];
        $clienteIds = collect($validated['cliente_ids'])->map(function ($id) {
            return (int) $id;
        })->unique()->values();

        if ($clienteIds->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Debes seleccionar al menos un cliente válido para depurar.'
            ], 422);
        }

        try {
            $result = DB::transaction(function () use ($idusuario, $clienteIds) {
                $clientesSeleccionados = Cliente::where('idusuario', '=', $idusuario)
                    ->whereIn('id', $clienteIds->all())
                    ->lockForUpdate()
                    ->get(['id']);

                if ($clientesSeleccionados->count() !== $clienteIds->count()) {
                    throw new Exception('Algunos clientes ya no existen o no pertenecen al usuario seleccionado.');
                }

                $ids = $clientesSeleccionados->pluck('id')->values();

                $bloqueos = [
                    'transacciones' => DB::table('transacciones')->whereIn('idcliente', $ids->all())->lockForUpdate()->count(),
                    'transaccionesDom' => DB::table('transaccionesDom')->whereIn('idcliente', $ids->all())->lockForUpdate()->count(),
                    'archivos' => DB::table('archivos')->whereIn('idpersona', $ids->all())->lockForUpdate()->count(),
                    'users' => DB::table('users')->whereIn('id', $ids->all())->lockForUpdate()->count(),
                ];

                if ($bloqueos['transacciones'] > 0 || $bloqueos['transaccionesDom'] > 0 || $bloqueos['archivos'] > 0 || $bloqueos['users'] > 0) {
                    throw new Exception('Uno o más clientes seleccionados ya no son elegibles para depuración. Actualiza el listado e intenta de nuevo.');
                }

                $eliminadosClientes = Cliente::whereIn('id', $ids->all())->delete();
                $eliminadosPersonas = Persona::whereIn('id', $ids->all())->delete();

                return [
                    'cliente_ids' => $ids->all(),
                    'eliminados_clientes' => $eliminadosClientes,
                    'eliminados_personas' => $eliminadosPersonas,
                ];
            });

            return response()->json([
                'status' => 'ok',
                'msg' => 'Clientes depurados correctamente.',
                'data' => $result
            ]);
        } catch (Exception $e) {
            Log::error('Error al depurar clientes', [
                'idusuario' => $idusuario,
                'cliente_ids' => $clienteIds->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'msg' => $e->getMessage()
            ], 422);
        }
    }
}
