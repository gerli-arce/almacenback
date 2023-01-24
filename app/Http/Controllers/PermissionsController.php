<?php

namespace App\Http\Controllers;

use App\gLibraries\gFetch;
use App\gLibraries\gJson;
use App\gLibraries\gStatus;
use App\gLibraries\gValidate;
use App\Models\ViewPermissionsByView;
use App\Models\Permission;
use App\Models\Response;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
class PermissionsController extends Controller
{
    public function index(Request $request){
        $response = new Response();
        try {

            $permissionsJpa = ViewPermissionsByView::select([
                'id',
                'permission',
                'correlative',
                'description',
                'status',
                'view__id',
                'view__view',
                'view__correlative',
                'view__path',
                'view__description',
                'view__status'
            ])->get();
           

            $permissions = array();
            foreach ($permissionsJpa as $permissionJpa) {
                $permission = gJSON::restore($permissionJpa->toArray(), '__');
                $permissions[] = $permission;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($permissions);
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage().'ln '.$th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }
    public function store(Request $request){
        $response = new Response();
        try {
    
          if (
            !isset($request->permission) ||
            !isset($request->correlative) ||
            !isset($request->_view)
          ) {
            throw new Exception("Error: No deje campos vacíos");
          }

          $permissionValidation = Permission::select(['permission'])
          ->where('permission', $request->permission)
          ->where('_view', $request->_view)
          ->first();
    
          if ($permissionValidation) {
              throw new Exception("El permiso ya existe.");
          }
    
          $permissionJpa = new Permission();
          $permissionJpa->permission = $request->permission;
          $permissionJpa->correlative = $request->correlative;
          $permissionJpa->_view = $request->_view;

          if($request->description){
            $permissionJpa->description = $request->description;
          }
     
          $permissionJpa->status ="1";

          $permissionJpa->save();
    
          $response->setStatus(200);
          $response->setMessage('Vista agregada correctamente');
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
                'view__status'

            ])
            ->orderBy($request->order['column'], $request->order['dir']);

            // if (!$request->all || !gValidate::check($role->permissions, 'views', 'see_trash')) {
            // }

            if(!$request->all){
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
            $response->setMessage($th->getMessage().'ln '.$th->getLine());
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
            if(isset($request->permission)){
                $permissionJpa->permission = $request->permission;
            }
            if(isset($request->correlative)){
                $permissionJpa->correlative = $request->correlative;
            }
            if(isset($request->_view)){
                $permissionJpa->_view = $request->_view;
            }
            if(isset($request->description)){
                $permissionJpa->description = $request->description;
            }
            
            if(isset($request->status)){
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
