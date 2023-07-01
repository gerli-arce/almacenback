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
                !isset($request->name)
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
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function update(Request $request)
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
                !isset($request->name)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $roomJpa = Room::find($request->id);
            $roomJpa->name = $request->name;
            $roomJpa->description = $request->description;

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

            $roomJpa->update_date = gTrace::getDate('mysql');
            $roomJpa->_update_user = $userid;
            $roomJpa->save();

            $response->setStatus(200);
            $response->setMessage('');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function paginate(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'users', 'read')) {
                throw new Exception('No tienes permisos para listar usuarios');
            }

            $dat = gValidate::check($role->permissions, $branch, 'users', 'read');

            $query = Room::select([
                'id',
                'name',
                'description',
                'relative_id',
                '_creation_user',
                'creation_date',
                '_update_user',
                'update_date',
                'status',
            ])
                ->orderBy($request->order['column'], $request->order['dir']);

            // if (!$request->all || !gValidate::check($role->permissions, 'views', 'see_trash')) {
            // }

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;

                if ($column == 'name' || $column == '*') {
                    $q->where('name', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->where('description', $type, $value);
                }
            });
            $iTotalDisplayRecords = $query->count();

            $roomsJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();



            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(Room::count());
            $response->setData($roomsJpa->toArray());
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function image($relative_id, $size)
    {
        $response = new Response();
        $content = null;
        $type = null;
        try {
            if ($size != 'full') {
                $size = 'mini';
            }
            if (
                !isset($relative_id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $roomJpa = Room::select([
                "room.image_$size as image_content",
                'room.image_type',

            ])
                ->where('relative_id', $relative_id)
                ->first();

            if (!$roomJpa) {
                throw new Exception('No se encontraron datos');
            }

            if (!$roomJpa->image_content) {
                throw new Exception('No existe imagen');
            }

            $content = $roomJpa->image_content;
            $type = $roomJpa->image_type;
            $response->setStatus(200);
        } catch (\Throwable $th) {
            $ruta = '../storage/images/room-default.jpg';
            $fp = fopen($ruta, 'r');
            $datos_image = fread($fp, filesize($ruta));
            $datos_image = addslashes($datos_image);
            fclose($fp);
            $content = stripslashes($datos_image);
            $type = 'image/jpeg';
            $response->setStatus(400);
        } finally {
            return response(
                $content,
                $response->getStatus()
            )->header('Content-Type', $type);
        }
    }
}
