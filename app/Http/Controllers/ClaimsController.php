<?php

namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Claims;
use App\Models\Plans;
use App\Models\Branch;
use App\Models\Response;
use App\Models\ViewClaim;
use App\Models\ViewModels;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Dompdf\Dompdf;
use Dompdf\Options;

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
                if (isset($Claim['plan_id'])) {
                    $PlansJpa = Plans::find($Claim['plan_id']);
                    $Claim['plan'] = $PlansJpa;
                }
                if (isset($Claim['model_id'])) {
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

    public function update(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'claims', 'update')) {
                throw new Exception("No tienes permisos para esta acción");
            }

            $ClaimsJpa = Claims::find($request->id);
            if (isset($request->_client)) {
                $ClaimsJpa->_client = $request->_client;
            }
            if (isset($request->_claim)) {
                $ClaimsJpa->_claim = $request->_claim;
            }
            if (isset($request->_plan)) {
                $ClaimsJpa->_plan = $request->_plan;
            }
            if (isset($request->_model)) {
                $ClaimsJpa->_model = $request->_model;
            }
            if (isset($request->_branch)) {
                $ClaimsJpa->_branch = $request->_branch;
            }
            if (isset($request->date)) {
                $ClaimsJpa->date = $request->date;
            }
            $ClaimsJpa->description = $request->description;
            $ClaimsJpa->update_date = gTrace::getDate('mysql');
            $ClaimsJpa->_update_user = $userid;
            $ClaimsJpa->status = "1";
            $ClaimsJpa->save();

            $response->setStatus(200);
            $response->setMessage('Se ha actualizado correctamente');
            $response->setData($ClaimsJpa->toArray());
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'ln: ' . $th->getLine());
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
                throw new Exception("No tienes permisos para esta acción");
            }

            $ClaimsJpa = Claims::find($request->id);
            $ClaimsJpa->status = null;
            $ClaimsJpa->update_date = gTrace::getDate('mysql');
            $ClaimsJpa->_update_user = $userid;
            $ClaimsJpa->save();

            $response->setStatus(200);
            $response->setMessage('Se ha eliminado correctamente');
            $response->setData($ClaimsJpa->toArray());
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'ln: ' . $th->getLine());
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
                throw new Exception("No tienes permisos para esta acción");
            }

            $ClaimsJpa = Claims::find($request->id);
            $ClaimsJpa->status = "1";
            $ClaimsJpa->update_date = gTrace::getDate('mysql');
            $ClaimsJpa->_update_user = $userid;
            $ClaimsJpa->save();

            $response->setStatus(200);
            $response->setMessage('Se ha restaurado correctamente');
            $response->setData($ClaimsJpa->toArray());

        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'ln: ' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function generateReportByClaim(Request $request){
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'claims', 'read')) {
                throw new Exception('No tienes permisos');
            }
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $pdf = new Dompdf($options);
            $template = file_get_contents('../storage/templates/reportClaim.html');
            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();

            $ViewClaimJpa = ViewClaim::find($request->id);
            $claim = gJSON::restore($ViewClaimJpa->toArray(), '__');
            if(isset($claim['plan_id'])){
                $PlanJpa = Plans::find($claim['plan_id']);
                if($PlanJpa){
                    $claim['plan'] = $PlanJpa;
                }
            }

            if(isset($claim['model_id'])){
                $ModelsJpa = ViewModels::select([
                    'id',
                    'model',
                    'relative_id',
                    'status'
                ])->find($claim['model_id']);
                if($ModelsJpa){
                    $claim['model'] = gJSON::restore($ModelsJpa->toArray(), '__');
                }
            }

            $model = '';
            if(isset($claim['model'])){
                $model = $claim['model']['model'];
            }

            $plan = '';
            if(isset($claim['plan'])){
                $plan = $claim['plan']['plan'];
            }

            $template = str_replace(
                [
                    '{id}',
                    '{branch_onteraction}',
                    '{issue_long_date}',
                    '{client}',
                    '{branch}',
                    '{claim}',
                    '{description}',
                    '{date}',
                    '{plan}',
                    '{model}',
                    '{ejcecutive}',
                ],
                [
                   $claim['id'],
                   $branch_->name,
                   gTrace::getDate('long'),
                   $claim['client']['name'].' '.$claim['client']['lastname'],
                   $claim['branch']['name'],
                   $claim['claim']['claim'],
                   $claim['description'],
                   $claim['date'],
                   $plan,
                   $model,
                   $claim['user_creation']['person']['name'].' '.$claim['user_creation']['person']['lastname'],
                ],
                $template
            );

            $pdf->loadHTML($template);
            $pdf->render();
            return $pdf->stream('Reclamo.pdf');
        } catch (\Throwable $th) {
            $response = new Response();
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . ' ln:' . $th->getLine());
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function generateReports(Request $request){
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'claims', 'read')) {
                throw new Exception('No tienes permisos');
            }

            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $pdf = new Dompdf($options);
            $template = file_get_contents('../storage/templates/reportsClaims.html');

            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();

            $query = ViewClaim::select('*');

            if(isset($request->date_start) && isset($request->date_end)){
                $query
                ->whereBetween('date', [$request->date_start, $request->date_end]);
            }else{

            }

            if(isset($request->branch)){
                $query->where('_branch', $request->branch);
            }else{

            }

            if(isset($request->claim)){
                $query->where('_claim', $request->claim);
            }else{

            }

            $claimsJpa = $query->get();

            $claims = array();
            foreach($claimsJpa as $claimJpa){
                $claim = gJSON::restore($claimJpa->toArray(), '__');
                $claims[] = $claim;
            }
            



            // $template = str_replace(
            //     [
            //         '{id}',
            //         '{branch_onteraction}',
            //         '{issue_long_date}',
            //         '{client}',
            //         '{branch}',
            //         '{claim}',
            //         '{description}',
            //         '{date}',
            //         '{plan}',
            //         '{model}',
            //         '{ejcecutive}',
            //     ],
            //     [
            //        $branch_->name,
            //        gTrace::getDate('long'),
            //     ],
            //     $template
            // );

            // $pdf->loadHTML($template);
            // $pdf->render();
            // return $pdf->stream('Reclamo.pdf');

            $response = new Response();
            $response->setStatus(200);
            $response->setMessage('Operacion correcta');
            $response->setData($claims);
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        } catch (\Throwable $th) {
            $response = new Response();
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . ' ln:' . $th->getLine());
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

}