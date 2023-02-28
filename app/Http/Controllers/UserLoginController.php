<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Auth;
use Illuminate\Http\Request;

class UserLoginController extends Controller
{
    public function userRegisterForm(Request $request)
    {
        return view('user-register-form');
    }

    public function userRegister(Request $request)
    {
        $this->validate($request, [
            'email' => 'email|required',
            'password' => 'required',
        ]);

        $request = $request->all();

        $request['password'] = bcrypt($request['password']);

        User::create($request);

        return redirect()->route('login.form');
    }

    public function loginForm(Request $request)
    {
        if (Auth::check()) {

            return redirect()->route('home');
        }

        return view('user-login');
    }

    public function loginAttempt(Request $request)
    {
        $this->validate($request, [
            'email' => 'email|required',
            'password' => 'required',
        ]);

        if (!Auth::attempt(['email' => $request->email, 'password' => $request->password])) {

            return redirect()->route('login.form');
        }

        return redirect()->route('home');
    }

    public function home()
    {
        if (!Auth::check()) {

            return redirect()->route('login.fomr');
        }

        return view('home');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        return redirect()->route('login.form');
    }
}
