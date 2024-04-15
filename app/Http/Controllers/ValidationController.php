<?php

namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\Response;
use App\Models\SalesProducts;
use App\Models\Validations;
use App\Models\viewInstallations;
use App\Models\ViewPeople;
use App\Models\ViewUsers;
use App\Models\ViewValidationsBySale;
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
                ->orderBy($request->order['column'], $request->order['dir']);

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;

                if ($column == 'technical__name' || $column == '*') {
                    $q->orWhere('technical__name', $type, $value);
                }
                if ($column == 'client__name' || $column == '*') {
                    $q->orWhere('client__name', $type, $value);
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
                ->where('status_sale', 'PENDIENTE');
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
            $Validations->_sale = $request->sale;
            $Validations->validations = gJSON::stringify($request->validations);
            $Validations->creation_date = gTrace::getDate('mysql');
            $Validations->_creation_user = $userid;
            $Validations->update_date = gTrace::getDate('mysql');
            $Validations->_update_user = $userid;
            $Validations->status = "1";
            $Validations->save();

            $SalesProductsJpa = SalesProducts::find($request->sale);
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
            $Validations->_sale = $request->sale;
            $Validations->validations = gJSON::stringify($request->validations);
            $Validations->update_date = gTrace::getDate('mysql');
            $Validations->_update_user = $userid;
            $Validations->save();

            $SalesProductsJpa = SalesProducts::find($request->sale);
            $SalesProductsJpa->validation = $request->validation;
            $SalesProductsJpa->validation_id = $Validations->id;
            // $SalesProductsJpa->validation_date = gTrace::getDate('mysql');
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

            $template = str_replace(
                [
                    '{id}',
                    '{branch_onteraction}',
                    '{issue_long_date}',
                    '{ejecutive}',
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
                ],
                [
                    $request->id,
                    $branch_->name,
                    gTrace::getDate('long'),
                    $request->user_creation['person']['name'] . ' ' . $request->user_creation['person']['lastname'],
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
                {
                    if (isset($request->date_start) && isset($request->date_end)) {
                        $query
                            ->whereBetween('date', [$request->date_start, $request->date_end]);
                    }
                }

            }

            $branchSearch = null;

            if (isset($request->branch)) {
                $query->where('sale__branch__id', $request->branch);
                $branchSearch = Branch::find($request->branch);
            }
            $validationsJpa = $query->get();

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

            $sucursales = array_values($sucursales);

            foreach ($validations as $val) {
              
                $branId = $val['sale']['branch']['id'];

                $sucursales[$branId]['mount_validations']++;
                if ($val['sale']['type_operation']['operation'] == 'INSTALACION') {
                    $type = $val['type'];
                    if (!isset($type)) {
                        $type = 'duo';
                    }
                    $technicalId = $val['sale']['technical']['id'];
                    // Verificar si technicalId existe antes de acceder a sus sub-arreglos
                    if (isset($sucursales[$branId]['technicals'][$technicalId])) {
                      $sucursales[$branId]['technicals'][$technicalId]['installations']["duo"]++;
                    }
                } else {

                    $type = $val['type'];
                    if ($type == "") {
                        $type = 'duo';
                    }

                    $branId = $val['sale']['branch']['id'];
                    $technicalId = $val['sale']['technical']['id'];

                    if (isset($sucursales[$branId]['technicals'][$technicalId])) {
                        $sucursales[$branId]['technicals'][$technicalId]['fauls']["duo"]++;
                      }
                }
            }

            // SETEO
            $summary = '';
            $count = 1;
            foreach ($validations as $validation) {
                $summary .= "
                <tr>
                    <td align='center'>{$count}</td>
                    <td>{$validation['sale']['client']['name']} {$validation['sale']['client']['lastname']} - ({$validation['sale']['client']['phone']})</td>
                    <td>{$validation['sale']['technical']['name']} {$validation['sale']['technical']['lastname']}</td>
                    <td align='center'>{$validation['sale']['branch']['name']}</td>
                    <td align='center'>{$validation['sale']['validation']}</td>
                    <td align='center'>{$validation['creation_date']}</td>
                </tr>
                ";
                $count++;
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
                    $mount_validations,
                    $summary,
                ],
                $template
            );

            // $pdf->loadHTML($template);
            // $pdf->render();
            // return $pdf->stream('Reclamo.pdf');

            $response = new Response();
            $response->setStatus(200);
            $response->setMessage("GAAA");
            $response->setData($sucursales);
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
