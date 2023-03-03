<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\guid;
use App\gLibraries\gValidate;
use App\Models\Tower;
use App\Models\Branch;
use App\Models\Stock;
use App\Models\Response;
use App\Models\ViewModels;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class TowerController extends Controller
{
    public function store(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'towers', 'create')) {
                throw new Exception("No tienes permisos para agregar torres");
            }

            if (
                !isset($request->name) 
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $towerValidation = Tower::select(['name'])
                ->where('name', $request->model)
                ->first();

            if ($towerValidation) {
                throw new Exception("Escoja otro nombre para el modelo ");
            }

            $towerJpa = new Tower();
            $towerJpa->name = $request->name;
            $towerJpa->description = $request->description;
            $towerJpa->coordenates = $request->coordenates;
            $towerJpa->address = $request->address;
            $towerJpa->relative_id = guid::short();

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
                    $towerJpa->image_type = $request->image_type;
                    $towerJpa->image_mini = base64_decode($request->image_mini);
                    $towerJpa->image_full = base64_decode($request->image_full);
                } else {
                    $towerJpa->image_type = null;
                    $towerJpa->image_mini = null;
                    $towerJpa->image_full = null;
                }
            }

            $towerJpa->status = "1";
            $towerJpa->save();

            $response->setStatus(200);
            $response->setMessage('La torre se a agregado correctamente');
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

    public function search(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'models', 'read')) {
                throw new Exception('No tienes permisos para listar modelos');
            }

            $modelsJpa = ViewModels::select([
                '*',
            ])->whereNotNull('status')
                ->WhereRaw("model LIKE CONCAT('%', ?, '%')", [$request->term])
                ->orWhereRaw("id LIKE CONCAT('%', ?, '%')", [$request->term])
                ->orderBy('model', 'asc')
                ->get();

            $models = array();
            foreach ($modelsJpa as $modelJpa) {
                $model = gJSON::restore($modelJpa->toArray(), '__');
                $models[] = $model;
            }
            
            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($models);
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

    public function paginate(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'models', 'read')) {
                throw new Exception('No tienes permisos para listar modelos');
            }

            $query = Tower::select([
                'id',
                'name',
                'description',
                'coordenates',
                'address',
                'relative_id',
                'status'
            ])
                ->orderBy($request->order['column'], $request->order['dir']);

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
                if ($column == 'coordenates' || $column == '*') {
                    $q->where('coordenates', $type, $value);
                }
                if ($column == 'address' || $column == '*') {
                    $q->orWhere('address', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->where('description', $type, $value);
                }
            });

            $iTotalDisplayRecords = $query->count();
            $towerJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(Tower::count());
            $response->setData($towerJpa->toArray());
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

            $modelJpa = Tower::select([
                "towers.image_$size as image_content",
                'towers.image_type',

            ])
                ->where('relative_id', $relative_id)
                ->first();

            if (!$modelJpa) {
                throw new Exception('No se encontraron datos');
            }

            if (!$modelJpa->image_content) {
                throw new Exception('No existe imagen');
            }

            $content = $modelJpa->image_content;
            $type = $modelJpa->image_type;
            $response->setStatus(200);
        } catch (\Throwable$th) {
            $ruta = '../storage/images/antena-default.png';
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

    public function update(Request $request)
    {
        $response = new Response();
        try {

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'models', 'update')) {
                throw new Exception('No tienes permisos para actualizar torres');
            }

            $towerJpa = Tower::select(['id'])->find($request->id);
            if (!$towerJpa) {
                throw new Exception("No se puede actualizar este registro");
            }

            if (isset($request->name)) {
                $verifyCatJpa = Tower::select(['id', 'name'])
                    ->where('name', $request->name)
                    ->where('id', '!=', $request->id)
                    ->first();
                if ($verifyCatJpa) {
                    throw new Exception("Elija otro nombre para esta llave");
                }
                $towerJpa->name = $request->name;
            }

            if (isset($request->coordenates)) {
                $towerJpa->coordenates = $request->coordenates;
            }

            if (isset($request->address)) {
                $towerJpa->address = $request->address;
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
                    $towerJpa->image_type = $request->image_type;
                    $towerJpa->image_mini = base64_decode($request->image_mini);
                    $towerJpa->image_full = base64_decode($request->image_full);
                } else {
                    $towerJpa->image_type = null;
                    $towerJpa->image_mini = null;
                    $towerJpa->image_full = null;
                }
            }

            $towerJpa->description = $request->description;

            if (gValidate::check($role->permissions, $branch, 'towers', 'change_status')) {
                if (isset($request->status)) {
                    $towerJpa->status = $request->status;
                }
            }

            $towerJpa->save();

            $response->setStatus(200);
            $response->setMessage('La torre ha sido actualizada correctamente');
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

    public function destroy(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'towers', 'delete_restore')) {
                throw new Exception('No tienes permisos para eliminar torres');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $towerJpa = Tower::find($request->id);
            if (!$towerJpa) {
                throw new Exception('La torre que deseas eliminar no existe');
            }

            $towerJpa->update_date = gTrace::getDate('mysql');
            $towerJpa->_update_user = $userid;
            $towerJpa->status = null;
            $towerJpa->save();

            $response->setStatus(200);
            $response->setMessage('La torre a sido eliminada correctamente');
            $response->setData($role->toArray());
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
    public function restore(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'towers', 'delete_restore')) {
                throw new Exception('No tienes permisos para restaurar torres.');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $modelsJpa = Models::find($request->id);
            if (!$modelsJpa) {
                throw new Exception('La torre que deseas restaurar no existe');
            }

            $modelsJpa->status = "1";
            $modelsJpa->save();

            $response->setStatus(200);
            $response->setMessage('La torre a sido restaurada correctamente');
            $response->setData($role->toArray());
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
