<?php

namespace App\gLibraries;

use Illuminate\Http\Request;
use App\Models\ViewUsers;
use Exception;

class auth{
    public function val(Request $request, string $view, string $permission)
    {
        try {
            if( $request->header('Auth-Token') == null || $request->header('Auth-User') == null){
                throw new Exception('Error: Datos de cabecera deben ser enviados');
            }

            $userJpa = ViewUsers::select()
                ->where('auth_token', $request->header('Auth-Token'))
                ->where('username', $request->header('Auth-User'))
                ->first();

            if (!$userJpa) {
                throw new Exception('No existe un registro de usuario que coincida con las credenciales enviadas');
            }


            $res = $userJpa;

        } catch (\Throwable $th) {
            $res = [$th->getMessage];
        }finally {
            return $res;
        }
    }
}