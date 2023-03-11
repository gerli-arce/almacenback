<?php

namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\guid;
use App\gLibraries\gValidate;
use App\Models\Keyses;
use App\Models\OperationKeys;
use App\Models\People;
use App\Models\Response;
use App\Models\ViewKeys;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KeysesController extends Controller
{

    public function lendKey(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'keys', 'update')) {
                throw new Exception("No tienes permisos para actualizar llaves en el sistema");
            }

            if (
                !isset($request->lend_person) ||
                !isset($request->lend_date) ||
                !isset($request->lend_hour) ||
                !isset($request->lend_key)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $operationKey = new OperationKeys();
            $operationKey->type_operation = "LEND";
            $operationKey->date_operation = $request->lend_date . ' ' . $request->lend_hour;
            $operationKey->date_execute_operation = gTrace::getDate('mysql');
            $operationKey->_user_operation = $userid;
            $operationKey->_key = $request->lend_key['id'];
            $operationKey->_person_operation = $request->lend_person;
            if (isset($request->lend_reazon)) {
                $operationKey->reazon = $request->lend_reazon;
            }
            $operationKey->save();

            $personJpa = People::find($request->lend_person);
            $name_person_lend = $personJpa->name . ' ' . $personJpa->lastname;

            $keyJpa = Keyses::find($request->lend_key['id']);
            $keyJpa->status_key = "EN USO";
            $keyJpa->description = "Se presto a " . $name_person_lend . " la fecha: " .
            $request->lend_date . ' ' . $request->lend_hour . " por la razon de: " .
            $request->lend_reazon;

            $keyJpa->save();

            $response->setStatus(200);
            $response->setMessage("El prestamo de la llave se realizo correctamente");
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

    public function returnKey(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'keys', 'update')) {
                throw new Exception("No tienes permisos para actualizar llaves en el sistema");
            }

            if (
                !isset($request->return_person) ||
                !isset($request->return_date) ||
                !isset($request->return_hour) ||
                !isset($request->return_key)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $operationKey = new OperationKeys();
            $operationKey->type_operation = "RETURN";
            $operationKey->date_operation = $request->return_date . ' ' . $request->return_hour;
            $operationKey->date_execute_operation = gTrace::getDate('mysql');
            $operationKey->_user_operation = $userid;
            $operationKey->_key = $request->return_key['id'];
            $operationKey->_person_operation = $request->return_person;
            if (isset($request->return_reazon)) {
                $operationKey->reazon = $request->return_reazon;
            }
            $operationKey->save();

            $personJpa = People::find($request->return_person);
            $name_person_return = $personJpa->name . ' ' . $personJpa->lastname;

            $keyJpa = Keyses::find($request->return_key['id']);
            $keyJpa->status_key = "DISPONIBLE";
            $keyJpa->description =  $name_person_return . " devolvio la llave, en la fecha: " .
            $request->return_date . ' ' . $request->return_hour . " por la razon de: " .
            $request->return_reazon;

            $keyJpa->save();

            $response->setStatus(200);
            $response->setMessage("La devolución de la llave se realizo correctamente");
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

    public function searchLendByKey(Request $request, $idkey)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'keys', 'read')) {
                throw new Exception("No tienes permisos para leer llaves en el sistema");
            }

            if (
                !isset($idkey)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $operationKey = OperationKeys::select([
                'operation_keys.id as id',
                'operation_keys.type_operation as type_operation',
                'operation_keys.date_operation as date_operation',
                'operation_keys.date_execute_operation as date_execute_operation',
                'operation_keys.reazon as reazon',
                'people.id as person__id',
                'people.name as person__name',
                'people.lastname as person__lastname'
            ])->where('_key', $idkey)
                            ->join('people','operation_keys._person_operation', 'people.id')
                              ->where('type_operation', 'LEND')
                              ->orderByDesc('id')
                              ->first();

            $keyOperationLen = gJSON::restore($operationKey->toArray(), '__');

            $response->setStatus(200);
            $response->setMessage("Operación correcta");
            $response->setData($keyOperationLen);
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

    public function store(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'keys', 'create')) {
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
            if($request->address){
                $keysesJpa->address = $request->address;
            }
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

            $query = ViewKeys::orderBy($request->order['column'], $request->order['dir']);

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
                    $q->orwhere('responsible__name', $type, $value);
                }
                if ($column == 'responsible__lastname' || $column == '*') {
                    $q->orwhere('responsible__lastname', $type, $value);
                }
                if ($column == 'date_entry' || $column == '*') {
                    $q->orwhere('date_entry', $type, $value);
                }
                if ($column == 'duplicate' || $column == '*') {
                    $q->orwhere('duplicate', $type, $value);
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
            foreach ($keysJpa as $keyJpa) {
                $key = gJSON::restore($keyJpa->toArray(), '__');
                $keys[] = $key;
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

            $userJpa = Keyses::select([
                "keyses.image_$size as image_content",
                'keyses.image_type',

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
            $ruta = '../storage/images/llaves-default.jpg';
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

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'keys', 'update')) {
                throw new Exception('No tienes permisos para actualizar las llaves');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }
            $keysJpa = Keyses::select(['id'])->find($request->id);
            if (!$keysJpa) {
                throw new Exception("No se puede actualizar este registro");
            }

            if (isset($request->name)) {
                $verifyCatJpa = Keyses::select(['id', 'name'])
                    ->where('name', $request->name)
                    ->where('id', '!=', $request->id)
                    ->first();
                if ($verifyCatJpa) {
                    throw new Exception("Elija otro nombre para esta llave, el nombre ya existe.");
                }
                $keysJpa->name = $request->name;
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
                    $keysJpa->image_type = $request->image_type;
                    $keysJpa->image_mini = base64_decode($request->image_mini);
                    $keysJpa->image_full = base64_decode($request->image_full);
                } else {
                    $keysJpa->image_type = null;
                    $keysJpa->image_mini = null;
                    $keysJpa->image_full = null;
                }
            }
            if (isset($request->price)) {
                $keysJpa->price = $request->price;
            }

            if (isset($request->address)) {
                $keysJpa->address = $request->address;
            }

            if (isset($request->responsible)) {
                $keysJpa->responsible = $request->responsible;
            }

            if (isset($request->duplicate)) {
                $keysJpa->duplicate = $request->duplicate;
            }

            if (isset($request->date_entry)) {
                $keysJpa->date_entry = $request->date_entry;
            }

            if (isset($request->description)) {
                $keysJpa->description = $request->description;
            }

            if (isset($request->status_key)) {
                $keysJpa->status_key = $request->status_key;
            }

            if (gValidate::check($role->permissions, $branch, 'keys', 'change_status')) {
                if (isset($request->status)) {
                    $keysJpa->status = $request->status;
                }
            }

            $keysJpa->update_date = gTrace::getDate('mysql');
            $keysJpa->_update_user = $userid;

            $keysJpa->save();

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
            if (!gValidate::check($role->permissions, $branch, 'keys', 'delete_restore')) {
                throw new Exception('No tienes permisos para eliminar llaves');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $keyJpa = Keyses::find($request->id);
            if (!$keyJpa) {
                throw new Exception('La llave que deseas eliminar no existe');
            }

            $keyJpa->update_date = gTrace::getDate('mysql');
            $keyJpa->_update_user = $userid;
            $keyJpa->status = null;
            $keyJpa->save();

            $response->setStatus(200);
            $response->setMessage('La llave a sido eliminada correctamente');
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
            if (!gValidate::check($role->permissions, $branch, 'keys', 'delete_restore')) {
                throw new Exception('No tienes permisos para restaurar llaves');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $keyJpa = Keyses::find($request->id);
            if (!$keyJpa) {
                throw new Exception('La llave que deseas restaurar no existe');
            }

            $keyJpa->update_date = gTrace::getDate('mysql');
            $keyJpa->_update_user = $userid;
            $keyJpa->status = "1";
            $keyJpa->save();

            $response->setStatus(200);
            $response->setMessage('La llave a sido restaurada correctamente');
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

    public function RecordKey(Request $request, $idkey){
        $response = new Response();
        try {

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'keys', 'read')) {
                throw new Exception('No tienes permisos para listar llaves');
            }

            $operatonsKeyJpa = OperationKeys::where('_key',$idkey)->orderBy('id', 'asc')->get();

          
            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($operatonsKeyJpa->toArray());
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
