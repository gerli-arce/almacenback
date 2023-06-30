<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\gLibraries\guid;
use App\Models\Response;
use App\Models\Room;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoomController extends Controller
{
    public function store(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'room', 'create')) {
                throw new Exception('No tienes permisos para listar los cuartos del sistema');
            }

            if (
                !isset($request->role) ||
                !isset($request->priority)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $roomJpa = new Room();
            $roomJpa->name = $request->name;
            if ($request->description) {
                $roomJpa->description = $request->description;
            }

            if (
                isset($request->image_type) &&
                isset($request->image_mini) &&
                isset($request->image_full)
            ) {
                if (
                    $request->image_type != "none" &&
                    $request->image_mini != "none" &&
                    $request->image_full != "none"
                ) {
                    $roomJpa->image_type = $request->image_type;
                    $roomJpa->image_mini = base64_decode($request->image_mini);
                    $roomJpa->image_full = base64_decode($request->image_full);
                } else {
                    $roomJpa->image_type = null;
                    $roomJpa->image_mini = null;
                    $roomJpa->image_full = null;
                }
            }

            $roomJpa->relative_id = guid::short();
            $roomJpa->creation_date = gTrace::getDate('mysql');
            $roomJpa->_creation_user = $userid;
            $roomJpa->update_date = gTrace::getDate('mysql');
            $roomJpa->_update_user = $userid;
            $roomJpa->status = "1";
            $roomJpa->save();

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
