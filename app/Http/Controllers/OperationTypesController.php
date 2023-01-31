<?php

namespace App\Http\Controllers;

use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\gLibraries\gUid;
use App\Models\OperationType;
use App\Models\Response;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class OperationTypesController extends Controller
{
    public function store(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'operation_types', 'create')) {
                throw new Exception("No tienes permisos para agregar tipo de operaciónes en ");
            }

            if (
                !isset($request->operation)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $operationValidation = OperationType::select(['operation'])
                ->where('operation', $request->operation)
                ->first();

            if ($operationValidation) {
                throw new Exception("Error: Elija otro nombre para la operación");
            }

            $operationTypeJpa = new OperationType();
            $operationTypeJpa->operation = $request->operation;

            if (isset($request->description)) {
                $operationTypeJpa->description = $request->description;
            }

            $operationTypeJpa->status = "1";
            $operationTypeJpa->save();

            $response->setStatus(200);
            $response->setMessage('El tipo de operación se a agregado correctamente');
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

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'operation_types', 'read')) {
                throw new Exception('No tienes permisos para listar los tipos de operaciónes');
            }

            $query = OperationType::select(['*'])
                ->orderBy($request->order['column'], $request->order['dir']);

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;
                if ($column == 'operation' || $column == '*') {
                    $q->where('operation', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }
            });

            $iTotalDisplayRecords = $query->count();
            $operationTypesJpa = $query->select('*')
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(OperationType::count());
            $response->setData($operationTypesJpa->toArray());
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
            if (!gValidate::check($role->permissions, $branch, 'operation_types', 'update')) {
                throw new Exception('No tienes permisos para actualizar los tipos de operaciónes');
            }

            $operationTypesJpa = OperationType::select(['id'])-> find($request->id);
            if (!$operationTypesJpa) {
                throw new Exception("No se puede actualizar este registro");
            }

            if (isset($request->operation)) {
                $verifOperation = OperationType::select(['id', 'operation'])
                    ->where('operation', $request->operation)
                    ->where('id', '!=', $request->id)
                    ->first();
                if ($verifOperation) {
                    throw new Exception("Elija otro nombre para esta operación");
                }
                $operationTypesJpa->operation = $request->operation;
            }

            if (isset($request->description)) {
                $operationTypesJpa->description = $request->description;
            }

            if (gValidate::check($role->permissions, $branch, 'operation_types', 'change_status')) {
                if (isset($request->status)) {
                    $operationTypesJpa->status = $request->status;
                }
            }

            $operationTypesJpa->save();

            $response->setStatus(200);
            $response->setMessage('El tipo de operación ha sido actualizado correctamente');
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
            if (!gValidate::check($role->permissions, $branch, 'operation_types', 'delete_restore')) {
                throw new Exception('No tienes permisos para eliminar tipos de operaciónes');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $operationTypeJpa = OperationType::find($request->id);
            if (!$operationTypeJpa) {
                throw new Exception('La categoria que deseas eliminar no existe');
            }
            
            $operationTypeJpa->status = null;
            $operationTypeJpa->save();

            $response->setStatus(200);
            $response->setMessage('La operación a sido eliminada correctamente');
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
            if (!gValidate::check($role->permissions, $branch, 'operation_types', 'delete_restore')) {
                throw new Exception('No tienes permisos para restaurar operaciónes');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $operationTypeJpa = OperationType::find($request->id);
            if (!$operationTypeJpa) {
                throw new Exception('La operación que deseas restaurar no existe');
            }

            $operationTypeJpa->update_date = gTrace::getDate('mysql');
            $operationTypeJpa->_update_user = $userid;
            $operationTypeJpa->status = "1";
            $operationTypeJpa->save();

            $response->setStatus(200);
            $response->setMessage('La operación a sido restaurada correctamente');
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
