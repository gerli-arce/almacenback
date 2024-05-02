<?php

namespace App\Http\Controllers;

use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Response;
use App\Models\Unity;
use App\Models\Role;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UnityController extends Controller
{
    public function store(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'unities', 'create')) {
                throw new Exception('No tienes permisos para agregar unidades');
            }

            if (
                !isset($request->value) ||
                !isset($request->acronym) ||
                !isset($request->name)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $unityValidation = Unity::select(['acronym', 'name'])
                ->where('acronym', $request->acronym)
                ->orWhere('name', $request->name)
                ->first();

            if ($unityValidation) {
                if ($unityValidation->acronym == $request->acronym) {
                    throw new Exception("Escoja otro acronimo para la unidad");
                }
                if ($unityValidation->name == $request->name) {
                    throw new Exception("Escoja otro nombre para la marca");
                }
            }

            $inityJpa = new Unity();
            $inityJpa->value = $request->value;
            $inityJpa->acronym = $request->acronym;
            $inityJpa->name = $request->name;

            if (isset($request->description)) {
                $inityJpa->description = $request->description;
            }

            $inityJpa->creation_date = gTrace::getDate('mysql');
            $inityJpa->_creation_user = $userid;
            $inityJpa->update_date = gTrace::getDate('mysql');
            $inityJpa->_update_user = $userid;
            $inityJpa->status = "1";
            $inityJpa->save();

            $response->setStatus(200);
            $response->setMessage('La unidad se a agregado correctamente');
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
            // if (!gValidate::check($role->permissions, $branch, 'unities', 'read')) {
            //     throw new Exception('No tienes permisos para listar unidades');
            // }

            $peopleJpa = Unity::select([
                'id',
                'acronym',
                'name',
            ])->whereNotNull('status')
                ->WhereRaw("acronym LIKE CONCAT('%', ?, '%')", [$request->term])
                ->orWhereRaw("name LIKE CONCAT('%', ?, '%')", [$request->term])
                ->orderBy('name', 'asc')
                ->get();

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($peopleJpa->toArray());
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

    public function searchById(Request $request, $id)
    {
        $response = new Response();
        try {

            // [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            // if ($status != 200) {
            //     throw new Exception($message);
            // }
            // if (!gValidate::check($role->permissions, $branch, 'unities', 'read')) {
            //     throw new Exception('No tienes permisos para listar unidades');
            // }

            $peopleJpa = Unity::select('*')->find($id);

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($peopleJpa->toArray());
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

    public function index(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'unities', 'read')) {
                throw new Exception('No tienes permisos para listar las unidades de ' . $branch);
            }

            $brandsJpa = Role::whereNotNull('status')->get();

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($brandsJpa->toArray());
        } catch (\Throwable$th) {
            $response->setMessage($th->getMessage());
            $response->setStatus(400);
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

            if (!gValidate::check($role->permissions, $branch, 'unities', 'read')) {
                throw new Exception('No tienes permisos para listar las unidades  de ' . $branch);
            }

            $query = Unity::select(['*'])
                ->orderBy($request->order['column'], $request->order['dir']);

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;
                if ($column == 'acronym' || $column == '*') {
                    $q->where('acronym', $type, $value);
                }
                if ($column == 'name' || $column == '*') {
                    $q->where('name', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }
            });

            $iTotalDisplayRecords = $query->count();
            $initiesJpa = $query->select('*')
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(Unity::count());
            $response->setData($initiesJpa->toArray());
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
            if (!gValidate::check($role->permissions, $branch, 'unities', 'update')) {
                throw new Exception('No tienes permisos para actualizar unidades');
            }

            $unityJpa = Unity::find($request->id);
            if (!$unityJpa) {
                throw new Exception("No se puede actualizar este registro");
            }

            if (isset($request->name)) {
                $verifyCatJpa = Unity::select(['id', 'name'])
                    ->where('name', $request->name)
                    ->where('id', '!=', $request->id)
                    ->first();
                if ($verifyCatJpa) {
                    throw new Exception("Elija otro nombre para unidad");
                }
                $unityJpa->name = $request->name;
            }

            if (isset($request->acronym)) {
                $verifyCatJpa = Unity::select(['id', 'acronym'])
                    ->where('acronym', $request->acronym)
                    ->where('id', '!=', $request->id)
                    ->first();
                if ($verifyCatJpa) {
                    throw new Exception("Elija otro acronimo para esta marca");
                }
                $unityJpa->acronym = $request->acronym;
            }

            if (isset($request->description)) {
                $unityJpa->description = $request->description;
            }

            if (gValidate::check($role->permissions, $branch, 'unities', 'change_status')) {
                if (isset($request->status)) {
                    $unityJpa->status = $request->status;
                }
            }

            $unityJpa->update_date = gTrace::getDate('mysql');
            $unityJpa->_update_user = $userid;

            $unityJpa->save();

            $response->setStatus(200);
            $response->setMessage('La unidad ha sido actualizada correctamente');
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
            if (!gValidate::check($role->permissions, $branch, 'unities', 'delete_restore')) {
                throw new Exception('No tienes permisos para eliminar unidades en ' . $branch);
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $unityJpa = Unity::find($request->id);
            if (!$unityJpa) {
                throw new Exception('La unidad que deseas eliminar no existe');
            }

            $unityJpa->update_date = gTrace::getDate('mysql');
            $unityJpa->_update_user = $userid;
            $unityJpa->status = null;
            $unityJpa->save();

            $response->setStatus(200);
            $response->setMessage('La unidad a sido eliminada correctamente');
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
            if (!gValidate::check($role->permissions, $branch, 'unities', 'delete_restore')) {
                throw new Exception('No tienes permisos para restaurar inidades en ' . $branch);
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $unityJpa = Unity::find($request->id);
            if (!$unityJpa) {
                throw new Exception('La unidad que deseas restaurar no existe');
            }

            $unityJpa->update_date = gTrace::getDate('mysql');
            $unityJpa->_update_user = $userid;
            $unityJpa->status = "1";
            $unityJpa->save();

            $response->setStatus(200);
            $response->setMessage('La unidad a sido restaurada correctamente');
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
