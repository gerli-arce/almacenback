<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Response;
use App\Models\Role;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoomController extends Controller
{
    public function store(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'roles', 'create')) {
                throw new Exception('No tienes permisos para listar los roles del sistema');
            }

            if (
                !isset($request->role) ||
                !isset($request->priority)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $roleJpa = new Role();
            $roleJpa->role = $request->role;
            $roleJpa->priority = $request->priority;
            if ($request->description) {
                $roleJpa->description = $request->description;
            }
            $roleJpa->permissions = '{}';
            $roleJpa->status = "1";
            $roleJpa->save();

            $response->setStatus(200);
            $response->setMessage('El rol se a agregado correctamente');
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
