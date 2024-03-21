<?php

namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Plans;
use App\Models\Response;
use App\Models\ViewPlans;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlansController extends Controller
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

            $PlansJpa = new Plans();
            $PlansJpa->plan = $request->plan;
            $PlansJpa->description = $request->description;

            $PlansJpa->creation_date = gTrace::getDate('mysql');
            $PlansJpa->_creation_user = $userid;
            $PlansJpa->update_date = gTrace::getDate('mysql');
            $PlansJpa->_update_user = $userid;
            $PlansJpa->status = "1";
            $PlansJpa->save();

            $response->setStatus(200);
            $response->setMessage('Se ha creado correctamente');
            $response->setData($PlansJpa->toArray());
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

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'cars', 'read')) {
                throw new Exception('No tienes permisos para listar movilidades  de ' . $branch);
            }

            $verifyCarJpa = Plans::select([
                'id',
                'plan',
            ])->whereNotNull('status')
                ->WhereRaw("plan LIKE CONCAT('%', ?, '%')", [$request->term])
                ->orWhereRaw("id LIKE CONCAT('%', ?, '%')", [$request->term])
                ->orderBy('plan', 'asc')
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

            $query = ViewPlans::select('*')
                ->orderBy($request->order['column'], $request->order['dir']);

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;
                if ($column == 'plan' || $column == '*') {
                    $q->where('plan', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }
            });

            $iTotalDisplayRecords = $query->count();
            $PlansJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

                $Plans = array();
                foreach ($PlansJpa as $PlanJpa) {
                    $Plan = gJSON::restore($PlanJpa->toArray(), '__');
                    $Plans[] = $Plan;
                }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewPlans::count());
            $response->setData($Plans);
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

            $PlansJpa = Plans::find($request->id);
            $PlansJpa->plan = $request->plan;
            $PlansJpa->description = $request->description;
            $PlansJpa->_update_user = $userid;
            $PlansJpa->update_date = gTrace::getDate('mysql');
            $PlansJpa->save();

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

            $PlansJpa = Plans::find($request->id);
            $PlansJpa->status = null;
            $PlansJpa->save();

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

            $PlansJpa = Plans::find($request->id);
            $PlansJpa->status = 1;
            $PlansJpa->save();

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
