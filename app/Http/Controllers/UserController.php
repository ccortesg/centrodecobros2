<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\User;
use App\Persona;
use Exception;

class UserController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->ajax()) return redirect('/');

        $buscar = $request->buscar;
        $criterio = $request->criterio;

        $query = User::join('personas','users.id','=','personas.id')
        ->join('roles','users.idrol','=','roles.id')
        ->select('personas.id','personas.nombre','personas.tipo_documento','personas.num_documento','personas.direccion','personas.telefono',
        'personas.email','users.usuario','users.condicion','users.idrol','roles.nombre as rol','users.IntegrationID','users.BusinessID',
        'users.productivo');
        
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

    public function store(Request $request)
    {
        if (!$request->ajax()) return redirect('/');

        $this->validate($request, [
            'nombre' => 'required|string|max:100',
            'usuario' => 'required|string|max:191|unique:users,usuario',
            'password' => 'required|string|min:6',
            'idrol' => 'required|integer|exists:roles,id',
            'email' => 'nullable|email|max:191',
            'IntegrationID' => 'nullable|string|max:50',
            'BusinessID' => 'nullable|string|max:50',
            'productivo' => 'nullable|boolean',
        ]);

        try{
            DB::beginTransaction();

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
            $user->IntegrationID = $request->IntegrationID;
            $user->BusinessID = $request->BusinessID;
            $user->productivo = $request->productivo;
            $user->save();

            DB::commit();
        } catch (Exception $e){
            DB::rollBack();
        }
    }

    public function update(Request $request)
    {
        if (!$request->ajax()) return redirect('/');

        $this->validate($request, [
            'id' => 'required|integer|exists:users,id',
            'nombre' => 'required|string|max:100',
            'usuario' => [
                'required',
                'string',
                'max:191',
                Rule::unique('users', 'usuario')->ignore($request->id),
            ],
            'password' => 'nullable|string|min:6',
            'idrol' => 'required|integer|exists:roles,id',
            'email' => 'nullable|email|max:191',
            'IntegrationID' => 'nullable|string|max:50',
            'BusinessID' => 'nullable|string|max:50',
            'productivo' => 'nullable|boolean',
        ]);

        try{
            DB::beginTransaction();

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
            $user->IntegrationID = $request->IntegrationID;
            $user->BusinessID = $request->BusinessID;
            $user->productivo = $request->productivo;
            $user->save();

            DB::commit();
        } catch (Exception $e){
            DB::rollBack();
        }
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
