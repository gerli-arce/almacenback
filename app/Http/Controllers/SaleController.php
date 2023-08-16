<?php

namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\DetailSale;
use App\Models\Product;
use App\Models\ViewDetailsSales;
use App\Models\Sale;
use App\Models\Response;
use App\Models\People;
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

            if (!gValidate::check($role->permissions, $branch, 'sales', 'create')) {
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
            $salesProduct->mount_dues = $request->mount_dues;
            if (isset($request->description)) {
                $salesProduct->description = $request->description;
            }

            $salesProduct->_creation_user = $userid;
            $salesProduct->creation_date = gTrace::getDate('mysql');
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');
            $salesProduct->status = "1";
            $salesProduct->save();

            $PeopleJpa = People::where('id', $request->_client)->first();

            if (isset($request->data)) {
                foreach ($request->data as $product) {
                    $productJpa = Product::find($product['product']['id']);
                    $stock = Stock::where('_model', $productJpa->_model)
                        ->where('_branch', $branch_->id)
                        ->first();
                    if ($product['product']['type'] == "MATERIAL") {
                        $stock->mount_new = $stock->mount_new - $product['mount_new'];
                        $stock->mount_second = $stock->mount_second - $product['mount_second'];
                        $stock->mount_ill_fated = $stock->mount_ill_fated - $product['mount_ill_fated'];
                        $productJpa->mount = $stock->mount_new + $stock->mount_second;
                    } else {
                        if ($productJpa->product_status == "NUEVO") {
                            $stock->mount_new = intval($stock->mount_new) - 1;
                        } else if ($productJpa->product_status == "SEMINUEVO") {
                            $stock->mount_second = intval($stock->mount_second) - 1;
                        }
                        $productJpa->disponibility = 'INSTALACION: '.$PeopleJpa->name.' '.$PeopleJpa->lastname;
                    }
                    $stock->save();
                    $productJpa->save();

                    $detailSale = new DetailSale();
                    $detailSale->_product = $productJpa->id;
                    $detailSale->mount_new = $product['mount_new'];
                    $detailSale->mount_second = $product['mount_second'];
                    $detailSale->mount_ill_fated = $product['mount_ill_fated'];
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

    public function paginate(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'sales', 'read')) {
                throw new Exception('No tienes permisos para listar las ventas');
            }

            $query = Sale::select([
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

                if ($column == 'client__name' || $column == '*') {
                    $q->orWhere('client__name', $type, $value);
                }
                if ($column == 'client__lastname' || $column == '*') {
                    $q->orWhere('client__lastname', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }
            })
                ->where('branch__correlative', $branch);

            $iTotalDisplayRecords = $query->count();

            $salesJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $sales = array();
            foreach ($salesJpa as $pending) {
                $saleJpa = gJSON::restore($pending->toArray(), '__');
                $sales[] = $saleJpa;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(Sale::where('branch__correlative', $branch)->count());
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

    public function getSaleDetails(Request $request, $id)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'sales', 'read')) {
                throw new Exception('No tienes permisos para ver detalles de venta');
            }

            if (
                !isset($id)
            ) {
                throw new Exception('Error: No deje campos vacíos');
            }

            // $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $viewDetailsSalesJpa = ViewDetailsSales::where('sale_product_id', $id)->whereNotNUll('status')->get();


            $details = array();
            foreach ($viewDetailsSalesJpa as $detailsJpa) {
                $detail = gJSON::restore($detailsJpa->toArray(), '__');
                $details[] = $detail;
            }

            $response->setData($details);
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

    public function update(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);

            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'sales', 'update')) {
                throw new Exception('No tienes permisos para actualizar ventas');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception('Error: No deje campos vacíos');
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $salesProduct = SalesProducts::find($request->id);

            if (isset($request->_client)) {
                $salesProduct->_client = $request->_client;
            }
            if (isset($request->_technical)) {
                $salesProduct->_technical = $request->_technical;
            }
            if (isset($request->date_sale)) {
                $salesProduct->date_sale = $request->date_sale;
            }
            if (isset($request->price_all)) {
                $salesProduct->price_all = $request->price_all;
            }
            if (isset($request->discount)) {
                $salesProduct->discount = $request->discount;
            }
            if (isset($request->price_installation)) {
                $salesProduct->price_installation = $request->price_installation;
            }
            if (isset($request->mount_dues)) {
                $salesProduct->mount_dues = $request->mount_dues;
            }

            
            if (isset($request->type_intallation)) {
                $salesProduct->type_intallation = $request->type_intallation;
            }

            $salesProduct->description = $request->description;
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');

            if (isset($request->data)) {
                foreach ($request->data as $product) {
                    if (isset($product['id'])) {
                        $productJpa = Product::find($product['product']['id']);
                        $detailSale = DetailSale::find($product['id']);
                        if ($product['product']['type'] == "MATERIAL") {

                            $stock = Stock::where('_model', $productJpa->_model)
                                ->where('_branch', $branch_->id)
                                ->first();

                            if (intval($detailSale->mount_new) != intval($product['mount_new'])) {
                                if (intval($detailSale->mount_new) > intval($product['mount_new'])) {
                                    $mount_dif = intval($detailSale->mount_new) - intval($product['mount_new']);
                                    $stock->mount_new = $stock->mount_new + $mount_dif;
                                } else if (intval($detailSale->mount_new) < intval($product['mount_new'])) {
                                    $mount_dif = intval($product['mount_new']) - intval($detailSale->mount_new);
                                    $stock->mount_new = $stock->mount_new -  $mount_dif;
                                }
                            }

                            if (intval($detailSale->mount_second) != intval($product['mount_second'])) {
                                if (intval($detailSale->mount_second) > intval($product['mount_second'])) {
                                    $mount_dif = intval($detailSale->mount_second) - intval($product['mount_second']);
                                    $stock->mount_second = $stock->mount_second + $mount_dif;
                                } else if (intval($detailSale->mount_second) < intval($product['mount_second'])) {
                                    $mount_dif = intval($product['mount_second']) - intval($detailSale->mount_second);
                                    $stock->mount_second = $stock->mount_second -  $mount_dif;
                                }
                            }

                            if (intval($detailSale->mount_ill_fated) != intval($product['mount_ill_fated'])) {
                                if (intval($detailSale->mount_ill_fated) > intval($product['mount_ill_fated'])) {
                                    $mount_dif = intval($detailSale->mount_ill_fated) - intval($product['mount_ill_fated']);
                                    $stock->mount_ill_fated = $stock->mount_ill_fated + $mount_dif;
                                } else if (intval($detailSale->mount_ill_fated) < intval($product['mount_ill_fated'])) {
                                    $mount_dif = intval($product['mount_ill_fated']) - intval($detailSale->mount_ill_fated);
                                    $stock->mount_ill_fated = $stock->mount_ill_fated -  $mount_dif;
                                }
                            }


                            $productJpa->mount = $stock->mount_new + $stock->mount_second;
                            $stock->save();
                        }
                        $detailSale->mount_new = $product['mount_new'];
                        $detailSale->mount_second = $product['mount_second'];
                        $detailSale->mount_ill_fated = $product['mount_ill_fated'];
                        $productJpa->save();
                        $detailSale->save();
                    } else {
                        $productJpa = Product::find($product['product']['id']);
                        $stock = Stock::where('_model', $productJpa->_model)
                            ->where('_branch', $branch_->id)
                            ->first();

                        if ($product['product']['type'] == "MATERIAL") {
                            $stock->mount_new = $stock->mount_new - $product['mount_new'];
                            $stock->mount_second = $stock->mount_second - $product['mount_second'];
                            $stock->mount_ill_fated = $stock->mount_ill_fated - $product['mount_ill_fated'];
                            $productJpa->mount = $stock->mount_new+$stock->mount_second;
                        } else {
                            $productJpa->disponibility = "VENDIDO";
                            if ($productJpa->product_status == "NUEVO") {
                                $stock->mount_new = $stock->mount_new - 1;
                            } else if ($productJpa->product_status == "SEMINUEVO") {
                                $stock->mount_second = $stock->mount_second - 1;
                            }
                        }

                        $stock->save();
                        $productJpa->save();

                        $detailSale = new DetailSale();
                        $detailSale->_product = $productJpa->id;
                        $detailSale->mount = $product['mount'];
                        $detailSale->_sales_product = $salesProduct->id;
                        $detailSale->status = '1';
                        $detailSale->save();
                    }
                }
            }
            $salesProduct->save();
            $response->setStatus(200);
            $response->setMessage('Instalación atualizada correctamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . ' ln:' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function cancelUseProduct(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);

            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'sales', 'update')) {
                throw new Exception('No tienes permisos para actualizar');
            }

            if (!isset($request->id)) {
                throw new Exception('Error: No deje campos vacíos');
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $salesProduct = SalesProducts::find($request->sale_product_id);
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');

            $detailSale = DetailSale::find($request->id);
            $detailSale->status = null;

            $productJpa = Product::find($request->product['id']);

            $stock = Stock::where('_model', $productJpa->_model)
                ->where('_branch', $branch_->id)
                ->first();
            if ($productJpa->type == "MATERIAL") {
                $stock->mount_new = $stock->mount_new + $request->mount_new;
                $stock->mount_second = $stock->mount_second + $request->mount_second;
                $stock->mount_ill_fated = $stock->mount_ill_fated + $request->mount_ill_fated;
            } else if ($productJpa->type == "EQUIPO") {
                $productJpa->disponibility = "DISPONIBLE";
                $stock = Stock::where('_model', $productJpa->_model)
                    ->where('_branch', $branch_->id)
                    ->first();
                if ($productJpa->product_status == "NUEVO") {
                    $stock->mount_new = intval($stock->mount_new) + 1;
                } else if ($productJpa->product_status == "SEMINUEVO") {
                    $stock->mount_second = intval($stock->mount_second) + 1;
                }
                $productJpa->save();
            }

            $productJpa->mount =  $stock->mount_new +  $stock->mount_second;

            $stock->save();
            $detailSale->save();
            $salesProduct->save();
            $productJpa->save();

            $response->setStatus(200);
            $response->setMessage('Liquidación atualizada correctamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . ' ln:' . $th->getLine());
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

            if (!gValidate::check($role->permissions, $branch, 'sales', 'delete')) {
                throw new Exception('No tienes permisos para eliminar ventas');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception('Error: No deje campos vacíos');
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $salesProduct = SalesProducts::find($request->id);
            $salesProduct->_update_user = $userid;
            $salesProduct->update_date = gTrace::getDate('mysql');

            $detailsSalesJpa = DetailSale::where('_sales_product', $salesProduct->id)
                ->get();
            foreach ($detailsSalesJpa as $detail) {
                $detailSale = DetailSale::find($detail['id']);
                $detailSale->status = null;
                $productJpa = Product::find($detail['_product']);
                $productJpa->disponibility = "DISPONIBLE";
                $stock = Stock::where('_model', $productJpa->_model)
                    ->where('_branch', $branch_->id)
                    ->first();
                 if ($productJpa->type == "MATERIAL") {
                    $stock->mount_new = $stock->mount_new + $detail['mount_new'];
                    $stock->mount_second = $stock->mount_second + $detail['mount_second'];
                    $stock->mount_ill_fated = $stock->mount_ill_fated + $detail['mount_ill_fated'];
                   
                }else{
                    if ($productJpa->product_status == 'NUEVO') {
                        $stock->mount_new = $stock->mount_new + 1;
                    }else if($productJpa->product_status == 'SEMINUEVO'){
                        $stock->mount_second = $stock->mount_second + 1;
                    }
                }
                $stock->save();
                $productJpa->save();
                $detailSale->save();
            }

          
            $salesProduct->save();
            $response->setStatus(200);
            $response->setMessage('Venta eliminada correactamente');
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . ' ln:' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }
}
