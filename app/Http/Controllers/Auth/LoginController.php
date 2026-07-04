<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\UserActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm(){
        return view('auth.login');
    }

    public function login(Request $request){
        $this->validateLogin($request);        

        if (Auth::attempt(['usuario' => $request->usuario,'password' => $request->password,'condicion'=>1])){
            app(UserActivityLogger::class)->log($request, 'login_success', true, Auth::user(), [
                'usuario' => $request->usuario,
            ]);

            return redirect()->route('main');
        }

        app(UserActivityLogger::class)->log($request, 'login_failed', false, null, [
            'usuario' => $request->usuario,
        ]);

        return back()
        ->withErrors(['usuario' => trans('auth.failed')])
        ->withInput(request(['usuario']));

    }

    protected function validateLogin(Request $request){
        $this->validate($request,[
            'usuario' => 'required|string',
            'password' => 'required|string'
        ]);

    }

    public function logout(Request $request){
        $user = Auth::user();
        app(UserActivityLogger::class)->log($request, 'logout', true, $user);

        Auth::logout();
        $request->session()->invalidate();
        return redirect('/');
    }
}
