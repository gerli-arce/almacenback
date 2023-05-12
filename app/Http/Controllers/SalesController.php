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

    public function generateReportBydate(Request $request){
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


            // if (
            //     !isset($request->id)
            // ) {
            //     throw new Exception("Error: No deje campos vacíos");
            // }
            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();
            $user = ViewUsers::select([
                'id',
                'username',
                'person__name',
                'person__lastname'
            ])->where('id', $userid)->first();
            $sumary = '';
            // $detailSaleJpa = DetailSale::select([
            //     'detail_sales.id as id',
            //     'products.id AS product__id',
            //     'products.type AS product__type',
            //     'models.id AS product__model__id',
            //     'models.model AS product__model__model',
            //     'models.relative_id AS product__model__relative_id',
            //     'unities.id as product__model__unity__id',
            //     'unities.name as product__model__unity__name',
            //     'products.relative_id AS product__relative_id',
            //     'products.mac AS product__mac',
            //     'products.serie AS product__serie',
            //     'products.price_sale AS product__price_sale',
            //     'products.currency AS product__currency',
            //     'products.num_guia AS product__num_guia',
            //     'products.condition_product AS product__condition_product',
            //     'products.disponibility AS product__disponibility',
            //     'products.product_status AS product__product_status',
            //     'branches.id AS sale_product__branch__id',
            //     'branches.name AS sale_product__branch__name',
            //     'branches.correlative AS sale_product__branch__correlative',
            //     'detail_sales.mount as mount',
            //     'detail_sales.description as description',
            //     'detail_sales._sales_product as _sales_product',
            //     'detail_sales.status as status',
            // ])
            //     ->join('products', 'detail_sales._product', 'products.id')
            //     ->join('models', 'products._model', 'models.id')
            //     ->join('unities', 'models._unity', 'unities.id')
            //     ->join('sales_products', 'detail_sales._sales_product', 'sales_products.id')
            //     ->join('branches', 'sales_products._branch', 'branches.id')
            //     ->whereNotNull('detail_sales.status')
            //     ->where('_sales_product', $request->id)
            //     ->get();
            // $details = array();
            // foreach ($detailSaleJpa as $detailJpa) {
            //     $detail = gJSON::restore($detailJpa->toArray(), '__');
            //     $details[] = $detail;
            // }
            // $models = array();
            // foreach ($details as $product) {
            //     $model = $relativeId = $unity = "";
            //     if ($product['product']['type'] === "EQUIPO") {
            //         $model = $product['product']['model']['model'];
            //         $relativeId = $product['product']['model']['relative_id'];
            //         $unity =  $product['product']['model']['unity']['name'];
            //     } else {
            //         $model = $product['product']['model']['model'];
            //         $relativeId = $product['product']['model']['relative_id'];
            //         $unity =  $product['product']['model']['unity']['name'];
            //     }
            //     $mount = $product['mount'];
            //     if (isset($models[$model])) {
            //         $models[$model]['mount'] += $mount;
            //     } else {
            //         $models[$model] = array('model' => $model, 'mount' => $mount, 'relative_id' => $relativeId, 'unity' => $unity);
            //     }
            // }
            // $count = 1;
            // $products = array_values($models);
            // foreach ($products as $detail) {
            //     $sumary .= "
            //     <tr>
            //         <td><center style='font-size:12px;'>{$count}</center></td>
            //         <td><center style='font-size:12px;'>{$detail['mount']}</center></td>
            //         <td><center style='font-size:12px;'>{$detail['unity']}</center></td>
            //         <td><center style='font-size:12px;'>{$detail['model']}</center></td>
            //     </tr>
            //     ";
            //     $count = $count + 1;
            // }
            $template = str_replace(
                [
                    '{branch_interaction}',
                    '{issue_long_date}',
                    '{user_generate}',
                    '{date_start_str}',
                    '{date_end_str}',
                    '{summary}',
                ],
                [
                    $branch_->name,
                    gTrace::getDate('long'),
                    $user->person__name.' '.$user->person__lastname,
                    $request->date_start_str,
                    $request->date_end_str,
                    $sumary,
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
