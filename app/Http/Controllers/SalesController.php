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
use App\Models\Stock;
use App\Models\viewInstallations;
use Exception;

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

            $query = viewInstallations::select([
                '*',
            ])
                ->orderBy($request->order['column'], $request->order['dir'])
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
                $detailSalesJpa = ViewDetailSale::select(['*'])->whereNotNull('status')->where('sale_product__id', $sale['id'])->get();
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
}
