<?php

namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\PhotographsByValidation;
use App\Models\Response;
use App\Models\SalesProducts;
use App\Models\User;
use App\Models\Validations;
use App\Models\viewInstallations;
use App\Models\ViewPeople;
use App\Models\ViewUsers;
use App\Models\ViewValidationsBySale;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ValidationController extends Controller
{
    public function paginate(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'validations', 'read')) {
                throw new Exception('No tienes permisos');
            }

            $query = viewInstallations::select([
                '*',
            ])
                ->orderBy($request->order['column'], $request->order['dir'])
                ->orderBy('validation', 'asc')->whereNotNull('status');

            if (!$request->all) {
                $query->where('status_sale', 'PENDIENTE');
            } else {
                // $query->where('status_sale', 'PENDIENTE');
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;

                if ($column == 'technical__name' || $column == '*') {
                    $q->orWhere(DB::raw("CONCAT(technical__name, ' ', technical__lastname)"), 'like', $value);
                }
                if ($column == 'client__name' || $column == '*') {
                    $q->orWhere(DB::raw("CONCAT(client__name, ' ', client__lastname)"), 'like', $value);
                }
                if ($column == 'user_creation__person__name' || $column == '*') {
                    $q->orWhere('user_creation__person__name', $type, $value);
                }
                if ($column == 'user_creation__person__lastname' || $column == '*') {
                    $q->orWhere('user_creation__person__lastname', $type, $value);
                }
                if ($column == 'branch__name' || $column == '*') {
                    $q->orWhere('branch__name', $type, $value);
                }
                if ($column == 'date_sale' || $column == '*') {
                    $q->orWhere('date_sale', $type, $value);
                }
            })
            ;
            // ->where('type_operation__operation', 'INSTALACION')
            // ->where('branch__correlative', $branch);

            $iTotalDisplayRecords = $query->count();

            $installationsPendingJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $installations = array();
            foreach ($installationsPendingJpa as $pending) {
                $install = gJSON::restore($pending->toArray(), '__');
                $installations[] = $install;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(Viewinstallations::count());
            $response->setData($installations);
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

    public function store(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'validations', 'create')) {
                throw new Exception('No tienes permisos para agregar');
            }

            if (
                !isset($request->validations) ||
                !isset($request->validation) ||
                !isset($request->type) ||
                !isset($request->sale)
            ) {
                throw new Exception('Error: No deje campos vacíos');
            }

            $Validations = new Validations();
            $Validations->type = $request->type;
            $Validations->validator = $request->validator;
            $Validations->_sale = $request->sale;
            $Validations->validations = gJSON::stringify($request->validations);
            $Validations->creation_date = gTrace::getDate('mysql');
            $Validations->_creation_user = $userid;
            $Validations->update_date = gTrace::getDate('mysql');
            $Validations->_update_user = $userid;
            $Validations->status = "1";
            $Validations->save();

            $SalesProductsJpa = SalesProducts::find($request->sale);

            if ($SalesProductsJpa->type_pay == "LIQUIDATION") {
                $SalesProductsJpa->status_sale = 'CULMINADA';
                $SalesProductsJpa->issue_date = gTrace::getDate('mysql');
                $SalesProductsJpa->_issue_user = $userid;
            }

            if ($request->type_submit == 'liquidation') {
                $SalesProductsJpa->status_sale = 'CULMINADA';
                $SalesProductsJpa->issue_date = gTrace::getDate('mysql');
                $SalesProductsJpa->_issue_user = $userid;
            }
            $SalesProductsJpa->validation = $request->validation;
            $SalesProductsJpa->validation_id = $Validations->id;
            $SalesProductsJpa->validation_date = gTrace::getDate('mysql');
            $SalesProductsJpa->save();

            $response->setStatus(200);
            $response->setMessage('Validacion registrada correctamente');
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

    public function getValidationBySale(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'validations', 'read')) {
                throw new Exception('No tienes permisos para agregar instalaciones');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception('Error: No deje campos vacíos');
            }

            $Validations = Validations::where('_sale', $request->id)->first();
            $Validations->validations = gJSON::parse($Validations->validations);

            $response->setStatus(200);
            $response->setMessage('Validacion registrada correctamente');
            $response->setData($Validations->toArray());
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

            if (!gValidate::check($role->permissions, $branch, 'validations', 'update')) {
                throw new Exception('No tienes permisos para actualizar');
            }

            if (
                !isset($request->validations) ||
                !isset($request->validation) ||
                !isset($request->type) ||
                !isset($request->id) ||
                !isset($request->sale)
            ) {
                throw new Exception('Error: No deje campos vacíos');
            }

            $Validations = Validations::find($request->id);
            $Validations->type = $request->type;
            $Validations->validator = $request->validator;
            $Validations->_sale = $request->sale;
            $Validations->validations = gJSON::stringify($request->validations);
            $Validations->update_date = gTrace::getDate('mysql');
            $Validations->_update_user = $userid;
            $Validations->save();

            $SalesProductsJpa = SalesProducts::find($request->sale);
            if ($SalesProductsJpa->type_pay == "LIQUIDATION") {
                $SalesProductsJpa->status_sale = 'CULMINADA';
                $SalesProductsJpa->issue_date = gTrace::getDate('mysql');
                $SalesProductsJpa->_issue_user = $userid;
            }
            if ($request->type_submit == 'liquidation') {
                $SalesProductsJpa->status_sale = 'CULMINADA';
                $SalesProductsJpa->issue_date = gTrace::getDate('mysql');
                $SalesProductsJpa->_issue_user = $userid;
            }
            $SalesProductsJpa->validation = $request->validation;
            $SalesProductsJpa->validation_id = $Validations->id;
            $SalesProductsJpa->validation_date = gTrace::getDate('mysql');
            $SalesProductsJpa->save();

            $response->setStatus(200);
            $response->setMessage('Validacion actializada correctamente');
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

    public function generateReportByValidation(Request $request)
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
            $template = file_get_contents('../storage/templates/validations/reportByValidation.html');

            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();

            $user = ViewUsers::select([
                'id',
                'username',
                'person__name',
                'person__lastname',
            ])->where('id', $userid)->first();

            $cuestions = "";
            $ValidationsJpa = Validations::find($request->validation_id);
            if ($ValidationsJpa && $ValidationsJpa->validations) {
                $ValidationsJpa->validations = gJSON::parse($ValidationsJpa->validations);
                if ($request->type_operation['operation'] == "INSTALACION") {
                    if (isset($ValidationsJpa->validations['service_status_group'])) {
                        $cuestions .= "
                        <tr>
                            <td>¿Su servicio de (internet/TV cable/ambos) funciona correctamente?</td>
                            <td align='center' style='background-color: " . ($ValidationsJpa->validations['service_status_group'] === 'SI' ? '#98fb9870' : '#FFC0CB') . "'>{$ValidationsJpa->validations['service_status_group']}</td>
                        </tr>
                        ";
                    } else {
                        $cuestions .= "
                        <tr>
                            <td>¿Su servicio de (internet/TV cable/ambos) funciona correctamente?</td>
                            <td'></td>
                        </tr>
                        ";
                    }

                    if (isset($ValidationsJpa->validations['verification_group'])) {
                        $cuestions .= "
                        <tr>
                            <td>¿Le ha explicado el técnico la velocidad contratada, la cantidad de megas que recibe y el estado del cableado?</td>
                            <td align='center'  style='background-color: " . ($ValidationsJpa->validations['verification_group'] === 'SI' ? '#98fb9870' : '#FFC0CB') . "'>{$ValidationsJpa->validations['verification_group']}</td>
                        </tr>";
                    } else {
                        $cuestions .= "
                        <tr>
                            <td>¿Le ha explicado el técnico la velocidad contratada, la cantidad de megas que recibe y el estado del cableado?</td>
                            <td ></td>
                        </tr>";
                    }

                    if (isset($ValidationsJpa->validations['speed_test_group'])) {
                        $cuestions .= "
                        <tr>
                            <td>¿Ha realizado pruebas de velocidad para verificar que cumple con lo contratado?</td>
                            <td align='center'  style='background-color: " . ($ValidationsJpa->validations['speed_test_group'] === 'SI' ? '#98fb9870' : '#FFC0CB') . "'>{$ValidationsJpa->validations['speed_test_group']}</td>
                        </tr>";
                    } else {
                        $cuestions .= "
                        <tr>
                            <td>¿Ha realizado pruebas de velocidad para verificar que cumple con lo contratado?</td>
                            <td ></td>
                        </tr>";
                    }

                    if (isset($ValidationsJpa->validations['coverage_group'])) {
                        $cuestions .= "
                        <tr>
                            <td>¿Se ha conectado a internet en diferentes dispositivos para verificar la cobertura en su hogar?</td>
                            <td align='center'  style='background-color: " . ($ValidationsJpa->validations['coverage_group'] === 'SI' ? '#98fb9870' : '#FFC0CB') . "'>{$ValidationsJpa->validations['coverage_group']}</td>
                        </tr>";
                    } else {
                        $cuestions .= "
                        <tr>
                            <td>¿Se ha conectado a internet en diferentes dispositivos para verificar la cobertura en su hogar?</td>
                            <td ></td>
                        </tr>";
                    }

                    if (isset($ValidationsJpa->validations['stability_group'])) {
                        $cuestions .= "
                        <tr>
                            <td>¿Ha tenido problemas con la estabilidad de la conexión a internet (cortes, intermitencias)?</td>
                            <td align='center'  style='background-color: " . ($ValidationsJpa->validations['stability_group'] === 'SI' ? '#98fb9870' : '#FFC0CB') . "'>{$ValidationsJpa->validations['stability_group']}</td>
                        </tr>";
                    } else {
                        $cuestions .= "
                        <tr>
                            <td>¿Ha tenido problemas con la estabilidad de la conexión a internet (cortes, intermitencias)?</td>
                            <td ></td>
                        </tr>";
                    }

                    if (isset($ValidationsJpa->validations['security_group'])) {
                        $cuestions .= "
                        <tr>
                            <td>¿Ha configurado la red Wi-Fi y ha probado la seguridad de la misma para internet?</td>
                            <td align='center'  style='background-color: " . ($ValidationsJpa->validations['security_group'] === 'SI' ? '#98fb9870' : '#FFC0CB') . "'>{$ValidationsJpa->validations['security_group']}</td>
                        </tr>";
                    } else {
                        $cuestions .= "
                        <tr>
                            <td>¿Ha configurado la red Wi-Fi y ha probado la seguridad de la misma para internet?</td>
                            <td ></td>
                        </tr>";
                    }

                    if (isset($ValidationsJpa->validations['tv_cable_explanation_group'])) {
                        $cuestions .= "
                        <tr>
                            <td>¿Le ha explicado el técnico la calidad de la señal, la cantidad de canales disponibles y el estado del cableado?</td>
                            <td align='center'  style='background-color: " . ($ValidationsJpa->validations['tv_cable_explanation_group'] === 'SI' ? '#98fb9870' : '#FFC0CB') . "'>{$ValidationsJpa->validations['tv_cable_explanation_group']}</td>
                        </tr>";
                    } else {
                        $cuestions .= "
                        <tr>
                            <td>¿Le ha explicado el técnico la calidad de la señal, la cantidad de canales disponibles y el estado del cableado?</td>
                            <td ></td>
                        </tr>";
                    }

                    if (isset($ValidationsJpa->validations['tv_cable_quality_group'])) {
                        $cuestions .= "
                        <tr>
                            <td>¿Ha verificado la calidad de la imagen y el sonido en diferentes canales de TV cable?
                            </td>
                            <td align='center'  style='background-color: " . ($ValidationsJpa->validations['tv_cable_quality_group'] === 'SI' ? '#98fb9870' : '#FFC0CB') . "'>{$ValidationsJpa->validations['tv_cable_quality_group']}</td>
                        </tr>";
                    } else {
                        $cuestions .= "
                        <tr>
                            <td>¿Ha verificado la calidad de la imagen y el sonido en diferentes canales de TV cable?
                            </td>
                            <td ></td>
                        </tr>";
                    }

                    if (isset($ValidationsJpa->validations['tv_cable_signal_group'])) {
                        $cuestions .= "
                        <tr>
                            <td>¿Ha experimentado cortes o intermitencias en la señal de TV cable?</td>
                            <td align='center'  style='background-color: " . ($ValidationsJpa->validations['tv_cable_signal_group'] === 'SI' ? '#98fb9870' : '#FFC0CB') . "'>{$ValidationsJpa->validations['tv_cable_signal_group']}</td>
                        </tr>
                        ";
                    } else {
                        $cuestions .= "
                        <tr>
                            <td>¿Ha experimentado cortes o intermitencias en la señal de TV cable?</td>
                            <td ></td>
                        </tr>
                        ";
                    }
                } else {
                    if (isset($ValidationsJpa->validations['service_status_group'])) {
                        $cuestions .= "
                        <tr>
                            <td>¿Su servicio de (internet/TV cable/ambos) funciona correctamente?</td>
                            <td align='center'  style='background-color: " . ($ValidationsJpa->validations['service_status_group'] === 'SI' ? '#98fb9870' : '#FFC0CB') . "'>{$ValidationsJpa->validations['service_status_group']}</td>
                        </tr>";
                    } else {
                        $cuestions .= "
                        <tr>
                            <td>¿Su servicio de (internet/TV cable/ambos) funciona correctamente?</td>
                            <td ></td>
                        </tr>";
                    }

                    if (isset($ValidationsJpa->validations['internet_speed_stability'])) {
                        $cuestions .= "
                        <tr>
                            <td>¿Está recibiendo la velocidad contratada de internet y es estable la conexión?</td>
                            <td align='center'  style='background-color: " . ($ValidationsJpa->validations['internet_speed_stability'] === 'SI' ? '#98fb9870' : '#FFC0CB') . "'>{$ValidationsJpa->validations['internet_speed_stability']}</td>
                        </tr>";
                    } else {
                        $cuestions .= "
                        <tr>
                            <td>¿Está recibiendo la velocidad contratada de internet y es estable la conexión?</td>
                            <td ></td>
                        </tr>";
                    }

                    if (isset($ValidationsJpa->validations['tv_channel_verification_group'])) {
                        $cuestions .= "
                        <tr>
                            <td>¿Está recibiendo la cantidad contratada de canales y es estable la calidad de video y audio?</td>
                            <td align='center'  style='background-color: " . ($ValidationsJpa->validations['tv_channel_verification_group'] === 'SI' ? '#98fb9870' : '#FFC0CB') . "'>{$ValidationsJpa->validations['tv_channel_verification_group']}</td>
                        </tr>
                        ";
                    } else {
                        $cuestions .= "
                        <tr>
                            <td>¿Está recibiendo la cantidad contratada de canales y es estable la calidad de video y audio?</td>
                            <td ></td>
                        </tr>
                        ";
                    }
                }

            } else {
                $ValidationsJpa = Validations::where('_sale', $request->id)->whereNotNull('status')->first();
                if (!$ValidationsJpa) {
                    $cuestions = "
                    <tr>
                        <td class='bg-red' colspan='2' align='center'>NO SE ENCONTRO VALIDACIÓN</td>
                    </tr>
                    ";
                } else {
                    $ValidationsJpa->validations = gJSON::parse($ValidationsJpa->validations);
                }
            }

            $bg_validation = "bg-green";

            if ($request->validation) {
                $validation = $request->validation;
            } else {
                $validation = 0;
            }

            if ($request->validation < 10) {
                if ($request->validation < 5) {
                    $bg_validation = 'bg-red';
                } else {
                    $bg_validation = 'bg-orange';
                }
            }

            $validation_type = "NO SELECIONADO";

            if ($ValidationsJpa->type == 'internet') {
                $validation_type = "INTERNET";
            } else if ($ValidationsJpa->type == 'cable') {
                $validation_type = "CABLE";
            } else if ($ValidationsJpa->type == 'duo') {
                $validation_type = "DUO (INTERNET Y CABLE)";
            }

            $coment = "";

            if (isset($ValidationsJpa->validations['comments'])) {
                $coment = $ValidationsJpa->validations['comments'];
            } else {
                $coment = "<i>Sin comentarios</i>";
            }

            $PhotographsByValidationJpa = PhotographsByValidation::select(['id', 'description', '_creation_user', 'creation_date'])
                ->where('_validation', $request->validation_id)->whereNotNUll('status')
                ->orderBy('id', 'desc')
                ->get();

            $images = '';
            $count = 1;

            foreach ($PhotographsByValidationJpa as $image) {

                $userCreation = User::select([
                    'users.id as id',
                    'users.username as username',
                ])
                    ->where('users.id', $image->_creation_user)->first();

                $images .= "
                <div style='page-break-before: always;'>
                    <p><strong>{$count}) {$image->description}</strong></p>
                    <p style='margin-left:18px'>Fecha: {$image->creation_date}</p>
                    <p style='margin-left:18px'>Usuario: {$userCreation->username}</p>
                    <center>
                        <img src='http://almacen.fastnetperu.com.pe/api/validationsimg/{$image->id}/full' alt='-'
                       class='evidences'
                    </center>
                </div>
                ";
                $count += 1;
            }

            $template = str_replace(
                [
                    '{id}',
                    '{branch_onteraction}',
                    '{issue_long_date}',
                    '{ejecutive}',
                    '{liquidation}',
                    '{operation}',
                    '{type_sale}',
                    '{client}',
                    '{phone}',
                    '{technical}',
                    '{type}',
                    '{validation}',
                    '{color_validation}',
                    '{opinion}',
                    '{cuestions}',
                    '{images}',
                ],
                [
                    $request->id,
                    $branch_->name,
                    gTrace::getDate('long'),
                    $request->user_creation['person']['name'] . ' ' . $request->user_creation['person']['lastname'],
                    $request->status_sale,
                    $request->type_operation['operation'],
                    str_replace('_', ' ', $request->type_intallation),
                    $request->client['name'] . ' ' . $request->client['lastname'],
                    $request->client['phone'],
                    $request->technical['name'] . ' ' . $request->technical['lastname'],
                    $validation_type,
                    $validation,
                    $bg_validation,
                    $coment,
                    $cuestions,
                    $images,
                ],
                $template
            );

            $pdf->loadHTML($template);
            $pdf->render();
            return $pdf->stream('VALIDACIONES.pdf');
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

    public function generateReportGeneral(Request $request)
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
            $template = file_get_contents('../storage/templates/validations/reportGeneral.html');

            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();

            $user = ViewUsers::select([
                'id',
                'username',
                'person__name',
                'person__lastname',
            ])->where('id', $userid)->first();

            $minDate = $request->date_start;
            $maxDate = $request->date_end;

            $query = ViewValidationsBySale::select('*')->whereNotNull('status')->whereNotNull('sale__status')->orderBy('creation_date', 'DESC');

            if (
                !isset($request->date_start) &&
                !isset($request->date_end)) {
                $minDate = $query->whereNotNull('creation_date')->min('creation_date');
                $maxDate = $query->whereNotNull('creation_date')->max('creation_date');
            } else {

                $fechaInicio = Carbon::parse($request->date_start);
                $fechaFin = Carbon::parse($request->date_end);

                $fechaInicio->setTime(0, 0, 0); // Establecer la fecha de inicio a 00:00:00
                $fechaFin->setTime(23, 59, 59); // Establecer la fecha de fin a 23:59:59

                if (isset($request->date_start) && isset($request->date_end)) {
                    $query
                        ->whereBetween('creation_date', [$fechaInicio, $fechaFin]);
                }
            }

            $branchSearch = null;

            if (isset($request->branch)) {
                $query->where('sale__branch__id', $request->branch);
                $branchSearch = Branch::find($request->branch);
            }
            $validationsJpa = $query->whereNot("sale__branch__id", 8)->get();

            $mount_validations = $query->count();

            $validations = array();
            foreach ($validationsJpa as $validationJpa) {
                $validation = gJSON::restore($validationJpa->toArray(), '__');
                $validation['validations'] = gJSON::parse($validation['validations']);
                $validations[] = $validation;
            }

            // ORDER BY BRANCH
            $branch_summary = '';

            $branchesJpa = Branch::get();

            $sucursales = [];
            foreach ($branchesJpa as $branch) {
                $branchName = $branch->name;
                $branId = $branch->id;
                if ($branId != 8) {
                    $sucursales[$branId] = [
                        'id' => $branId,
                        'name' => $branchName,
                        'technicals' => [],
                        'validations' => [],
                        'mount_validations' => 0,
                    ];
                    $technicalsJpa = ViewPeople::select([
                        'id',
                        'name',
                        'lastname',
                        'type',
                        'branch__id',
                        'branch__name',
                        'branch__correlative',
                        'status',
                    ])
                        ->whereNotNull('status')
                        ->orderBy('name', 'DESC')
                        ->where('type', 'TECHNICAL')
                        ->where('branch__id', $branch->id)->get();

                    foreach ($technicalsJpa as $technical) {
                        $technicalName = $technical->name . ' ' . $technical->lastname;
                        $technicalId = $technical->id;
                        $sucursales[$branId]['technicals'][$technicalId] = [
                            'id' => $technicalId,
                            'name' => $technicalName,
                            'validations' => [],
                            'installations' => [],
                            'fauls' => [],
                        ];

                        $sucursales[$branId]['technicals'][$technicalId]['installations']['duo'] = 0;
                        $sucursales[$branId]['technicals'][$technicalId]['installations']['internet'] = 0;
                        $sucursales[$branId]['technicals'][$technicalId]['installations']['cable'] = 0;

                        $sucursales[$branId]['technicals'][$technicalId]['fauls']['duo'] = 0;
                        $sucursales[$branId]['technicals'][$technicalId]['fauls']['internet'] = 0;
                        $sucursales[$branId]['technicals'][$technicalId]['fauls']['cable'] = 0;

                    }
                }
            }

            // $sucursales = array_values($sucursales);

            foreach ($validations as $val) {

                $branId = $val['sale']['branch']['id'];

                $sucursales[$branId]['mount_validations']++;

                // $sucursales[$branId]['validations'][] = $val;
                $technicalId = $val['sale']['technical']['id'];
                $technicalName = $val['sale']['technical']['name'] . ' ' . $val['sale']['technical']['lastname'];

                if ($val['sale']['type_operation']['operation'] == 'INSTALACION') {
                    $type = $val['type'];
                    if ($type == "") {
                        $type = 'internet';
                    }

                    if (!isset($sucursales[$branId]['technicals'][$technicalId])) {
                        // $sucursales[$branId]['technicals'][$technicalId]['installations'];

                        $sucursales[$branId]['technicals'][$technicalId] = [
                            'id' => $technicalId,
                            'name' => $technicalName,
                            'validations' => [],
                            'installations' => [],
                            'fauls' => [],
                        ];

                        $sucursales[$branId]['technicals'][$technicalId]['installations']['duo'] = 0;
                        $sucursales[$branId]['technicals'][$technicalId]['installations']['internet'] = 0;
                        $sucursales[$branId]['technicals'][$technicalId]['installations']['cable'] = 0;

                        $sucursales[$branId]['technicals'][$technicalId]['fauls']['duo'] = 0;
                        $sucursales[$branId]['technicals'][$technicalId]['fauls']['internet'] = 0;
                        $sucursales[$branId]['technicals'][$technicalId]['fauls']['cable'] = 0;

                        $sucursales[$branId]['technicals'][$technicalId]['installations'][$type] = 1;
                    } else {
                        $sucursales[$branId]['technicals'][$technicalId]['installations'][$type]++;
                    }
                } else {

                    $type = $val['type'];
                    if ($type == "") {
                        $type = 'internet';
                    }
                    if (!isset($sucursales[$branId]['technicals'][$technicalId])) {
                        $sucursales[$branId]['technicals'][$technicalId] = [
                            'id' => $technicalId,
                            'name' => $technicalName,
                            'validations' => [],
                            'installations' => [],
                            'fauls' => [],
                        ];

                        $sucursales[$branId]['technicals'][$technicalId]['installations']['duo'] = 0;
                        $sucursales[$branId]['technicals'][$technicalId]['installations']['internet'] = 0;
                        $sucursales[$branId]['technicals'][$technicalId]['installations']['cable'] = 0;

                        $sucursales[$branId]['technicals'][$technicalId]['fauls']['duo'] = 0;
                        $sucursales[$branId]['technicals'][$technicalId]['fauls']['internet'] = 0;
                        $sucursales[$branId]['technicals'][$technicalId]['fauls']['cable'] = 0;
                        // $sucursales[$branId]['technicals'][$technicalId]['installations'];
                        $sucursales[$branId]['technicals'][$technicalId]['fauls'][$type] = 1;
                    } else {
                        $sucursales[$branId]['technicals'][$technicalId]['fauls'][$type]++;
                    }
                }
            }

            $sucursales = array_values($sucursales);

            foreach ($sucursales as $branch) {
                $branch_summary .= "
                    <tr class='bg-yellow'>
                        <td>{$branch['name']}</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td ></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td ></td>
                    </t>
                ";
                foreach ($branch['technicals'] as $technicals) {
                    if (isset($technicals["id"])) {
                        $faul_duo = 0;
                        $faul_internet = 0;
                        $faul_cable = 0;
                        if (isset($technicals['fauls']['duo'])) {
                            $faul_duo = $technicals['fauls']['duo'];
                        }

                        if (isset($technicals['fauls']['internet'])) {
                            $faul_internet = $technicals['fauls']['internet'];
                        }
                        if (isset($technicals['fauls']['cable'])) {
                            $faul_cable = $technicals['fauls']['cable'];
                        }

                        $installation__duo = 0;
                        $installation__internet = 0;
                        $installation__cable = 0;
                        if (isset($technicals['installations']['duo'])) {
                            $installation__duo = $technicals['installations']['duo'];
                        }

                        if (isset($technicals['installations']['internet'])) {
                            $installation__internet = $technicals['installations']['internet'];
                        }

                        if (isset($technicals['installations']['cable'])) {
                            $installation__cable = $technicals['installations']['cable'];
                        }

                        if (!isset($technicals['installations']['duo'])) {
                            throw new Exception(json_encode($technicals));
                        }

                        $total_faulds = intval($faul_duo) + intval($faul_internet) + intval($faul_cable);
                        $total_instalation = intval($installation__duo) + intval($installation__internet) + intval($installation__cable);
                        $branch_summary .= "
                        <tr>
                            <td>{$technicals['name']}</td>
                            <td align='center' class='" . ($technicals['fauls']['duo'] > 0 ? 'bg-green' : ' ') . " ' >{$technicals['fauls']['duo']}</td>
                            <td align='center'  class='" . ($technicals['fauls']['internet'] > 0 ? 'bg-green' : ' ') . " ' >{$technicals['fauls']['internet']}</td>
                            <td align='center' class='" . ($technicals['fauls']['cable'] > 0 ? 'bg-green' : ' ') . " '  >{$technicals['fauls']['cable']}</td>
                            <td align='center' class='bg-secondary' >{$total_faulds}</td>
                            <td align='center' class='" . ($technicals['installations']['duo'] > 0 ? 'bg-green' : ' ') . " '  >{$technicals['installations']['duo']}</td>
                            <td align='center' class='" . ($technicals['installations']['internet'] > 0 ? 'bg-green' : ' ') . " '  >{$technicals['installations']['internet']}</td>
                            <td align='center' class='" . ($technicals['installations']['cable'] > 0 ? 'bg-green' : ' ') . " '  >{$technicals['installations']['cable']}</td>
                            <td align='center' class='bg-secondary' >{$total_instalation}</td>
                        </t>
                    ";
                    }
                }

            }

            // SETEO
            $summary = '';

            foreach ($sucursales as $brach) {

                $summary .= "
                    <tr class='bg-orange'>
                        <td colspan='2'>
                            {$brach['name']}
                        </td>
                        <td>
                            VALIDACIÓNES
                        </td>
                        <td style='font-size:13px;text-align:center;'>
                            {$brach['mount_validations']}
                        </td>
                    </tr>
                    <tr class='bg-secondary'>
                        <td align='center'>#</td>
                        <td align='center'>CLIENTE</td>
                        <td align='center'>TECNICO</td>
                        <td align='center'>COMENTARIOS</td>
                    </tr>
                ";
                $count = 1;
                foreach ($brach['validations'] as $validation) {
                    $summary .= "
                        <tr>
                            <td align='center'>{$count}</td>
                            <td>
                                <p>{$validation['sale']['client']['name']} {$validation['sale']['client']['lastname']}</p>
                                <p><strong>{$validation['sale']['client']['phone']}</strong> / {$validation['creation_date']}</p>
                            </td>
                            <td>
                                <p><center>{$validation['sale']['technical']['name']} {$validation['sale']['technical']['lastname']}</center></p>
                                <p><center style='font-size:13px;'>{$validation['sale']['validation']}</center></p>
                            </td>
                            <td>
                                <p>{$validation['validations']['comments']}</p>
                            </td>
                        </tr>
                    ";
                    $count++;
                }

            }

            $branchSelected = 'GENERALES';
            if ($branchSearch) {
                $branchSelected = $branchSearch->name;
            }

            $template = str_replace(
                [
                    '{ejecutive}',
                    '{date_start}',
                    '{date_end}',
                    '{branch_selected}',
                    '{mount_validations}',
                    '{branch_summary}',
                    '{summary}',
                ],
                [
                    $user->person__name . ' ' . $user->person__lastname,
                    $minDate,
                    $maxDate,
                    $branchSelected,
                    $mount_validations,
                    $branch_summary,
                    $summary,
                ],
                $template
            );

            $pdf->loadHTML($template);
            $pdf->render();
            return $pdf->stream('VALIDACIONES.pdf');

            // $response = new Response();
            // $response->setStatus(200);
            // $response->setMessage("GAAA");
            // $response->setData($sucursales);
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
            $template = file_get_contents('../storage/templates/validations/reportReytered.html');

            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();

            $user = ViewUsers::select([
                'id',
                'username',
                'person__name',
                'person__lastname',
            ])->where('id', $userid)->first();

            $summary = "";


            $GetReitered = viewInstallations::whereNotNull('status')
                ->where('type_operation__operation', 'AVERIA')
                ->whereBetween('date_sale', [$request->date_start, $request->date_end])
                ->select('client__id', DB::raw('COUNT(*) as count'))
                ->groupBy('client__id')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            $rei = [];

            foreach ($GetReitered as $ReiteredJpa) {
                $GetRecords = viewInstallations::where('client__id', $ReiteredJpa->client__id)
                    ->where('type_operation__operation', 'AVERIA')
                    ->whereBetween('date_sale', [$request->date_start, $request->date_end])
                    ->whereNotNull('status')
                    ->get();
                $reitered = [];
                foreach($GetRecords as $av){
                    $reitered [] = gJSON::restore($av->toArray(), '__');
                }
                $rei[] =  $reitered;
            }

            $color = true;
            $color_val = "bg-secondary";

            foreach ($rei as $reiJpa) {

                foreach ($reiJpa as $record) {
                    $type_intallation = str_replace('_',' ',$record['type_intallation']);
                    $summary.="
                    <tr>
                        <td class='{$color_val}'>{$record['client']['name']} {$record['client']['lastname']}</td>
                        <td class='{$color_val}'>{$record['technical']['name']} {$record['technical']['lastname']}</td>
                        <td class='{$color_val}'>{$type_intallation} </td>
                        <td class='{$color_val}'>{$record['branch']['name']}</td>
                        <td class='{$color_val}'>{$record['date_sale']}</td>
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
            return $pdf->stream('AVERIAS REITERADAS.pdf');

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

    public function generateReportNotValidations(Request $request)
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
            $template = file_get_contents('../storage/templates/validations/reportGeneralNotValidation.html');

            // $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();

            $user = ViewUsers::select([
                'id',
                'username',
                'person__name',
                'person__lastname',
            ])->where('id', $userid)->first();

            $minDate = $request->date_start;
            $maxDate = $request->date_end;

            $query = viewInstallations::select([
                '*',
            ])
                ->orderBy('date_sale', 'desc')
                ->whereNotNull('status')
                ->where('status_sale', 'PENDIENTE')
                ->whereNull('validation_date');

            if (

                !isset($request->date_start) &&
                !isset($request->date_end)) {
                $minDate = $query->whereNotNull('date_sale')->min('date_sale');
                $maxDate = $query->whereNotNull('date_sale')->max('date_sale');
            } else {

                // $fechaInicio = Carbon::parse($request->date_start);
                // $fechaFin = Carbon::parse($request->date_end);

                $dateStart = date('Y-m-d', strtotime($request->date_start));
                $dateEnd = date('Y-m-d', strtotime($request->date_end));

                if (isset($request->date_start) && isset($request->date_end)) {
                    $query
                        ->whereBetween('date_sale', [$dateStart, $dateEnd]);
                }
            }

            $branchSearch = null;

            if (isset($request->branch)) {
                $query->where('branch__id', $request->branch);
                $branchSearch = Branch::find($request->branch);
            }
            $validationsJpa = $query->whereNot("branch__id", 8)->get();

            $mount_sales = $query->count();

            $sales = array();
            foreach ($validationsJpa as $salesJpa) {
                $sale = gJSON::restore($salesJpa->toArray(), '__');
                $sales[] = $sale;
            }

            $branchesJpa = Branch::get();

            $sucursales = [];
            foreach ($branchesJpa as $branch) {
                $branchName = $branch->name;
                $branId = $branch->id;
                if ($branId != 8) {
                    $sucursales[$branId] = [
                        'id' => $branId,
                        'name' => $branchName,
                        'validations' => [],
                        'mount_sales' => 0,
                    ];
                }
            }

            // // $sucursales = array_values($sucursales);

            foreach ($sales as $val) {
                $branId = $val['branch']['id'];
                $sucursales[$branId]['mount_sales']++;
                $sucursales[$branId]['validations'][] = $val;
            }

            $sucursales = array_values($sucursales);

            $summary = '';

            foreach ($sucursales as $branch) {
                $summary .= "
                    <tr  class='bg-blue'>
                        <td colspan='4' align='center'>{$branch['name']}</td>
                        <td align='center' >{$branch['mount_sales']}</td>
                    </t>
                    <tr>
                        <td class='bg-secondary'>ID</td>
                        <td class='bg-secondary'>CLIENTE</td>
                        <td class='bg-secondary'>TÉCNICO</td>
                        <td class='bg-secondary'>TIPO</td>
                        <td class='bg-secondary'>FECHA</td>
                    </tr>
                ";
                foreach ($branch['validations'] as $val) {
                    $summary .= "
                    <tr>
                        <td>{$val['id']}</td>
                        <td>{$val['client']['name']} {$val['client']['lastname']}</td>
                        <td>{$val['technical']['name']} {$val['technical']['lastname']}</td>
                        <td>{$val['type_operation']['operation']}</td>
                        <td>{$val['date_sale']}</td>
                    </t>
                ";
                }
            }

            $branchSelected = 'GENERALES';
            if ($branchSearch) {
                $branchSelected = $branchSearch->name;
            }

            $template = str_replace(
                [
                    '{ejecutive}',
                    '{date_start}',
                    '{date_end}',
                    '{branch_selected}',
                    '{mount_validations}',
                    '{summary}',
                ],
                [
                    $user->person__name . ' ' . $user->person__lastname,
                    $minDate,
                    $maxDate,
                    $branchSelected,
                    $mount_sales,
                    $summary,
                ],
                $template
            );

            $pdf->loadHTML($template);
            $pdf->render();
            return $pdf->stream('Reclamo.pdf');

            // $response = new Response();
            // $response->setStatus(200);
            // $response->setMessage("GAAA");
            // $response->setData($sucursales);
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

    public function setImage(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'cars', 'read')) {
                throw new Exception("No tienes permisos para actualizar");
            }

            // if (
            //     !isset($request->_review) && !isset($request->_car)
            // ) {
            //     throw new Exception("Error: No deje campos vacíos");
            // }

            $PhotographsByValidation = new PhotographsByValidation();
            $PhotographsByValidation->_validation = $request->_validation;
            if (isset($request->description)) {
                $PhotographsByValidation->description = $request->description;
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
                    $PhotographsByValidation->image_type = $request->image_type;
                    $PhotographsByValidation->image_mini = base64_decode($request->image_mini);
                    $PhotographsByValidation->image_full = base64_decode($request->image_full);
                } else {
                    throw new Exception("Una imagen debe ser cargada.");
                }
            } else {
                throw new Exception("Una imagen debe ser cargada.");
            }

            $PhotographsByValidation->_creation_user = $userid;
            $PhotographsByValidation->creation_date = gTrace::getDate('mysql');
            $PhotographsByValidation->_update_user = $userid;
            $PhotographsByValidation->update_date = gTrace::getDate('mysql');
            $PhotographsByValidation->status = "1";
            $PhotographsByValidation->save();

            $response->setStatus(200);
            $response->setMessage('');
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

    public function updateImage(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'cars', 'read')) {
                throw new Exception("No tienes permisos para actualizar");
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $PhotographsByValidationJpa = PhotographsByValidation::find($request->id);
            $PhotographsByValidationJpa->description = $request->description;

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
                    $PhotographsByValidationJpa->image_type = $request->image_type;
                    $PhotographsByValidationJpa->image_mini = base64_decode($request->image_mini);
                    $PhotographsByValidationJpa->image_full = base64_decode($request->image_full);
                }
            }

            $PhotographsByValidationJpa->_update_user = $userid;
            $PhotographsByValidationJpa->update_date = gTrace::getDate('mysql');
            $PhotographsByValidationJpa->save();

            $response->setStatus(200);
            $response->setMessage('Imagen guardada correctamente');
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

    public function getImages(Request $request, $id)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'cars', 'read')) {
                throw new Exception("No tienes permisos para actualizar");
            }

            if (
                !isset($id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $PhotographsByValidationJpa = PhotographsByValidation::select(['id', 'description', '_creation_user', 'creation_date', '_update_user', 'update_date'])
                ->where('_validation', $id)->whereNotNUll('status')
                ->orderBy('id', 'desc')
                ->get();

            $response->setStatus(200);
            $response->setMessage('Operación correcta.');
            $response->setData($PhotographsByValidationJpa->toArray());
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

    public function images($id, $size)
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

            $PhotographsByValidationJpa = PhotographsByValidation::select([
                "photographs_by_validation.image_$size as image_content",
                'photographs_by_validation.image_type',

            ])
                ->where('id', $id)
                ->first();

            if (!$PhotographsByValidationJpa) {
                throw new Exception('No se encontraron datos');
            }

            if (!$PhotographsByValidationJpa->image_content) {
                throw new Exception('No existe imagen');
            }

            $content = $PhotographsByValidationJpa->image_content;
            $type = $PhotographsByValidationJpa->image_type;
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

    public function deleteImage(Request $request, $id)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'cars', 'read')) {
                throw new Exception("No tienes permisos para actualizar");
            }

            if (
                !isset($id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $PhotographsByValidationJpa = PhotographsByValidation::find($id);
            $PhotographsByValidationJpa->_update_user = $userid;
            $PhotographsByValidationJpa->update_date = gTrace::getDate('mysql');
            $PhotographsByValidationJpa->status = null;
            $PhotographsByValidationJpa->save();

            $response->setStatus(200);
            $response->setMessage('Imagen eliminada correctamente');
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
