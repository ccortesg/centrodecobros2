<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\User;
use App\Persona;

class UserController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->ajax()) return redirect('/');

        $buscar = $request->buscar;
        $criterio = in_array($request->criterio, ['nombre', 'num_documento', 'email', 'telefono'], true)
            ? $request->criterio
            : 'nombre';

        $query = User::join('personas','users.id','=','personas.id')
        ->join('roles','users.idrol','=','roles.id')
        ->leftJoin('users as cliente_vinculado', 'users.idusuario_vinculado', '=', 'cliente_vinculado.id')
        ->leftJoin('personas as persona_vinculada', 'cliente_vinculado.id', '=', 'persona_vinculada.id')
        ->select('personas.id','personas.nombre','personas.tipo_documento','personas.num_documento','personas.direccion','personas.telefono',
        'personas.email','users.usuario','users.condicion','users.idrol','roles.nombre as rol','users.IntegrationID','users.BusinessID',
        'users.productivo', 'users.idusuario_vinculado', 'cliente_vinculado.usuario as usuario_vinculado',
        'persona_vinculada.nombre as nombre_vinculado');
        
        if ($buscar!=''){            
            $query->where('personas.'.$criterio, 'like', '%'. $buscar . '%');
        }

        $personas = $query->orderBy('personas.id', 'desc')->paginate(10);
        
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

    public function selectUsuario(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        $users = User::where('condicion','=','1')
        ->select('id','usuario')->orderBy('usuario', 'asc')->get();
        return ['usuarios' => $users];
    }

    public function selectClientesVinculables(Request $request)
    {
        if (!$request->ajax()) return redirect('/');

        $clientes = User::join('personas', 'users.id', '=', 'personas.id')
            ->where('users.idrol', User::ROLE_CLIENTE)
            ->where('users.condicion', 1)
            ->select('users.id', 'users.usuario', 'personas.nombre', 'users.productivo')
            ->orderBy('personas.nombre', 'asc')
            ->get();

        return ['clientes' => $clientes];
    }

    public function store(Request $request)
    {
        if (!$request->ajax()) return redirect('/');

        $this->validate($request, $this->validationRules($request));
        $clienteVinculado = $this->clienteVinculadoValidado($request);

        DB::transaction(function () use ($request, $clienteVinculado) {

            $persona = new Persona();
            $persona->nombre = $request->nombre;
            $persona->tipo_documento = $request->tipo_documento;
            $persona->num_documento = $request->num_documento;
            $persona->direccion = $request->direccion;
            $persona->telefono = $request->telefono;
            $persona->email = $request->email;
            $persona->save();

            $user = new User();
            $user->id = $persona->id;
            $user->idrol = $request->idrol;
            $user->usuario = $request->usuario;
            $user->password = bcrypt( $request->password);
            $user->condicion = '1';
            $this->aplicarConfiguracionRol($user, $request, $clienteVinculado);
            $user->save();
        });

        return response()->json(['status' => 'success']);
    }

    public function update(Request $request)
    {
        if (!$request->ajax()) return redirect('/');

        $this->validate($request, $this->validationRules($request, true));
        $clienteVinculado = $this->clienteVinculadoValidado($request, (int) $request->id);

        DB::transaction(function () use ($request, $clienteVinculado) {

            $user = User::findOrFail($request->id);
            $persona = Persona::findOrFail($user->id);
            $persona->nombre = $request->nombre;
            $persona->tipo_documento = $request->tipo_documento;
            $persona->num_documento = $request->num_documento;
            $persona->direccion = $request->direccion;
            $persona->telefono = $request->telefono;
            $persona->email = $request->email;
            $persona->save();

            
            $user->usuario = $request->usuario;
            if ($request->filled('password')) {
                $user->password = bcrypt($request->password);
            }
            $user->condicion = '1';
            $user->idrol = $request->idrol;
            $this->aplicarConfiguracionRol($user, $request, $clienteVinculado);
            $user->save();
        });

        return response()->json(['status' => 'success']);
    }

    private function validationRules(Request $request, $updating = false)
    {
        $roleIsViewer = (int) $request->idrol === User::ROLE_CONSULTA_RESPUESTAS;
        $linkRules = [
            Rule::requiredIf($roleIsViewer),
            'nullable',
            'integer',
            Rule::exists('users', 'id')->where(function ($query) {
                $query->where('idrol', User::ROLE_CLIENTE)->where('condicion', 1);
            }),
        ];

        if ($updating) {
            $linkRules[] = Rule::notIn([(int) $request->id]);
        }

        $rules = [
            'nombre' => 'required|string|max:100',
            'usuario' => $updating ? [
                'required',
                'string',
                'max:191',
                Rule::unique('users', 'usuario')->ignore($request->id),
            ] : 'required|string|max:191|unique:users,usuario',
            'password' => $updating ? 'nullable|string|min:6' : 'required|string|min:6',
            'idrol' => 'required|integer|exists:roles,id',
            'idusuario_vinculado' => $linkRules,
            'email' => 'nullable|email|max:191',
            'IntegrationID' => [Rule::requiredIf(!$roleIsViewer), 'nullable', 'string', 'max:50'],
            'BusinessID' => [Rule::requiredIf(!$roleIsViewer), 'nullable', 'string', 'max:50'],
            'productivo' => [Rule::requiredIf(!$roleIsViewer), 'nullable', 'boolean'],
        ];

        if ($updating) {
            $rules['id'] = 'required|integer|exists:users,id';
        }

        return $rules;
    }

    private function clienteVinculadoValidado(Request $request, $userId = null)
    {
        if ((int) $request->idrol !== User::ROLE_CONSULTA_RESPUESTAS) {
            return null;
        }

        $cliente = User::where('id', $request->idusuario_vinculado)
            ->where('idrol', User::ROLE_CLIENTE)
            ->where('condicion', 1)
            ->first();

        if (!$cliente || ($userId !== null && (int) $cliente->id === (int) $userId)) {
            throw ValidationException::withMessages([
                'idusuario_vinculado' => ['El Cliente vinculado no es válido o no está activo.'],
            ]);
        }

        return $cliente;
    }

    private function aplicarConfiguracionRol(User $user, Request $request, $clienteVinculado)
    {
        if ((int) $request->idrol === User::ROLE_CONSULTA_RESPUESTAS) {
            $user->idusuario_vinculado = $clienteVinculado->id;
            $user->IntegrationID = 'N/A';
            $user->BusinessID = 'N/A';
            $user->productivo = $clienteVinculado->productivo;

            return;
        }

        $user->idusuario_vinculado = null;
        $user->IntegrationID = $request->IntegrationID;
        $user->BusinessID = $request->BusinessID;
        $user->productivo = $request->productivo;
    }

    public function desactivar(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        $user = User::findOrFail($request->id);
        $user->condicion = '0';
        $user->save();
    }

    public function activar(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        $user = User::findOrFail($request->id);
        $user->condicion = '1';
        $user->save();
    }
}
