<?php
 
namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
 
use Illuminate\Http\Request;
 
class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $anio=date('Y');
        $driver = DB::connection()->getDriverName();
        $monthExpression = $driver === 'sqlite'
            ? "CAST(strftime('%m', t.fecha) AS INTEGER)"
            : 'MONTH(t.fecha)';
        $yearExpression = $driver === 'sqlite'
            ? "CAST(strftime('%Y', t.fecha) AS INTEGER)"
            : 'YEAR(t.fecha)';

        $transacciones=DB::table('transacciones as t')
        ->select(DB::raw($monthExpression . ' as mes'),
        DB::raw($yearExpression . ' as anio'),
        DB::raw('SUM((t.amount/100)) as total'))
        ->where([['t.idusuario', '=', \Auth::user()->id],['t.productivo', '=', \Auth::user()->productivo]])
        ->groupBy(DB::raw($monthExpression),DB::raw($yearExpression))
        ->get();

        $importes=DB::table('transaccionesDom as t')
        ->select(DB::raw($monthExpression . ' as mes'),
        DB::raw($yearExpression . ' as anio'),
        DB::raw('SUM((t.amount/100)) as total'))
        ->where([['t.idusuario', '=', \Auth::user()->id],['t.productivo', '=', \Auth::user()->productivo]])
        ->groupBy(DB::raw($monthExpression),DB::raw($yearExpression))
        ->get();
 
        return ['transacciones'=>$transacciones, 'importes'=>$importes];      
 
    }
}
