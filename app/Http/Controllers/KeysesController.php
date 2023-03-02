<?php

namespace App\Http\Controllers;

use App\gLibraries\gTrace;
use App\gLibraries\guid;
use App\gLibraries\gJSON;
use App\gLibraries\gValidate;
use App\Models\Keyses;
use App\Models\ViewKeys;
use App\Models\Response;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KeysesController extends Controller
{
    public function store(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'keyses', 'create')) {
                throw new Exception("No tienes permisos para agregar llaves al sistema");
            }

            if (
                !isset($request->name) ||
                !isset($request->responsible) ||
                !isset($request->date_entry) ||
                !isset($request->price) ||
                !isset($request->duplicate) 
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            if (!isset($request->image_mini) ||
                !isset($request->image_full) ||
                !isset($request->image_type)
            ) {
                throw new Exception("Error: para agregar una llave es nesesario una imagen de esta.");
            }

            $keyValidateExistence = Keyses::select(['name'])
                ->where('name', $request->name)
                ->first();

            if ($keyValidateExistence) {
                throw new Exception("Escoja otro nombre para esta llave");
            }

            $keysesJpa = new Keyses();
            $keysesJpa->name = $request->name;
            $keysesJpa->responsible = $request->responsible;
            $keysesJpa->date_entry = $request->date_entry;
            $keysesJpa->price = $request->price;
            $keysesJpa->duplicate = $request->duplicate;
            $keysesJpa->address = $request->address;
            $keysesJpa->relative_id = guid::short();
            $keysesJpa->status_key = "DISPONIBLE";
          
            if (
                isset($request->image_type) &&
                isset($request->image_mini) &&
                isset($request->image_full)
            ) {

                $keysesJpa->image_type = $request->image_type;
                $keysesJpa->image_mini = base64_decode($request->image_mini);
                $keysesJpa->image_full = base64_decode($request->image_full);

            } else {
                $keysesJpa->image_type = null;
                $keysesJpa->image_mini = null;
                $keysesJpa->image_full = null;
            }

            if (isset($request->description)) {
                $keysesJpa->description = $request->description;
            }

            $keysesJpa->creation_date = gTrace::getDate('mysql');
            $keysesJpa->_creation_user = $userid;
            $keysesJpa->update_date = gTrace::getDate('mysql');
            $keysesJpa->_update_user = $userid;
            $keysesJpa->status = "1";
            $keysesJpa->save();

            $response->setStatus(200);
            $response->setMessage("La llave se a agregado correctamente");
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
            if (!gValidate::check($role->permissions, $branch, 'brands', 'read')) {
                throw new Exception('No tienes permisos para listar marcas');
            }

            $peopleJpa = Brand::select([
                'id',
                'correlative',
                'brand',
                'relative_id',
            ])->whereNotNull('status')
                ->WhereRaw("brand LIKE CONCAT('%', ?, '%')", [$request->term])
                ->orWhereRaw("id LIKE CONCAT('%', ?, '%')", [$request->term])
                ->orderBy('brand', 'asc')
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

    public function paginate(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'keys', 'read')) {
                throw new Exception('No tienes permisos para listar llaves');
            }

            $query = ViewKeys::select(['*'])
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
                if ($column == 'responsible__name' || $column == '*') {
                    $q->where('responsible__name', $type, $value);
                }
                if ($column == 'date_entry' || $column == '*') {
                    $q->where('date_entry', $type, $value);
                }
                if ($column == 'duplicate' || $column == '*') {
                    $q->where('duplicate', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }
            });

            
            $iTotalDisplayRecords = $query->count();
            $keysJpa = $query
            ->skip($request->start)
            ->take($request->length)
            ->get();
            
            $keys = [];
            foreach($keysJpa as $keyJpa){
                $key = gJSON::restore($keyJpa->toArray(),'__');
                $keys[] =$key;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewKeys::count());
            $response->setData($keys);
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

            $userJpa = Brand::select([
                "brands.image_$size as image_content",
                'brands.image_type',

            ])
                ->where('relative_id', $relative_id)
                ->first();

            if (!$userJpa) {
                throw new Exception('No se encontraron datos');
            }
            if (!$userJpa->image_content) {
                throw new Exception('No existe imagen');
            }
            $content = $userJpa->image_content;
            $type = $userJpa->image_type;
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
            if (!gValidate::check($role->permissions, $branch, 'brands', 'update')) {
                throw new Exception('No tienes permisos para actualizar marcas');
            }

            $brandJpa = Brand::select(['id'])->find($request->id);
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

            if (
                isset($request->image_type) &&
                isset($request->image_mini) &&
                isset($request->image_full)
            ) {
                if (
                    $request->image_type &&
                    $request->image_mini &&
                    $request->image_full
                ) {
                    $brandJpa->image_type = $request->image_type;
                    $brandJpa->image_mini = base64_decode($request->image_mini);
                    $brandJpa->image_full = base64_decode($request->image_full);
                } else {
                    $brandJpa->image_type = null;
                    $brandJpa->image_mini = null;
                    $brandJpa->image_full = null;
                }
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
