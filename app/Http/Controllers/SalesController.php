<?php

namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\Parcel;
use App\Models\Plant;
use App\Models\Response;
use App\Models\SalesProducts;
use App\Models\ViewDetailsSales;
use App\Models\viewInstallations;
use App\Models\ViewSales;
use App\Models\ViewUsers;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Database\Query\Builder;

class SalesController extends Controller
{
    public function paginate(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'record_sales', 'read')) {
                throw new Exception('No tienes permisos para listar las salidas');
            }

            // $query = ViewSales::select([
            //     '*',
            // ])
            //     ->orderBy('view_sales.'.$request->order['column'], $request->order['dir'])
            //     ->whereNotNUll('view_sales.status')
            //     ->where('view_sales.branch__correlative', $branch);

            $query = ViewSales::select([
                'view_sales.id as id',
                'view_sales.client_id as client_id',
                'view_sales.technical_id as technical_id',
                'view_sales.branch__id as branch__id',
                'view_sales.branch__name as branch__name',
                'view_sales.branch__correlative	 as branch__correlative',
                'view_sales.type_operation__id	 as type_operation__id',
                'view_sales.type_operation__operation	 as type_operation__operation',
                'view_sales.tower_id as tower_id',
                'view_sales.plant_id as plant_id',
                'view_sales.room_id as room_id',
                'view_sales.type_intallation as type_intallation',
                'view_sales.date_sale as date_sale',
                'view_sales.issue_date as issue_date',
                'view_sales.issue_user_id as issue_user_id',
                'view_sales.status_sale as status_sale',
                'view_sales.description as description',
                'view_sales.user_creation__id as user_creation__id',
                'view_sales.user_creation__username as user_creation__username',
                'view_sales.user_creation__person__id as user_creation__person__id',
                'view_sales.user_creation__person__name as user_creation__person__name',
                'view_sales.user_creation__person__lastname as user_creation__person__lastname',
                'view_sales.creation_date as creation_date',
                'view_sales.update_user_id as update_user_id',
                'view_sales.update_date as update_date',
                'view_sales.status as status',

            ])
                ->distinct()
                ->leftJoin('view_details_sales', 'view_sales.id', '=', 'view_details_sales.sale_product_id')
                ->whereNotNull('view_sales.status')
                ->where('view_sales.branch__correlative', $branch)
                ->orderBy('view_sales.' . $request->order['column'], $request->order['dir']);

            if (isset($request->search['model'])) {
                $query
                    ->where('view_details_sales.product__model__id', $request->search['model']);
            }

            if (isset($request->search['date_start']) || isset($request->search['date_end'])) {
                $dateStart = date('Y-m-d', strtotime($request->search['date_start']));
                $dateEnd = date('Y-m-d', strtotime($request->search['date_end']));

                $query->where('date_sale', '>=', $dateStart)
                    ->where('date_sale', '<=', $dateEnd);
            }

            if ($request->search['column'] != '*') {
                if ($request->search['column'] == 'INSTALLATION') {
                    $query->where('type_operation__operation', 'INSTALACIÓN');
                } else if ($request->search['column'] == 'FAULD') {
                    $query->where('type_operation__operation', 'AVERIA');
                } else if ($request->search['column'] == 'TOWER') {
                    $query->where('type_operation__operation', 'TORRE');
                } else if ($request->search['column'] == 'PLANT') {
                    $query->where('type_operation__operation', 'PLANTA');
                } else if ($request->search['column'] == 'TECHNICAL') {
                    $query->where('type_operation__operation', 'PARA TECNICO');
                } else if($request->search['column'] == 'SALES'){
                    $query->where('type_operation__operation', 'VENTA');
                }
            }

            $iTotalDisplayRecords = $query->count();

            $salesJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $sales = array();
            foreach ($salesJpa as $saleJpa) {
                $sale = gJSON::restore($saleJpa->toArray(), '__');
                $detailSalesJpa = ViewDetailsSales::select(['*'])->whereNotNull('status')->where('sale_product_id', $sale['id'])->get();
                $details = array();
                foreach ($detailSalesJpa as $detailJpa) {
                    $detail = gJSON::restore($detailJpa->toArray(), '__');
                    $details[] = $detail;
                }
                $sale['details'] = $details;
                $sales[] = $sale;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewSales::where('branch__correlative', $branch)->count());
            $response->setData($sales);
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

    public function generateReportBydate(Request $request)
    {
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'read')) {
                throw new Exception('No tienes permisos para listar encomiedas creadas');
            }
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $pdf = new Dompdf($options);
            $template = file_get_contents('../storage/templates/reportSales.html');

            if (
                !isset($request->date_start) ||
                !isset($request->date_end)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();
            $user = ViewUsers::select([
                'id',
                'username',
                'person__name',
                'person__lastname',
            ])->where('id', $userid)->first();
            $sumary = '';

            $dateStart = date('Y-m-d', strtotime($request->date_start));
            $dateEnd = date('Y-m-d', strtotime($request->date_end));

            $query = ViewSales::select([
                'view_sales.id as id',
                'view_sales.client_id as client_id',
                'view_sales.technical_id as technical_id',
                'view_sales.branch__id as branch__id',
                'view_sales.branch__name as branch__name',
                'view_sales.branch__correlative	 as branch__correlative',
                'view_sales.type_operation__id	 as type_operation__id',
                'view_sales.type_operation__operation	 as type_operation__operation',
                'view_sales.tower_id as tower_id',
                'view_sales.plant_id as plant_id',
                'view_sales.room_id as room_id',
                'view_sales.type_intallation as type_intallation',
                'view_sales.date_sale as date_sale',
                'view_sales.issue_date as issue_date',
                'view_sales.issue_user_id as issue_user_id',
                'view_sales.status_sale as status_sale',
                'view_sales.description as description',
                'view_sales.user_creation__id as user_creation__id',
                'view_sales.user_creation__username as user_creation__username',
                'view_sales.user_creation__person__id as user_creation__person__id',
                'view_sales.user_creation__person__name as user_creation__person__name',
                'view_sales.user_creation__person__lastname as user_creation__person__lastname',
                'view_sales.creation_date as creation_date',
                'view_sales.update_user_id as update_user_id',
                'view_sales.update_date as update_date',
                'view_sales.status as status',

            ])
                ->distinct()
                ->leftJoin('view_details_sales', 'view_sales.id', '=', 'view_details_sales.sale_product_id')
                ->whereNotNull('view_sales.status')
                ->where('view_sales.branch__correlative', $branch)
                ->orderBy('view_sales.date_sale', 'desc')
                ->where('view_sales.date_sale', '>=', $dateStart)
                ->where('view_sales.date_sale', '<=', $dateEnd);

            if (isset($request->model)) {
                $query
                    ->where('view_details_sales.product__model__id', $request->model);
            }

            if ($request->filter != '*') {
                if ($request->filter == 'INSTALLATION') {
                    $query->where('type_operation__operation', 'INSTALACIÓN');
                } else if ($request->filter == 'FAULD') {
                    $query->where('type_operation__operation', 'AVERIA');
                } else if ($request->filter == 'TOWER') {
                    $query->where('type_operation__operation', 'TORRE');
                } else if ($request->filter == 'PLANT') {
                    $query->where('type_operation__operation', 'PLANTA');
                } else if ($request->filter == 'TECHNICAL') {
                    $query->where('type_operation__operation', 'PARA TECNICO');
                }
            }

            $salesJpa = $query->get();

            $sales = array();
            foreach ($salesJpa as $saleJpa) {
                $sale = gJSON::restore($saleJpa->toArray(), '__');
                $detailSalesJpa = ViewDetailsSales::select(['*'])->whereNotNull('status')->where('sale_product_id', $sale['id'])->get();
                $details = array();
                foreach ($detailSalesJpa as $detailJpa) {
                    $detail = gJSON::restore($detailJpa->toArray(), '__');
                    $details[] = $detail;
                }
                $sale['details'] = $details;
                $sales[] = $sale;
            }

            $count = 1;
            $view_details = '';
            foreach ($sales as $sale) {

                $instalation_details = "";
                $plant_details = "";
                $tower_details = "";
                $fauld_details = "";
                $parcel_details = "";

                if ($sale['type_operation']['operation'] == 'INSTALACIÓN' || $sale['type_operation']['operation'] == 'INSTALACION' || $sale['type_operation']['operation'] == 'AVERIA') {
                    $viewInstallations = viewInstallations::where('id', $sale['id'])->first();
                    $install = gJSON::restore($viewInstallations->toArray(), '__');

                    $instalation_details = "
                    <div>
                        <p>Cliente: <strong>{$install['client']['name']} {$install['client']['lastname']}</strong></p>
                        <p>Técnico: <strong>{$install['technical']['name']} {$install['technical']['lastname']}</strong></p>
                        <p>Fecha: <strong>{$install['date_sale']}</strong></p>
                    </div>
                    ";
                } else if ($sale['type_operation']['operation'] == 'PLANTA') {

                    $viewPlant = SalesProducts::select([
                        'sales_products.id as id',
                        'tech.id as technical__id',
                        'tech.name as technical__name',
                        'tech.lastname as technical__lastname',
                        'plant.id as plant__id',
                        'plant.name as plant__name',
                        'sales_products.date_sale as date_sale',
                        'sales_products.status_sale as status_sale',
                        'sales_products.description as description',
                        'sales_products.status as status',
                    ])
                        ->join('people as tech', 'sales_products._technical', 'tech.id')
                        ->join('plant', 'sales_products._plant', 'plant.id')
                        ->where('sales_products.id', $sale['id'])->first();

                    if ($viewPlant) {
                        $plant_details = "
                        <div>
                            <p>Tipo: LIQUIDACION</p>
                            <p>Proyecto:{$viewPlant->id}) <strong>{$viewPlant->plant__name}</strong></p>
                            <p>Técnico: <strong>{$viewPlant->technical__name} {$viewPlant->technical__lastname}</strong></p>
                            <p>Fecha: <strong>{$viewPlant->date_sale}</strong></p>
                        </div>
                        ";
                    } else {

                        $PlantJpa = Plant::find($sale['plant_id']);
                        if ($PlantJpa) {
                            $plant_details = "
                              <div>
                                    <p>Tipo: AGREGADO A STOCK</p>
                                    <p>Proyecto: {$PlantJpa->id}) <strong>{$PlantJpa->name}</strong></p>
                                    <p>Fecha: <strong>{$sale['date_sale']}</strong></p>
                              </div>
                              ";
                        }

                    }
                } else if ($sale['type_operation']['operation'] == 'TORRE') {

                    $saleProductJpa = SalesProducts::select([
                        'sales_products.id as id',
                        'tech.id as technical__id',
                        'tech.name as technical__name',
                        'tech.lastname as technical__lastname',
                        'towers.id as tower__id',
                        'towers.name as tower__name',
                        'sales_products.date_sale as date_sale',
                        'sales_products.status_sale as status_sale',
                        'sales_products.description as description',
                        'sales_products.status as status',
                    ])
                        ->join('people as tech', 'sales_products._technical', 'tech.id')
                        ->join('towers', 'sales_products._tower', 'towers.id')
                        ->where('sales_products.id', $sale['id'])->first();

                    $tower_details = "
                    <div>
                        <p>Torre: <strong>{$saleProductJpa->tower__name}</strong></p>
                        <p>Técnico: <strong>{$saleProductJpa->technical__name} {$saleProductJpa->technical__lastname}</strong></p>
                        <p>Fecha: <strong>{$saleProductJpa->date_sale}</strong></p>
                    </div>
                    ";
                } else if ($sale['type_operation']['operation'] == 'PARA TECNICO') {
                    $saleProductJpa = SalesProducts::select([
                        'sales_products.id as id',
                        'tech.id as technical__id',
                        'tech.name as technical__name',
                        'tech.lastname as technical__lastname',
                        'sales_products.date_sale as date_sale',
                        'sales_products.status_sale as status_sale',
                        'sales_products.description as description',
                        'sales_products.status as status',
                    ])
                        ->join('people as tech', 'sales_products._technical', 'tech.id')
                        ->where('sales_products.id', $sale['id'])->first();

                    $tower_details = "
                    <div>
                        <p>Técnico: <strong>{$saleProductJpa->technical__name} {$saleProductJpa->technical__lastname}</strong></p>
                        <p>Fecha: <strong>{$saleProductJpa->date_sale}</strong></p>
                    </div>
                    ";
                } else if ($sale['type_operation']['operation'] == 'ENCOMIENDA') {
                    $ParcelJpa = Parcel::where('_sale_product', $sale['id'])->first();
                    $branch_send = Branch::find($ParcelJpa->_branch_send);
                    $branch_received = Branch::find($ParcelJpa->_branch_destination);

                    $parcel_details = "
                    <div>
                        <p>Sucursal de envio: {$branch_send->name}</p>
                        <p>Sucursal de recepcion: {$branch_received->name}</p>
                        <p>Fecha de envio: {$ParcelJpa->date_send}</p>
                        <p>Fecha de recojo: {$ParcelJpa->date_entry}</p>
                    </div>
                    ";
                }

                $usuario = "
                <div>
                    <p><center><strong> {$sale['user_creation']['person']['name']} {$sale['user_creation']['person']['lastname']} </strong> </center></p>
                    <p><center>{$sale['date_sale']}</center></p>
                </div>
                ";

                $tipo_instalacion = isset($sale['type_intallation']) ? $sale['type_intallation'] : "<i>sin tipo</i>";
                $tipo_instalacion = str_replace('_', ' ', $tipo_instalacion);

                $datos = "
                    <div>
                        <p>Tipo operación <strong>{$sale['type_operation']['operation']}</strong></p>
                        <p>Tipo salida: <strong>{$tipo_instalacion}</strong></p>
                        <p>Descripción: <strong>{$sale['description']}</strong></p>
                    </div>
                ";

                $sumary .= "
                <tr style='font-size:12px;'>
                    <td>{$count}</td>
                    <td>{$usuario}</td>
                    <td>{$datos}</td>
                </tr>
                ";

                $view_details .= "
                <div style='margin-top:8px;'>
                    <p style='margin-buttom: 12px;'><strong>{$count}){$sale['type_operation']['operation']}</strong> - {$sale['user_creation']['person']['name']} {$sale['user_creation']['person']['lastname']} - {$sale['date_sale']} </p>
                    <div style='margin-buttom: 12px;margin-left:20px;'>
                        {$instalation_details}
                        {$plant_details}
                        {$tower_details}
                        {$fauld_details}
                        {$parcel_details}
                    </div>
                    <div style='display: flex; flex-wrap: wrap; justify-content: space-between;margin-top: 50px;'>";

                foreach ($sale['details'] as $detailJpa) {
                    $details_equipment = 'display:none;';
                    if ($detailJpa['product']['type'] == 'EQUIPO') {
                        $details_equipment = '';
                    }
                    $view_details .= "
                            <div style='border: 2px solid #bbc7d1; border-radius: 9px; width: 25%; display: inline-block; padding:8px; font-size:12px; margin-left:10px;'>
                                <center>
                                    <p><strong>{$detailJpa['product']['model']['model']}</strong></p>
                                    <img src='https://almacen.fastnetperu.com.pe/api/model/{$detailJpa['product']['model']['relative_id']}/mini' style='background-color: #38414a;object-fit: cover; object-position: center center; cursor: pointer; height:50px;margin-top:12px;'></img>
                                    <div style='{$details_equipment}'>
                                        <p>Mac: <strong>{$detailJpa['product']['mac']}</strong><p>
                                        <p>Serie: <strong>{$detailJpa['product']['serie']}</strong></p>
                                    </div>
                                    <div>
                                        <p style='font-size:20px; color:#2f6593'>Nu:{$detailJpa['mount_new']} | Se:{$detailJpa['mount_second']} | Ma:{$detailJpa['mount_ill_fated']}</p>
                                    </div>
                                </center>
                            </div>
                        ";
                }

                $view_details .= "
                            </div>
                        </div>
                    ";

                $count = $count + 1;
            }

            $template = str_replace(
                [
                    '{branch_interaction}',
                    '{issue_long_date}',
                    '{user_generate}',
                    '{date_start_str}',
                    '{date_end_str}',
                    '{summary}',
                    '{details}',
                ],
                [
                    $branch_->name,
                    gTrace::getDate('long'),
                    $user->person__name . ' ' . $user->person__lastname,
                    $request->date_start_str,
                    $request->date_end_str,
                    $sumary,
                    $view_details,
                ],
                $template
            );

            $pdf->loadHTML($template);
            $pdf->render();
            return $pdf->stream('Guia.pdf');
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

    public function generateReport(Request $request)
    {
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'plant_pending', 'read')) {
                throw new Exception('No tienes permisos para listar encomiedas creadas');
            }
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $pdf = new Dompdf($options);
            $template = file_get_contents('../storage/templates/reportSalesForModel.html');

            if (
                !isset($request->date_start) ||
                !isset($request->date_end)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();
            $user = ViewUsers::select([
                'id',
                'username',
                'person__name',
                'person__lastname',
            ])->where('id', $userid)->first();
            $sumary = '';

            $dateStart = date('Y-m-d', strtotime($request->date_start));
            $dateEnd = date('Y-m-d', strtotime($request->date_end));

            $query = ViewSales::select([
                'view_sales.id as id',
                'view_sales.client_id as client_id',
                'view_sales.technical_id as technical_id',
                'view_sales.branch__id as branch__id',
                'view_sales.branch__name as branch__name',
                'view_sales.branch__correlative	 as branch__correlative',
                'view_sales.type_operation__id as type_operation__id',
                'view_sales.type_products as type_products',
                'view_sales.type_operation__operation as type_operation__operation',
                'view_sales.tower_id as tower_id',
                'view_sales.plant_id as plant_id',
                'view_sales.room_id as room_id',
                'view_sales.type_intallation as type_intallation',
                'view_sales.date_sale as date_sale',
                'view_sales.issue_date as issue_date',
                'view_sales.issue_user_id as issue_user_id',
                'view_sales.status_sale as status_sale',
                'view_sales.description as description',
                'view_sales.user_creation__id as user_creation__id',
                'view_sales.user_creation__username as user_creation__username',
                'view_sales.user_creation__person__id as user_creation__person__id',
                'view_sales.user_creation__person__name as user_creation__person__name',
                'view_sales.user_creation__person__lastname as user_creation__person__lastname',
                'view_sales.creation_date as creation_date',
                'view_sales.update_user_id as update_user_id',
                'view_sales.update_date as update_date',
                'view_sales.status as status',

            ])
                ->distinct()
                ->leftJoin('view_details_sales', 'view_sales.id', '=', 'view_details_sales.sale_product_id')
                ->whereNotNull('view_sales.status')
                ->where('view_sales.branch__correlative', $branch)
                ->whereNot(function ($q1) {
                    $q1->where('view_sales.type_intallation', '=', 'AGREGADO_A_STOCK')
                        ->where('view_sales.type_products', '=', 'PRODUCTS');
                })
                ->whereNot('view_sales.type_intallation', '=', 'SACADO_DE_STOCK')
                ->orderBy('view_sales.date_sale', 'desc')
                ->where('view_sales.date_sale', '>=', $dateStart)
                ->where('view_sales.date_sale', '<=', $dateEnd);

            if (isset($request->model)) {
                $query
                    ->where('view_details_sales.product__model__id', $request->model);
            }

            if ($request->filter != '*') {
                if ($request->filter == 'INSTALLATION') {
                    $query->where('view_sales.type_operation__operation', 'INSTALACIÓN');
                } else if ($request->filter == 'FAULD') {
                    $query->where('view_sales.type_operation__operation', 'AVERIA');
                } else if ($request->filter == 'TOWER') {
                    $query->where('view_sales.type_operation__operation', 'TORRE');
                } else if ($request->filter == 'PLANT') {
                    $query->where('view_sales.type_operation__operation', 'PLANTA');
                } else if ($request->filter == 'TECHNICAL') {
                    $query->where('view_sales.type_operation__operation', 'PARA TECNICO')
                    // ->whereNot(function ($q1) {
                    //     $q1->where('view_sales.type_intallation', '=', 'AGREGADO_A_STOCK')
                    //         ->where('view_sales.type_products', '=', 'PRODUCTS');
                    // })
                    // ->whereNot(function ($q1) {
                    //     $q1->where('view_sales.type_intallation', '=', 'AGREGADO_A_STOCK')
                    //         ->where('view_sales.type_products', '=', 'PRODUCTS');
                    // })
                    ;
                }
            }



            $salesJpa = $query->get();
            $detailsJpa = [];

            $sales = array();
            foreach ($salesJpa as $saleJpa) {
                $sale = gJSON::restore($saleJpa->toArray(), '__');
                $detailSalesJpa = ViewDetailsSales::select(
                    [
                        'view_details_sales.id as id',
                        'view_details_sales.product__id as product__id',
                        'view_details_sales.product__type as product__type',
                        'view_details_sales.product__model__id as product__model__id',
                        'view_details_sales.product__model__model as product__model__model',
                        'view_details_sales.product__model__relative_id as product__model__relative_id',
                        'view_details_sales.product__model__unity_id as product__model__unity_id',
                        'unities.id as product__model__unity__id',
                        'unities.name as product__model__unity__name',
                        'view_details_sales.mount_new as mount_new',
                        'view_details_sales.mount_second as mount_second',
                        'view_details_sales.mount_ill_fated as mount_ill_fated',
                    ]
                )
                    ->join('unities', 'view_details_sales.product__model__unity_id', 'unities.id')
                    ->whereNotNull('view_details_sales.status')->where('view_details_sales.sale_product_id', $sale['id'])->get();
                $details = array();
                foreach ($detailSalesJpa as $detailJpa) {
                    $detail = gJSON::restore($detailJpa->toArray(), '__');
                    $details[] = $detail;
                    $detailsJpa[] = $detail;
                }
                $sale['details'] = $details;
                $sales[] = $sale;
            }

            $models = array();
            foreach ($detailsJpa as $product) {
                if ($product != []) {

                    $model = $relativeId = $unity = "";
                    if ($product['product']['type'] === "EQUIPO") {
                        $model = $product['product']['model']['model'];
                        $relativeId = $product['product']['model']['relative_id'];
                        $unity = $product['product']['model']['unity']['name'];
                    } else {
                        $model = $product['product']['model']['model'];
                        $relativeId = $product['product']['model']['relative_id'];
                        $unity = $product['product']['model']['unity']['name'];
                    }
                    $mount_new = $product['mount_new'];
                    $mount_second = $product['mount_second'];
                    $mount_ill_fated = $product['mount_ill_fated'];
                    if (isset($models[$model])) {
                        $models[$model]['mount_new'] += $mount_new;
                        $models[$model]['mount_second'] += $mount_second;
                        $models[$model]['mount_ill_fated'] += $mount_ill_fated;
                    } else {
                        $models[$model] = array(
                            'model' => $model,
                            'mount_new' => $mount_new,
                            'mount_second' => $mount_second,
                            'mount_ill_fated' => $mount_ill_fated,
                            'relative_id' => $relativeId,
                            'unity' => $unity);
                    }
                }
            }

            $count = 1;
            $products = array_values($models);

            foreach ($products as $detail) {

                $sumary .= "
                <tr>
                    <td><center style='font-size:15px;'>{$count}</center></td>
                    <td><center style='font-size:12px;'>{$detail['model']}</center></td>
                    <td><center style='font-size:15px;'>{$detail['mount_new']}</center></td>
                    <td><center style='font-size:15px;'>{$detail['mount_second']}</center></td>
                    <td><center style='font-size:15px;'>{$detail['mount_ill_fated']}</center></td>
                    <td><center style='font-size:15px;'>{$detail['unity']}</center></td>
                </tr>
                ";

                $count += 1;
            }

            $template = str_replace(
                [
                    '{branch_onteraction}',
                    '{issue_long_date}',
                    '{user}',
                    '{date_start}',
                    '{date_end}',
                    '{summary}',
                ],
                [
                    $branch_->name,
                    gTrace::getDate('long'),
                    $user->person__name . ' ' . $user->person__lastname,
                    $request->date_start_str,
                    $request->date_end_str,
                    $sumary,
                ],
                $template
            );

            // $response = new Response();
            // $response->setStatus(200);
            // $response->setMessage('Operacion correcta');
            // $response->setData($products);
            // return response(
            //     $response->toArray(),
            //     $response->getStatus()
            // );

            $pdf->loadHTML($template);
            $pdf->render();
            return $pdf->stream('Guia.pdf');
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
