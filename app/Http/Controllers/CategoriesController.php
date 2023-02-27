<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Category;
use App\Models\Response;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoriesController extends Controller
{
    public function store(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'categories', 'create')) {
                throw new Exception('No tienes permisos para agregar categorias en ' . $branch);
            }

            if (
                !isset($request->category)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $roleValidation = Category::select(['category'])->where('category', $request->category)->first();

            if ($roleValidation) {
                throw new Exception("Escoja otro nombre para esta categoria");
            }

            $categoriesJpa = new Category();
            $categoriesJpa->category = $request->category;

            if (isset($request->description)) {
                $categoriesJpa->description = $request->description;
            }

            $categoriesJpa->creation_date = gTrace::getDate('mysql');
            $categoriesJpa->_creation_user = $userid;
            $categoriesJpa->update_date = gTrace::getDate('mysql');
            $categoriesJpa->_update_user = $userid;
            $categoriesJpa->status = "1";
            $categoriesJpa->save();

            $response->setStatus(200);
            $response->setMessage('La categoria se a agregado correctamente');
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
            if (!gValidate::check($role->permissions, $branch, 'categories', 'read')) {
                throw new Exception('No tienes permisos para listar categorias');
            }

            $peopleJpa = Category::select([
                'id',
                'category',
            ])->whereNotNull('status')
                ->WhereRaw("category LIKE CONCAT('%', ?, '%')", [$request->term])
                ->orWhereRaw("id LIKE CONCAT('%', ?, '%')", [$request->term])
                ->orderBy('category', 'asc')
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


    public function index(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'categories', 'read')) {
                throw new Exception('No tienes permisos para listar las categorias de ' . $branch);
            }

            $categoriesJpa = Category::whereNotNull('status')->get();

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($categoriesJpa->toArray());
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

            if (!gValidate::check($role->permissions, $branch, 'categories', 'read')) {
                throw new Exception('No tienes permisos para listar las categorias  de ' . $branch);
            }

            $query = Category::select(['*'])
                ->orderBy($request->order['column'], $request->order['dir']);

                if(!$request->all){
                    $query->whereNotNull('status');
                }
    
            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;
                if ($column == 'category' || $column == '*') {
                    $q->where('category', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }
            });

            $iTotalDisplayRecords = $query->count();
            $categoriesJpa = $query->select('*')
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(Category::count());
            $response->setData($categoriesJpa->toArray());
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
            if (!gValidate::check($role->permissions, $branch, 'categories', 'update')) {
                throw new Exception('No tienes permisos para actualizar categorias');
            }

            $categoriesJpa = Category::find($request->id);
            if (!$categoriesJpa) {
                throw new Exception("No se puede actualizar este registro");
            }

            if (isset($request->category)) {
                $verifyCatJpa = Category::select(['id', 'category','status'])
                    ->where('category', $request->category)
                    ->where('id', '!=', $request->id)
                    ->whereNotNull('status')
                    ->first();
                if ($verifyCatJpa) {
                    throw new Exception("Elija otro nombre para esta categoria");
                }
                $categoriesJpa->category = $request->category;
            }

            if (isset($request->description)) {
                $categoriesJpa->description = $request->description;
            }

            if (gValidate::check($role->permissions, $branch, 'categories', 'change_status')) {
                if (isset($request->status)) {
                    $categoriesJpa->status = $request->status;
                }
            }

            $categoriesJpa->update_date = gTrace::getDate('mysql');
            $categoriesJpa->_update_user = $userid;

            $categoriesJpa->save();

            $response->setStatus(200);
            $response->setMessage('La categoria ha sido actualizado correctamente');
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
            if (!gValidate::check($role->permissions, $branch, 'categories', 'delete_restore')) {
                throw new Exception('No tienes permisos para eliminar categorias en '.$branch);
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $categoriesJpa = Category::find($request->id);
            if (!$categoriesJpa) {
                throw new Exception('La categoria que deseas eliminar no existe');
            }

            $categoriesJpa->update_date = gTrace::getDate('mysql');
            $categoriesJpa->_update_user = $userid;
            $categoriesJpa->status = null;
            $categoriesJpa->save();

            $response->setStatus(200);
            $response->setMessage('La categoria a sido eliminada correctamente');
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
            if (!gValidate::check($role->permissions, $branch, 'categories', 'delete_restore')) {
                throw new Exception('No tienes permisos para restaurar categorias en '.$branch);
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $categoriesJpa = Category::find($request->id);
            if (!$categoriesJpa) {
                throw new Exception('La categoria que deseas restaurar no existe');
            }

            $categoriesJpa->update_date = gTrace::getDate('mysql');
            $categoriesJpa->_update_user = $userid;
            $categoriesJpa->status = "1";
            $categoriesJpa->save();

            $response->setStatus(200);
            $response->setMessage('La categoria a sido restaurada correctamente');
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
