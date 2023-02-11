<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\gUid;
use App\gLibraries\gValidate;
use App\Models\Models;
use App\Models\Response;
use App\Models\ViewModels;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModelsController extends Controller
{
    public function store(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'models', 'create')) {
                throw new Exception("No tienes permisos para agregar modelos en ");
            }

            if (
                !isset($request->model) ||
                !isset($request->_brand) ||
                !isset($request->_category)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $modelValidation = Models::select(['model'])
                ->where('model', $request->model)
                ->first();

            if ($modelValidation) {
                throw new Exception("Escoja otro nombre para el modelo ");
            }

            $modelJpa = new Models();
            $modelJpa->model = $request->model;
            $modelJpa->_brand = $request->_brand;
            $modelJpa->_category = $request->_category;
            $modelJpa->relative_id = guid::short();

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
                    $modelJpa->image_type = $request->image_type;
                    $modelJpa->image_mini = base64_decode($request->image_mini);
                    $modelJpa->image_full = base64_decode($request->image_full);
                } else {
                    $modelJpa->image_type = null;
                    $modelJpa->image_mini = null;
                    $modelJpa->image_full = null;
                }
            }

            if (isset($request->description)) {
                $modelJpa->description = $request->description;
            }

            $modelJpa->status = "1";
            $modelJpa->save();

            $response->setStatus(200);
            $response->setMessage('El modelo se a agregado correctamente');
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

            $query = ViewModels::select(['*'])
                ->orderBy($request->order['column'], $request->order['dir']);

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;
                if ($column == 'model' || $column == '*') {
                    $q->where('model', $type, $value);
                }
                if ($column == 'brand__brand' || $column == '*') {
                    $q->where('brand__brand', $type, $value);
                }
                if ($column == 'category__category' || $column == '*') {
                    $q->where('category__category', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }
            });

            $iTotalDisplayRecords = $query->count();
            $modelsJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $models = array();
            foreach ($modelsJpa as $modelJpa) {
                $model = gJSON::restore($modelJpa->toArray(), '__');
                $models[] = $model;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewModels::count());
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

            $modelJpa = Models::select([
                "models.image_$size as image_content",
                'models.image_type',

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
            $ruta = '../storage/images/brands-default.jpg';
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
                throw new Exception('No tienes permisos para actualizar modelos');
            }

            $modelJpa = Models::select(['id'])->find($request->id);
            if (!$modelJpa) {
                throw new Exception("No se puede actualizar este registro");
            }

            if (isset($request->model)) {
                $verifyCatJpa = Models::select(['id', 'model'])
                    ->where('model', $request->model)
                    ->where('id', '!=', $request->id)
                    ->first();
                if ($verifyCatJpa) {
                    throw new Exception("Elija otro nombre para este modelo");
                }
                $modelJpa->model = $request->model;
            }

            if (isset($request->_brand)) {
                $modelJpa->_brand = $request->_brand;
            }

            if (isset($request->_category)) {
                $modelJpa->_category = $request->_category;
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
                    $modelJpa->image_type = $request->image_type;
                    $modelJpa->image_mini = base64_decode($request->image_mini);
                    $modelJpa->image_full = base64_decode($request->image_full);
                } else {
                    $modelJpa->image_type = null;
                    $modelJpa->image_mini = null;
                    $modelJpa->image_full = null;
                }
            }

            if (isset($request->description)) {
                $modelJpa->description = $request->description;
            }

            if (gValidate::check($role->permissions, $branch, 'models', 'change_status')) {
                if (isset($request->status)) {
                    $modelJpa->status = $request->status;
                }
            }

            $modelJpa->save();

            $response->setStatus(200);
            $response->setMessage('El modelo ha sido actualizado correctamente');
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
            if (!gValidate::check($role->permissions, $branch, 'models', 'delete_restore')) {
                throw new Exception('No tienes permisos para eliminar modelos');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $modelsJpa = Models::find($request->id);
            if (!$modelsJpa) {
                throw new Exception('El modelo que deseas eliminar no existe');
            }

            $modelsJpa->update_date = gTrace::getDate('mysql');
            $modelsJpa->_update_user = $userid;
            $modelsJpa->status = null;
            $modelsJpa->save();

            $response->setStatus(200);
            $response->setMessage('El modelo a sido eliminado correctamente');
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
            if (!gValidate::check($role->permissions, $branch, 'models', 'delete_restore')) {
                throw new Exception('No tienes permisos para restaurar modelos.');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $modelsJpa = Models::find($request->id);
            if (!$modelsJpa) {
                throw new Exception('El modelo que deseas restaurar no existe');
            }

            $modelsJpa->status = "1";
            $modelsJpa->save();

            $response->setStatus(200);
            $response->setMessage('El modelo a sido restaurado correctamente');
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
