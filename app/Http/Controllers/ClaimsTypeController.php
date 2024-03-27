<?php

namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\ClaimsType;
use App\Models\ViewClaimType;
use App\Models\Response;
use App\Models\ViewReviewCar;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClaimsTypeController extends Controller
{
    public function store(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'claims', 'create')) {
                throw new Exception("No tienes permisos para esta acción");
            }

            $ClaimsTypeJpa = new ClaimsType();
            $ClaimsTypeJpa->claim = $request->claim;
            $ClaimsTypeJpa->description = $request->description;

            $ClaimsTypeJpa->creation_date = gTrace::getDate('mysql');
            $ClaimsTypeJpa->_creation_user = $userid;
            $ClaimsTypeJpa->update_date = gTrace::getDate('mysql');
            $ClaimsTypeJpa->_update_user = $userid;
            $ClaimsTypeJpa->status = "1";
            $ClaimsTypeJpa->save();

            $response->setStatus(200);
            $response->setMessage('Se ha creado correctamente');
            $response->setData($ClaimsTypeJpa->toArray());
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

    public function search(Request $request)
    {
        $response = new Response();
        try {

            // [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            // if ($status != 200) {
            //     throw new Exception($message);
            // }
            // if (!gValidate::check($role->permissions, $branch, 'cars', 'read')) {
            //     throw new Exception('No tienes permisos para listar movilidades  de ' . $branch);
            // }

            $verifyCarJpa = ClaimsType::select([
                'id',
                'claim',
            ])->whereNotNull('status')
                ->WhereRaw("claim LIKE CONCAT('%', ?, '%')", [$request->term])
                ->orWhereRaw("id LIKE CONCAT('%', ?, '%')", [$request->term])
                ->orderBy('claim', 'asc')
                ->get();

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($verifyCarJpa->toArray());
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

            if (!gValidate::check($role->permissions, $branch, 'claims', 'read')) {
                throw new Exception('No tienes permisos para esta acción');
            }

            $query = ViewClaimType::select('*')
                ->orderBy($request->order['column'], $request->order['dir']);

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;
                if ($column == 'id' || $column == '*') {
                    $q->orWhere('id', $type, $value);
                }
                if ($column == 'claim' || $column == '*') {
                    $q->orWhere('claim', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }
            });

            $iTotalDisplayRecords = $query->count();
            $ClaimsJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $ClaimsType = array();
            foreach ($ClaimsJpa as $ClaimJpa) {
                $Claim = gJSON::restore($ClaimJpa->toArray(), '__');
                $ClaimsType[] = $Claim;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewClaimType::count());
            $response->setData($ClaimsType);
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

    public function update(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'claims', 'update')) {
                throw new Exception("No tienes permisos para actualizar");
            }

            $ClaimsTypeJpa = ClaimsType::find($request->id);
            $ClaimsTypeJpa->claim = $request->claim;
            $ClaimsTypeJpa->description = $request->description;
            $ClaimsTypeJpa->_update_user = $userid;
            $ClaimsTypeJpa->update_date = gTrace::getDate('mysql');
            $ClaimsTypeJpa->save();

            $response->setStatus(200);
            $response->setMessage('Se ha actualizado correctamente');
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

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'claims', 'delete_restore')) {
                throw new Exception("No tienes permisos para eliminar");
            }

            $ClaimsTypeJpa = ClaimsType::find($request->id);
            $ClaimsTypeJpa->status = null;
            $ClaimsTypeJpa->save();

            $response->setStatus(200);
            $response->setMessage('Se ha eliminado correctamente');
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

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'claims', 'delete_restore')) {
                throw new Exception("No tienes permisos para restaurar");
            }

            $ClaimsTypeJpa = ClaimsType::find($request->id);
            $ClaimsTypeJpa->status = 1;
            $ClaimsTypeJpa->save();

            $response->setStatus(200);
            $response->setMessage('Se ha restaurado correctamente');
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
