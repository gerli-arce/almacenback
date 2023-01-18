<?php

namespace App\Http\Controllers;

use App\gLibraries\gFetch;
use App\gLibraries\gJson;
use App\gLibraries\gStatus;
use App\gLibraries\gValidate;
use App\Models\ViewPermissionsByView;
use App\Models\User;
use App\Models\Response;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function login(Request $request)
    {
        $response = new Response();
        try {
           
            if(!isset($request->username)){
                throw new Exception("El nombre de usuario debe ser enviado");
            }

            if(!isset($request->password)){
                throw new Exception("La contraseña debe ser enviada");
            }

            $userJpa = User::where('username', '=', $request->username)
            ->first();

            

            
            $response->setStatus(200);
            $response->setMessage('');
        } catch (\Throwable$th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }
}
