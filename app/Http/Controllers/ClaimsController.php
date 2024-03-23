<?php

namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Plans;
use App\Models\Claims;
use App\Models\ViewModels;
use App\Models\Response;
use App\Models\ViewClaim;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClaimsController extends Controller
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

            $ClaimsJpa = new Claims();
            $ClaimsJpa->_client = $request->_client;
            $ClaimsJpa->_claim = $request->_claim;
            $ClaimsJpa->_plan = $request->_plan;
            $ClaimsJpa->_model = $request->_model;
            $ClaimsJpa->_branch = $request->_branch;
            $ClaimsJpa->date = $request->date;
            $ClaimsJpa->description = $request->description;

            $ClaimsJpa->creation_date = gTrace::getDate('mysql');
            $ClaimsJpa->_creation_user = $userid;
            $ClaimsJpa->update_date = gTrace::getDate('mysql');
            $ClaimsJpa->_update_user = $userid;
            $ClaimsJpa->status = "1";
            $ClaimsJpa->save();

            $response->setStatus(200);
            $response->setMessage('Se ha creado correctamente');
            $response->setData($ClaimsJpa->toArray());
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

            $query = ViewClaim::select('*')
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
                    $q->orWhere('claim__claim', $type, $value);
                }
                if ($column == 'branch' || $column == '*') {
                    $q->orWhere('branch__name', $type, $value);
                }
                if ($column == 'date' || $column == '*') {
                    $q->orWhere('date', $type, $value);
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
                if(isset($Claim['plan_id'])){
                    $PlansJpa = Plans::find($Claim['plan_id']);
                    $Claim['plan'] = $PlansJpa;
                }
                if(isset($Claim['model_id'])){
                    $ModelsJpa = ViewModels::find($Claim['model_id']);
                    $Claim['model'] = $ModelsJpa;
                }
                $ClaimsType[] = $Claim;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewClaim::count());
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

}
