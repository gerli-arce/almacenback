<?php

namespace App\Http\Controllers;

use App\gLibraries\gValidate;
use App\Models\CarComponents;
use App\Models\Response;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CarsComponentsController extends Controller
{

    public function store(Request $request)
    {
        $response = new Response();
        try {
            
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'cars_components', 'create')) {
                throw new Exception('No tienes permisos para agregar componentes de vheículo');
            }

            if (
                !isset($request->component) ||
                !isset($request->_part)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $carsComponentsValidation = CarComponents::select(['component'])
                ->where('component', $request->component)
                ->where('_part', $request->_part)
                ->first();

            if ($carsComponentsValidation) {
                throw new Exception("El componente ya existe.");
            }

            $carComponentsJpa = new CarComponents();
            $carComponentsJpa->component = $request->component;
            $carComponentsJpa->_part = $request->_part;

            $carComponentsJpa->description = $request->description;

            $carComponentsJpa->status = "1";

            $carComponentsJpa->save();

            $response->setStatus(200);
            $response->setMessage('Componente agregada correctamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }$response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'cars_parts', 'create')) {
                throw new Exception('No tienes permisos en ' . $branch);
            }

            if (
                !isset($request->part)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $partsCarJpa = new PartsCars();
            $partsCarJpa->part = $request->part;
            $partsCarJpa->status = 1;
            $partsCarJpa->save();

            $response->setStatus(200);
            $response->setMessage('La parte del vehículo se a agregado correctamente');
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

            $query = ViewPermissionsByView::select([
                'id',
                'permission',
                'correlative',
                'description',
                'status',
                'view__id',
                'view__view',
                'view__path',
                'view__description',
                'view__status',

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

                if ($column == 'permission' || $column == '*') {
                    $q->where('permission', $type, $value);
                }
                if ($column == 'correlative' || $column == '*') {
                    $q->where('correlative', $type, $value);
                }
                if ($column == 'view__view' || $column == '*') {
                    $q->orWhere('view__view', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }

            });
            $iTotalDisplayRecords = $query->count();
            $permissionsJpa = $query->select('*')
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $permissions = array();
            foreach ($permissionsJpa as $permissionJpa) {
                $permission = gJSON::restore($permissionJpa->toArray(), '__');
                $permissions[] = $permission;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(Permission::count());
            $response->setData($permissions);
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'ln ' . $th->getLine());
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

            $PermissionValidation = Permission::select(['permissions.id', 'permissions.permission'])
                ->where('permission', $request->permission)
                ->where('id', '!=', $request->id)
                ->first();

            if ($PermissionValidation) {
                throw new Exception("Este permiso ya existe");
            }

            $permissionJpa = Permission::find($request->id);
            if (!$permissionJpa) {
                throw new Exception("El permiso que solicitada no existe");
            }
            if (isset($request->permission)) {
                $permissionJpa->permission = $request->permission;
            }
            if (isset($request->correlative)) {
                $permissionJpa->correlative = $request->correlative;
            }
            if (isset($request->_view)) {
                $permissionJpa->_view = $request->_view;
            }
            if (isset($request->description)) {
                $permissionJpa->description = $request->description;
            }

            if (isset($request->status)) {
                $permissionJpa->status = $request->status;
            }

            $permissionJpa->save();

            $response->setStatus(200);
            $response->setMessage('El permiso se a actualizado correctamente');
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

    public function delete(Request $request)
    {
        $response = new Response();
        try {

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: Es necesario el ID para esta operación");
            }

            $permissionJpa = Permission::find($request->id);

            if (!$permissionJpa) {
                throw new Exception("Este reguistro no existe");
            }

            $permissionJpa->status = null;
            $permissionJpa->save();

            $response->setStatus(200);
            $response->setMessage('El permiso se a eliminado correctamente');
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

    public function restore(Request $request)
    {
        $response = new Response();
        try {

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: Es necesario el ID para esta operación");
            }

            $viewJpa = Permission::find($request->id);
            if (!$viewJpa) {
                throw new Exception("Este reguistro no existe");
            }
            $viewJpa->status = "1";
            $viewJpa->save();

            $response->setStatus(200);
            $response->setMessage('El permiso a sido restaurado correctamente');
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
}
