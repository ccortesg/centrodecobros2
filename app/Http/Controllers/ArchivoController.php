<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;

use App\Archivo;
use App\Cliente;
use Exception;

class ArchivoController extends Controller
{
    public $destinationPath = "archivos/";

    private function puedeAccederPersona($idpersona)
    {
        if ($this->usuarioEsAdministrador()) {
            return true;
        }

        return Cliente::where('id', '=', $idpersona)
            ->where('idusuario', '=', \Auth::user()->id)
            ->exists();
    }

    public function index(Request $request)
    {
        if (!$request->ajax()) return redirect('/');

        $idpersona = $request->idpersona;       

        if (!$this->puedeAccederPersona($idpersona)) {
            return $this->respuestaNoAutorizado($request);
        }
        
        $archivos = Archivo::join('personas','archivos.idpersona','=','personas.id')
        ->select('archivos.id','archivos.idpersona','archivos.nombre','archivos.extension','archivos.hashname')
        ->where('archivos.idpersona', '=', $idpersona)
        ->orderBy('archivos.id', 'desc')->paginate(20);

        return [           
            'archivos' => $archivos
        ];
    }

    public function store(Request $request)
    {        
        if (!$request->ajax()) return redirect('/');

        $request->validate([
            'archivo' => 'required|mimes:jpg,jpeg,png,xlx,xlxs,xls,doc,docx,pdf|max:16384'
            ]);

        $archivo = new Archivo();

        if($request->file()){
            if (!$this->puedeAccederPersona($request->idpersona)) {
                return $this->respuestaNoAutorizado($request);
            }

            try{
                DB::beginTransaction();
                
                $file = $request->file('archivo');            

                $filename = $file->hashName();
    
                Storage::disk('local')->putFileAs(
                    $this->destinationPath,
                    $file,
                    $filename
                );
                
                $archivo->idpersona = $request->idpersona;
                $archivo->nombre = $file->getClientOriginalName();
                $archivo->hashname = $filename;
                $archivo->extension = $file->extension();
                $archivo->save();
                
                DB::commit();

            } catch (Exception $e){
                DB::rollBack();
            }        

        }        
    }
    
    public function delete(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        $archivo = Archivo::findOrFail($request->id);
        if (!$this->puedeAccederPersona($archivo->idpersona)) {
            return $this->respuestaNoAutorizado($request);
        }
        Storage::disk('local')->delete($this->destinationPath.$archivo->hashname);
        $archivo->delete();
    }


    public function download(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        
        $archivo = Archivo::findOrFail($request->id);
        if (!$this->puedeAccederPersona($archivo->idpersona)) {
            return $this->respuestaNoAutorizado($request);
        }
        $file = Storage::disk('local')->get($this->destinationPath.$archivo->hashname);

        $headers = [
            'Content-Type' => 'application/'.$archivo->extension,
        ];
       
        return $file;
    }

}
