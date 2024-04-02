<?php

namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\Claims;
use App\Models\Plans;
use App\Models\Response;
use App\Models\ViewClaim;
use App\Models\ViewModels;
use App\Models\ViewUsers;
use Dompdf\Dompdf;
use Dompdf\Options;
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

            if (
                isset($request->image_type) &&
                isset($request->image_mini) &&
                isset($request->image_full)
            ) {
                if (
                    $request->image_type != "none" &&
                    $request->image_mini != "none" &&
                    $request->image_full != "none"
                ) {
                    $ClaimsJpa->image_type = $request->image_type;
                    $ClaimsJpa->image_mini = base64_decode($request->image_mini);
                    $ClaimsJpa->image_full = base64_decode($request->image_full);
                } else {
                    throw new Exception("Una imagen debe ser cargada.");
                }
            } else {
                throw new Exception("Una imagen debe ser cargada.");
            }

            $ClaimsJpa->creation_date = gTrace::getDate('mysql');
            $ClaimsJpa->_creation_user = $userid;
            $ClaimsJpa->update_date = gTrace::getDate('mysql');
            $ClaimsJpa->_update_user = $userid;
            $ClaimsJpa->status = "1";
            $ClaimsJpa->save();

            $responseData = [
                '_client' => $ClaimsJpa->_client,
                '_claim' => $ClaimsJpa->_claim,
                '_plan' => $ClaimsJpa->_plan,
                '_model' => $ClaimsJpa->_model,
                '_branch' => $ClaimsJpa->_branch,
                'date' => $ClaimsJpa->date,
                'description' => $ClaimsJpa->description,
                'update_date' => $ClaimsJpa->update_date,
                '_update_user' => $ClaimsJpa->_update_user,
                'status' => $ClaimsJpa->status,
             ];

            $response->setStatus(200);
            $response->setMessage('Se ha creado correctamente');
            $response->setData($responseData);
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
                if ($column == 'client' || $column == '*') {
                    $q->orWhere('client__name', $type, $value);
                }
                if ($column == 'client' || $column == '*') {
                    $q->orWhere('client__lastname', $type, $value);
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

    public function image($id, $size)
    {
        $response = new Response();
        $content = null;
        $type = null;
        try {
            if ($size != 'full') {
                $size = 'mini';
            }
            if (
                !isset($id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $ClaimsJpa = Claims::select([
                "claims.image_$size as image_content",
                'claims.image_type',

            ])
                ->where('id', $id)
                ->first();

            if (!$ClaimsJpa) {
                throw new Exception('No se encontraron datos');
            }

            if (!$ClaimsJpa->image_content) {
                throw new Exception('No existe imagen');
            }

            $content = $ClaimsJpa->image_content;
            $type = $ClaimsJpa->image_type;
            $response->setStatus(200);
        } catch (\Throwable $th) {
            $ruta = '../storage/images/img-default.jpg';
            $fp = fopen($ruta, 'r');
            $datos_image = fread($fp, filesize($ruta));
            $datos_image = addslashes($datos_image);
            fclose($fp);
            $content = stripslashes($datos_image);
            $type = 'image/jpeg';
            $response->setStatus(200);
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

            if (
                isset($request->image_type) &&
                isset($request->image_mini) &&
                isset($request->image_full)
            ) {
                if (
                    $request->image_type != "none" &&
                    $request->image_mini != "none" &&
                    $request->image_full != "none"
                ) {
                    $ClaimsJpa->image_type = $request->image_type;
                    $ClaimsJpa->image_mini = base64_decode($request->image_mini);
                    $ClaimsJpa->image_full = base64_decode($request->image_full);
                } else {
                    throw new Exception("Una imagen debe ser cargada.");
                }
            } else {
                throw new Exception("Una imagen debe ser cargada.");
            }

            $ClaimsJpa->description = $request->description;
            $ClaimsJpa->update_date = gTrace::getDate('mysql');
            $ClaimsJpa->_update_user = $userid;
            $ClaimsJpa->status = "1";
            $ClaimsJpa->save();

            $responseData = [
                '_client' => $ClaimsJpa->_client,
                '_claim' => $ClaimsJpa->_claim,
                '_plan' => $ClaimsJpa->_plan,
                '_model' => $ClaimsJpa->_model,
                '_branch' => $ClaimsJpa->_branch,
                'date' => $ClaimsJpa->date,
                'description' => $ClaimsJpa->description,
                'update_date' => $ClaimsJpa->update_date,
                '_update_user' => $ClaimsJpa->_update_user,
                'status' => $ClaimsJpa->status,
             ];

            $response->setStatus(200);
            $response->setMessage('Se ha actualizado correctamente');
            $response->setData($responseData);
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

    public function generateReportByClaim(Request $request)
    {
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
            if (isset($claim['plan_id'])) {
                $PlanJpa = Plans::find($claim['plan_id']);
                if ($PlanJpa) {
                    $claim['plan'] = $PlanJpa;
                }
            }

            if (isset($claim['model_id'])) {
                $ModelsJpa = ViewModels::select([
                    'id',
                    'model',
                    'relative_id',
                    'status',
                ])->find($claim['model_id']);
                if ($ModelsJpa) {
                    $claim['model'] = gJSON::restore($ModelsJpa->toArray(), '__');
                }
            }

            $model = '';
            if (isset($claim['model'])) {
                $model = $claim['model']['model'];
            }

            $plan = '';
            if (isset($claim['plan'])) {
                $plan = $claim['plan']['plan'];
            }

            $template = str_replace(
                [
                    '{id}',
                    '{img_id}',
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
                    $claim['id'],
                    $branch_->name,
                    gTrace::getDate('long'),
                    $claim['client']['name'] . ' ' . $claim['client']['lastname'],
                    $claim['branch']['name'],
                    $claim['claim']['claim'],
                    $claim['description'],
                    $claim['date'],
                    $plan,
                    $model,
                    $claim['user_creation']['person']['name'] . ' ' . $claim['user_creation']['person']['lastname'],
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

    public function generateReports(Request $request)
    {
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

            $user = ViewUsers::select([
                'id',
                'username',
                'person__name',
                'person__lastname',
            ])->where('id', $userid)->first();

            $minDate = $request->date_start;
            $maxDate = $request->date_end;

            $query = ViewClaim::select('*')->whereNotNull('status');
            if (
                !isset($request->branch) &&
                !isset($request->date_start) &&
                !isset($request->date_end) &&
                !isset($request->claim)) {
                $minDate = $query->whereNotNull('date')->min('date');
                $maxDate = $query->whereNotNull('date')->max('date');
            } else {
                {
                    if (isset($request->date_start) && isset($request->date_end)) {
                        $query
                            ->whereBetween('date', [$request->date_start, $request->date_end]);
                    }
                }

                if (isset($request->branch)) {
                    $query->where('branch__id', $request->branch);
                }

                if (isset($request->claim)) {
                    $query->where('claim__id', $request->claim);
                }
            }

            $claimsJpa = $query->get();

            $claims = array();
            foreach ($claimsJpa as $claimJpa) {
                $claim = gJSON::restore($claimJpa->toArray(), '__');
                $claims[] = $claim;
            }

            $finalClaims = [];
            $claimsAll = 0;
            foreach ($claims as $claim) {
                $branchId = $claim['branch']['id'];
                $claimId = $claim['claim']['id'];

                if (!array_key_exists($branchId, $finalClaims)) {
                    $finalClaims[$branchId] = [
                        "id" => $branchId,
                        "name" => $claim['branch']['name'],
                        "claims" => [],
                    ];
                }
                $existingClaim = isset($finalClaims[$branchId]["claims"][$claimId]) ? $finalClaims[$branchId]["claims"][$claimId] : null;
                if (!$existingClaim) {
                    $finalClaims[$branchId]["claims"][$claimId] = [
                        "id" => $claimId,
                        "claim" => $claim['claim']['claim'],
                        "count" => 1,
                    ];
                } else {
                    $finalClaims[$branchId]["claims"][$claimId]["count"]++;
                }
                $claimsAll++;
            }

            $summary = "";

            foreach ($finalClaims as $branchId => $branchData) {
                $branchName = $branchData['name'];
                $branchTotal = 0; // Initialize branch total count

                $count_claims = count($branchData['claims']);
                $summary .= "<tr><td rowspan='{$count_claims}'>{$branchName}</td>";

                $claimsByBranch = 0;
                foreach ($branchData['claims'] as $cl) {
                    $claimsByBranch += $cl['count'];
                }

                $count = true;

                foreach ($branchData['claims'] as $claimData) {
                    if (!$count) {
                        $summary .= "<tr>";
                    }
                    $branchTotal += $claimData['count'];
                    $summary .= "<td>{$claimData['claim']}</td><td align='cente'>{$claimData['count']}</td>";
                    if ($count == 1) {
                        $summary .= "<td rowspan='{$count_claims}' align='center'>{$claimsByBranch}</td>";
                        $count = false;
                    }
                    $summary .= "</tr>";
                }
            }

            $template = str_replace(
                [
                    '{branch_onteraction}',
                    '{issue_long_date}',
                    '{ejecutive}',
                    '{date_start}',
                    '{date_end}',
                    '{claims_all}',
                    '{summary}',
                    '{description}',
                    '{date}',
                    '{plan}',
                    '{model}',
                    '{ejcecutive}',
                ],
                [
                    $branch_->name,
                    gTrace::getDate('long'),
                    $user->person__name . ' ' . $user->person__lastname,
                    $minDate,
                    $maxDate,
                    $claimsAll,
                    $summary,
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

    public function generateReportReitered(Request $request)
    {
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
            $template = file_get_contents('../storage/templates/reportsClaimsReitered.html');

            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();

            $user = ViewUsers::select([
                'id',
                'username',
                'person__name',
                'person__lastname',
            ])->where('id', $userid)->first();

            $summary = "";
            // $query = ViewClaim::select('*')->whereNotNull('status');

            $GetReitered = ViewClaim::whereNotNull('status')
                ->select('client__id', 'claim__id', DB::raw('COUNT(*) as count'))
                ->groupBy('client__id', 'claim__id')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            $rei = array();

            foreach ($GetReitered as $ReiteredJpa) {
                $GetRecords = ViewClaim::where('client__id', $ReiteredJpa->client__id)->where('claim__id', $ReiteredJpa->claim__id)->get();
                $claims = array();
                foreach ($GetRecords as $record) {
                    $claims[] = gJSON::restore($record->toArray(), '__');
                }
                $rei[] = $claims;
            }

            $color = true;
            $color_val = "bg-secondary";

            foreach ($rei as $reiJpa) {


                foreach ($reiJpa as $record) {
                    $summary.="
                    <tr>
                        <td class='{$color_val}'>{$record['client']['name']}{$record['client']['lastname']}</td>
                        <td class='{$color_val}'>{$record['claim']['claim']}</td>
                        <td class='{$color_val}'>{$record['branch']['name']}</td>
                        <td class='{$color_val}'>{$record['date']}</td>
                    </tr>
                    ";
                }


                if($color){
                    $color = false;
                    $color_val = "";
                }else{
                    $color = true;
                    $color_val = "bg-secondary";
                }

            }

            $template = str_replace(
                [
                    '{branch_onteraction}',
                    '{issue_long_date}',
                    '{summary}'
                ],
                [
                    $branch_->name,
                    gTrace::getDate('long'),
                    $summary,
                ],
                $template
            );

            $pdf->loadHTML($template);
            $pdf->render();
            return $pdf->stream('Reclamo.pdf');

            // $response = new Response();
            // $response->setStatus(200);
            // $response->setMessage('O');
            // $response->setData($rei);
            // return response(
            //     $response->toArray(),
            //     $response->getStatus()
            // );

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
