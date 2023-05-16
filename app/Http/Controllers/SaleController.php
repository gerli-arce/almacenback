<?php

namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\DetailSale;
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
use Dompdf\Dompdf;
use Dompdf\Options;

class SaleController extends Controller
{
    
    public function store(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'sale', 'create')) {
                throw new Exception('No tienes permisos para agregar ventas');
            }

            if (
                !isset($request->_type_operation) ||
                !isset($request->_client) ||
                !isset($request->price_all) ||
                !isset($request->price_sale) ||
                !isset($request->date_sale)
            ) {
                throw new Exception('Error: No deje campos vacíos');
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $salesProduct = new SalesProducts();
            $salesProduct->_client = $request->_client;
            $salesProduct->_branch = $branch_->id;
            $salesProduct->_type_operation = $request->_type_operation;
            $salesProduct->type_intallation = 'VENTA';
            $salesProduct->date_sale = $request->date_sale;
            $salesProduct->status_sale = "CULMINADA";
            $salesProduct->price_all = $request->price_all;
            $salesProduct->price_installation = $request->price_sale;
            $salesProduct->discount = $request->discount;
            $salesProduct->type_pay = "CONTADO";
            $salesProduct->mount_dues = 1;
            if (isset($request->description)) {
                $salesProduct->description = $request->description;
            }

            $salesProduct->_creation_user = $userid;
            $salesProduct->creation_date = gTrace::getDate('mysql');
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');
            $salesProduct->status = "1";
            $salesProduct->save();

            if (isset($request->data)) {
                foreach ($request->data as $product) {
                    $productJpa = Product::find($product['product']['id']);
                    $stock = Stock::where('_model', $productJpa->_model)
                            ->where('_branch', $branch_->id)
                            ->first();
                    if ($product['product']['type'] == "MATERIAL") {
                        $productJpa->mount = $productJpa->mount - $product['mount'];
                        $stock->mount_new = $productJpa->mount;
                    } else {
                        if ($productJpa->product_status == "NUEVO") {
                            $stock->mount_new = intval($stock->mount_new) - 1;
                        } else if ($productJpa->product_status == "SEMINUEVO") {
                            $stock->mount_second = intval($stock->mount_second) - 1;
                        }
                    }
                    $stock->save();
                    $productJpa->save();

                    $detailSale = new DetailSale();
                    $detailSale->_product = $productJpa->id;
                    $detailSale->mount = $product['mount'];
                    // $detailSale->description = $product['description'];
                    $detailSale->_sales_product = $salesProduct->id;
                    $detailSale->status = '1';
                    $detailSale->save();
                }
            }
            $response->setStatus(200);
            $response->setMessage('Venta agregada correctamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . ', ln:' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }
}
