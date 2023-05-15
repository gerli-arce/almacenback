<?php

namespace App\Http\Controllers;


use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\DetailSale;
use App\Models\ViewDetailSale;
use App\Models\ViewPlant;
use App\Models\ProductByTechnical;
use App\Models\RecordProductByTechnical;
use App\Models\Response;
use App\Models\SalesProducts;
use App\Models\ViewDetailsSales;
use App\Models\ViewSales;
use App\Models\ViewUsers;
use App\Models\viewInstallations;
use Exception;

use Dompdf\Dompdf;
use Dompdf\Options;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

            $query = ViewSales::select([
                '*',
            ])
                ->orderBy($request->order['column'], $request->order['dir'])
                ->whereNotNUll('status')
                ->where('branch__correlative', $branch);


            if (isset($request->search['date_start']) || isset($request->search['date_end'])) {
                $dateStart = date('Y-m-d', strtotime($request->search['date_start']));
                $dateEnd = date('Y-m-d', strtotime($request->search['date_end']));

                $query->where('date_sale', '>=', $dateStart)
                    ->where('date_sale', '<=', $dateEnd);
            }

            if ($request->search['column'] != '*') {
                if($request->search['column'] == 'INSTALLATION'){
                    $query->where('type_operation__operation', 'INSTALACIÓN');
                }else if($request->search['column'] == 'FAULD'){
                    $query->where('type_operation__operation', 'AVERIA');
                }else if($request->search['column'] == 'TOWER'){
                    $query->where('type_operation__operation', 'TORRE');
                }else if($request->search['column'] == 'PLANT'){
                    $query->where('type_operation__operation', 'PLANTA');
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
                    $detail =  gJSON::restore($detailJpa->toArray(), '__');
                    $details[] = $detail;
                }
                $sale['details'] = $details;
                $sales[] = $sale;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(Viewinstallations::where('branch__correlative', $branch)->count());
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
                'person__lastname'
            ])->where('id', $userid)->first();
            $sumary = '';

            $dateStart = date('Y-m-d', strtotime($request->date_start));
            $dateEnd = date('Y-m-d', strtotime($request->date_end));

            $query = ViewSales::select([
                '*',
            ])
                ->orderBy('date_sale', 'desc')
                ->whereNotNUll('status')
                ->where('branch__correlative', $branch)
                ->where('date_sale', '>=', $dateStart)
                ->where('date_sale', '<=', $dateEnd);

            if ($request->filter != '*') {
                if($request->filter == 'INSTALLATION'){
                    $query->where('type_operation__operation', 'INSTALACIÓN');
                }else if($request->filter == 'FAULD'){
                    $query->where('type_operation__operation', 'AVERIA');
                }else if($request->filter == 'TOWER'){
                    $query->where('type_operation__operation', 'TORRE');
                }else if($request->filter == 'PLANT'){
                    $query->where('type_operation__operation', 'PLANTA');
                }
            }


            $salesJpa = $query->get();


            $sales = array();
            foreach ($salesJpa as $saleJpa) {
                $sale = gJSON::restore($saleJpa->toArray(), '__');
                $detailSalesJpa = ViewDetailsSales::select(['*'])->whereNotNull('status')->where('sale_product_id', $sale['id'])->get();
                $details = array();
                foreach ($detailSalesJpa as $detailJpa) {
                    $detail =  gJSON::restore($detailJpa->toArray(), '__');
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


                if ($sale['type_operation']['operation'] == 'INSTALACIÓN' || $sale['type_operation']['operation'] == 'AVERIA') {
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
                            <p>Torre: <strong>{$viewPlant->plant__name}</strong></p>
                            <p>Técnico: <strong>{$viewPlant->technical__name} {$viewPlant->technical__lastname}</strong></p>
                            <p>Fecha: <strong>{$viewPlant->date_sale}</strong></p>
                        </div>
                        ";
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
                }

                $usuario = "
                <div>
                    <p style='color:#71b6f9;'>{$sale['user_creation']['username']}</p>
                    <p><strong> {$sale['user_creation']['person']['name']} {$sale['user_creation']['person']['lastname']} </strong> </p>
                    <p>{$sale['date_sale']}</p>
                </div>
                ";

                $tipo_instalacion = isset($sale['type_intallation']) ? $sale['type_intallation'] : "<i>sin tipo</i>";

                $datos = "
                    <div>
                        <p>Tipo operación <strong>{$sale['type_operation']['operation']}</strong></p>
                        <p>Tipo salida: <strong>{$tipo_instalacion}</strong></p>
                        <p>Descripción: <strong>{$sale['description']}</strong></p>
                    </div>
                ";


                $sumary .= "
                <tr>
                    <td>{$count}</td>
                    <td>{$usuario}</td>
                    <td>{$datos}</td>
                </tr>
                ";

                $view_details .= "
                <div style='margin-top:8px;'>
                    <p style='margin-buttom: 12px;'>{$count}) <strong>{$sale['type_operation']['operation']}</strong> - {$sale['user_creation']['person']['name']} {$sale['user_creation']['person']['lastname']} - {$sale['date_sale']} </p>
                    <div style='margin-buttom: 12px;margin-left:20px;'>
                        {$instalation_details}
                        {$plant_details}
                        {$tower_details}
                        {$fauld_details}
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
                                    <img src='https://almacendev.fastnetperu.com.pe/api/model/{$detailJpa['product']['model']['relative_id']}/mini' style='background-color: #38414a;object-fit: cover; object-position: center center; cursor: pointer; height:50px;margin:0px;'></img>
                                    <div style='{$details_equipment}'>
                                        <p>Mac: <strong>{$detailJpa['product']['mac']}</strong><p>
                                        <p>Serie: <strong>{$detailJpa['product']['serie']}</strong></p>                                 
                                    </div>
                                    <p style='font-size:20px; color:#2f6593'>{$detailJpa['mount']}</p>
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
}
