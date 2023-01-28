<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Brand;
use App\Models\Response;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BrandController extends Controller
{
    public function store(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'brands', 'create')) {
                throw new Exception('No tienes permisos para agregar marcas en ' . $branch);
            }

            if (
                !isset($request->correlative) ||
                !isset($request->brand)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $brandValidation = Brand::select(['brand','correlative'])
            ->where('brand', $request->brand)
            ->orWhere('correlative', $request->correlative)
            ->first();

            if ($brandValidation) {
                if($brandValidation->brand == $request->brand){
                    throw new Exception("Escoja otro nombre para la marca");
                }
                if($brandValidation->correlative == $request->correlative){
                    throw new Exception("Escoja otro correlativo para la marca");
                }
            }

            $brandJpa = new Brand();
            $brandJpa->brand = $request->brand;
            $brandJpa->correlative = $request->correlative;

            if (isset($request->description)) {
                $brandJpa->description = $request->description;
            }

            $brandJpa->creation_date = gTrace::getDate('mysql');
            $brandJpa->_creation_user = $userid;
            $brandJpa->update_date = gTrace::getDate('mysql');
            $brandJpa->_update_user = $userid;
            $brandJpa->status = "1";
            $brandJpa->save();

            $response->setStatus(200);
            $response->setMessage('La marca se a agregado correctamente');
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

            if (!gValidate::check($role->permissions, $branch, 'brands', 'read')) {
                throw new Exception('No tienes permisos para listar las marcas de ' . $branch);
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

            if (!gValidate::check($role->permissions, $branch, 'brands', 'read')) {
                throw new Exception('No tienes permisos para listar las marcas  de ' . $branch);
            }

            $query = Brand::select(['*'])
                ->orderBy($request->order['column'], $request->order['dir']);

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;
                if ($column == 'correlative' || $column == '*') {
                    $q->where('correlative', $type, $value);
                }
                if ($column == 'brand' || $column == '*') {
                    $q->where('brand', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }
            });

            $iTotalDisplayRecords = $query->count();
            $brandsJpa = $query->select('*')
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(Brand::count());
            $response->setData($brandsJpa->toArray());
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
            if (!gValidate::check($role->permissions, $branch, 'brands', 'update')) {
                throw new Exception('No tienes permisos para actualizar marcas');
            }

            $brandJpa = Brand::find($request->id);
            if (!$brandJpa) {
                throw new Exception("No se puede actualizar este registro");
            }

            if (isset($request->brand)) {
                $verifyCatJpa = Brand::select(['id', 'brand'])
                    ->where('brand', $request->brand)
                    ->where('id', '!=', $request->id)
                    ->first();
                if ($verifyCatJpa) {
                    throw new Exception("Elija otro nombre para esta marca");
                }
                $brandJpa->brand = $request->brand;
            }

            if (isset($request->correlative)) {
                $verifyCatJpa = Brand::select(['id', 'correlative'])
                    ->where('correlative', $request->correlative)
                    ->where('id', '!=', $request->id)
                    ->first();
                if ($verifyCatJpa) {
                    throw new Exception("Elija otro correlativo para esta marca");
                }
                $brandJpa->correlative = $request->correlative;
            }

            if (isset($request->description)) {
                $brandJpa->description = $request->description;
            }

            if (gValidate::check($role->permissions, $branch, 'brands', 'change_status')) {
                if (isset($request->status)) {
                    $brandJpa->status = $request->status;
                }
            }

            $brandJpa->update_date = gTrace::getDate('mysql');
            $brandJpa->_update_user = $userid;

            $brandJpa->save();

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
            if (!gValidate::check($role->permissions, $branch, 'brands', 'delete_restore')) {
                throw new Exception('No tienes permisos para eliminar marcas en ' . $branch);
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $brandJpa = Brand::find($request->id);
            if (!$brandJpa) {
                throw new Exception('La categoria que deseas eliminar no existe');
            }

            $brandJpa->update_date = gTrace::getDate('mysql');
            $brandJpa->_update_user = $userid;
            $brandJpa->status = null;
            $brandJpa->save();

            $response->setStatus(200);
            $response->setMessage('La marca a sido eliminada correctamente');
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
            if (!gValidate::check($role->permissions, $branch, 'brands', 'delete_restore')) {
                throw new Exception('No tienes permisos para restaurar marcas en ' . $branch);
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $categoriesJpa = Brand::find($request->id);
            if (!$categoriesJpa) {
                throw new Exception('La marca que deseas restaurar no existe');
            }

            $categoriesJpa->update_date = gTrace::getDate('mysql');
            $categoriesJpa->_update_user = $userid;
            $categoriesJpa->status = "1";
            $categoriesJpa->save();

            $response->setStatus(200);
            $response->setMessage('La marca a sido restaurada correctamente');
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
