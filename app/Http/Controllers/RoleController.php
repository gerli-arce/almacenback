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

class RoleController extends Controller
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

            if ($request->priority < $role->priority) {
                throw new Exception('Los roles nuevos no pueden tener mayor prioridad al tuyo, intenta poner un número mayor a ' . $role->priority);
            }

            $roleValidation = Role::select(['roles.role'])->where('role', $request->role)->first();

            if ($roleValidation) {
                throw new Exception("Escoja otro nombre para este rol");
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
            $response->setData($roleJpa->toArray());
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
            if (!gValidate::check($role->permissions, $branch, 'roles', 'read')) {
                throw new Exception('No tienes permisos para listar los roles del sistema');
            }

            $rolesJpa = Role::where('roles.priority', '>=', $role->priority)->whereNotNull('status')->get();

            $roles = array();
            foreach ($rolesJpa as $roleJpa) {
                $role = gJSON::restore($roleJpa->toArray());
                $role['permissions'] = gJSON::parse($role['permissions']);
                $roles[] = $role;
            }
            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($roles);
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
            if (!gValidate::check($role->permissions,$branch , 'roles', 'read')) {
                throw new Exception('No tienes permisos para listar los roles del sistema');
            }

            $query = Role::where('roles.priority', '>=', $role->priority)
            ->orderBy($request->order['column'], $request->order['dir']);

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;
                if ($column == 'role' || $column == '*') {
                    $q->where('role', $type, $value);
                }
                if ($column == 'priority' || $column == '*') {
                    $q->orWhere('priority', $type, $value);
                }
                if ($column == 'permissions' || $column == '*') {
                    $q->orWhere('permissions', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }
            });

            $iTotalDisplayRecords = $query->count();
            $rolesJpa = $query->select('*')
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $roles = gJSON::restore($rolesJpa->toArray());

            foreach ($roles as $key => $value) {
                $roles[$key]['permissions'] = gJSON::parse($value['permissions']);
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(Role::count());
            $response->setData($roles);
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

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'roles', 'update')) {
                throw new Exception('No tienes permisos para actualizar los roles del sistema');
            }

            $roleJpa = Role::find($request->id);
            if (!$roleJpa) {
                throw new Exception("No se puede actualizar este registro");
            }

            if ($request->role) {
                $roleValidation = Role::select(['roles.id', 'roles.role'])
                    ->where('role', $request->role)
                    ->where('id', '!=', $request->id)
                    ->first();
                if ($roleValidation) {
                    throw new Exception("Escoja otro nombre para el rol");
                }
                $roleJpa->role = $request->role;
            }

            if ($role->id != $request->id) {
                if ($request->priority) {
                    if ($request->priority < $role->priority) {
                        throw new Exception('No puede asignar un rol superior al suyo, intenta poner un número mayor a ' . $role->priority);
                    }
                    $roleJpa->priority = $request->priority;
                }
            }

            if ($request->priority < $role->priority) {
                throw new Exception('Los roles que actualices no pueden tener mayor prioridad al tuyo, intenta poner un número mayor a ' . $role->priority);
            }

            if($request->description){
                $roleJpa->description = $request->description;
            }

            if($request->permissions){
                $roleJpa->permissions = $request->permissions;
            }


            if (gValidate::check($role->permissions, $branch, 'views', 'change_status')) {
                if (isset($request->status)) {
                    $roleJpa->status = $request->status;
                }
            }

            $roleJpa->save();

            $response->setStatus(200);
            $response->setMessage('El rol ha sido actualizado correctamente');
            $response->setData($roleJpa->toArray());
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

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'roles', 'delete_restore')) {
                throw new Exception('No tienes permisos para eliminar roles en el sistema');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $role = Role::find($request->id);
            if ($role == null) {
                throw new Exception('El rol que deseas eliminar no existe');
            }
            $role->status = null;
            $role->save();

            $response->setStatus(200);
            $response->setMessage('El rol a sido eliminado correctamente');
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

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'roles', 'delete_restore')) {
                throw new Exception('No tienes permisos para restaurar roles en el sistema');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $role = Role::find($request->id);
            if ($role == null) {
                throw new Exception('El rol que deseas restaurar no existe');
            }
            $role->status = "1";
            $role->save();

            $response->setStatus(200);
            $response->setMessage('El rol a sido restaurado correctamente');
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
    public function permissions(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'roles', 'update')) {
                throw new Exception('No tienes permisos para modificar roles en el sistema');
            }

            if (
                !isset($request->id) ||
                !isset($request->permissions)
            ) {
                throw new Exception("Error: Envíe todos los datos necesarios");
            }

            if ($role->id == $request->id) {
                throw new Exception('No se pueden realizar modificaciones sobre tu mismo rol');
            }

            if (!gJSON::parseable($request->permissions)) {
                throw new Exception('Los permisos deben seguir el formato de una lista clave-valor');
            }

            $roleJpa = Role::find($request->id);
            if ($roleJpa == null) {
                throw new Exception('El rol que deseas restaurar no existe');
            }
            [$ok, $message, $permissions] = gvalidate::cleanPermissions(
                $role->permissions,
                gJSON::parse($roleJpa->permissions),
                gJSON::parse($request->permissions)
            );
            if (!$ok) {
                throw new Exception($message);
            }
            $roleJpa->permissions = gJSON::stringify($permissions);
            $roleJpa->save();

            $response->setStatus(200);
            $response->setMessage('Permisos asignados correctamente');
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
    public function saveBranches(Request $request){
        $response = new Response();
        try {

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'roles', 'update')) {
                throw new Exception('No tienes permisos para actualizar los roles del sistema');
            }

            $response->setStatus(200);
            $response->setMessage('');
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
