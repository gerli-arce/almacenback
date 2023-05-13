<?php

namespace App\Http\Controllers;


use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\DetailSale;
use App\Models\ViewDetailSale;
use App\Models\Product;
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

            $salesJpa = ViewSales::select([
                '*',
            ])
                ->orderBy('date_sale', 'desc')
                ->whereNotNUll('status')
                ->where('branch__correlative', $branch)
                ->where('date_sale', '>=', $dateStart)
                ->where('date_sale', '<=', $dateEnd)->get();


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
                    <p style='margin-buttom: 12px;'>{$count}) <strong>{$sale['type_operation']['operation']} - {$sale['user_creation']['person']['name']} {$sale['user_creation']['person']['lastname']} - {$sale['date_sale']}</strong> </p>
                    <div style='display: flex; flex-wrap: wrap; justify-content: space-between;margin-top: 50px;'>";

                foreach ($sale['details'] as $detailJpa) {
                    $view_details .= "
                            <div style='border: 1px solid black; width: 30%; display: inline-block; padding:8px;'>
                            <center>
                                <p><strong>{$detailJpa['product']['model']['model']}</strong></p>
                                <img src='https://almacendev.fastnetperu.com.pe/api/model/{$detailJpa['product']['model']['relative_id']}/mini' style='background-color: #38414a;object-fit: cover; object-position: center center; cursor: pointer; height:80px;margin:0px;'></img>
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
