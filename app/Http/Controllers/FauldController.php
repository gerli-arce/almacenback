<?php

namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\DetailSale;
use App\Models\People;
use App\Models\Product;
use App\Models\ProductByTechnical;
use App\Models\Response;
use App\Models\SalesProducts;
use App\Models\Stock;
use App\Models\viewInstallations;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FauldController extends Controller
{

    public function registerFauld(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'faulds_pending', 'create')) {
                throw new Exception('No tienes permisos para agregar averias');
            }

            if (
                !isset($request->_client) ||
                !isset($request->type_intallation) ||
                !isset($request->price_installation) ||
                !isset($request->_technical) ||
                !isset($request->_type_operation) ||
                // !isset($request->price_all) ||
                !isset($request->type_pay) ||
                !isset($request->mount_dues) ||
                !isset($request->date_sale)
            ) {
                throw new Exception('Error: No deje campos vacíos');
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $salesProduct = new SalesProducts();
            $salesProduct->_client = $request->_client;
            $salesProduct->_technical = $request->_technical;
            $salesProduct->_branch = $branch_->id;
            $salesProduct->_type_operation = $request->_type_operation;
            $salesProduct->type_intallation = $request->type_intallation;
            $salesProduct->date_sale = $request->date_sale;
            $salesProduct->status_sale = $request->status_sale;
            // $salesProduct->price_all = $request->price_all;
            $salesProduct->_issue_user = $userid;
            $salesProduct->price_installation = $request->price_installation;
            $salesProduct->type_pay = $request->type_pay;
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

            if (isset($request->data)) {
                foreach ($request->data as $product) {
                    $productJpa = Product::find($product['product']['id']);

                    $productByTechnicalJpa = ProductByTechnical::where('_technical', $request->_technical)
                        ->whereNotNull('status')
                        ->whereNot('type', 'LEND')
                        ->where('_model', $product['product']['model']['id'])->first();

                    if ($product['product']['type'] == "MATERIAL") {
                        if ($product['mount_new'] > 0) {
                            $productByTechnicalJpa->mount_new = $productByTechnicalJpa->mount_new - $product['mount_new'];
                        }

                        if ($product['mount_second'] > 0) {
                            $productByTechnicalJpa->mount_second = $productByTechnicalJpa->mount_second - $product['mount_second'];
                        }

                        if ($product['mount_ill_fated'] > 0) {
                            $productByTechnicalJpa->mount_ill_fated = $productByTechnicalJpa->mount_ill_fated - $product['mount_ill_fated'];
                        }

                        $productByTechnicalJpa->save();
                    } else {

                        $stock = Stock::where('_model', $productJpa->_model)
                            ->where('_branch', $branch_->id)
                            ->first();
                        if ($productJpa->product_status == "NUEVO") {
                            $stock->mount_new = intval($stock->mount_new) - 1;
                        } else if ($productJpa->product_status == "SEMINUEVO") {
                            $stock->mount_second = intval($stock->mount_second) - 1;
                        }

                        $stock->save();
                        $productJpa->save();
                    }

                    $detailSale = new DetailSale();
                    $detailSale->_product = $productJpa->id;
                    $detailSale->mount_new = $product['mount_new'];
                    $detailSale->mount_second = $product['mount_second'];
                    $detailSale->mount_ill_fated = $product['mount_ill_fated'];
                    $detailSale->description = $product['description'];
                    $detailSale->_sales_product = $salesProduct->id;
                    $detailSale->status = '1';
                    $detailSale->save();
                }
            }
            $response->setStatus(200);
            $response->setMessage('Avería agregada correctamente');
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

    public function paginateFauldsPending(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'faulds_pending', 'read')) {
                throw new Exception('No tienes permisos para listar las instataciónes pendientes');
            }

            $query = viewInstallations::select([
                '*',
            ])
                ->orderBy($request->order['column'], $request->order['dir'])
                ->orderBy('id', 'desc');

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;

                if ($column == 'technical__name' || $column == '*') {
                    $q->where('technical__name', $type, $value);
                }
                if ($column == 'client__name' || $column == '*') {
                    $q->where('client__name', $type, $value);
                }
                if ($column == 'user_creation__username' || $column == '*') {
                    $q->orWhere('user_creation__username', $type, $value);
                }
                if ($column == 'date_sale' || $column == '*') {
                    $q->orWhere('date_sale', $type, $value);
                }
            })
                ->where('status_sale', 'PENDIENTE')
                ->where('type_operation__operation', 'AVERIA')
                ->where('branch__correlative', $branch);
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
            $response->setITotalRecords(Viewinstallations::where('status_sale', 'PENDIENTE')->where('branch__correlative', $branch)->count());
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

    public function getSale(Request $request, $id)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'faulds_pending', 'read')) {
                throw new Exception('No tienes permisos para listar las instataciónes pendientes');
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $InstallationJpa = viewInstallations::find($id);

            $detailSaleJpa = DetailSale::select([
                'detail_sales.id as id',
                'products.id AS product__id',
                'products.type AS product__type',
                'branches.id AS product__branch__id',
                'branches.name AS product__branch__name',
                'branches.correlative AS product__branch__correlative',
                'brands.id AS product__model__brand__id',
                'brands.correlative AS product__model__brand__correlative',
                'brands.brand AS product__model__brand__brand',
                'brands.relative_id AS product__model__brand__relative_id',
                'categories.id AS product__model__category__id',
                'categories.category AS product__model__category__category',
                'models.id AS product__model__id',
                'models.model AS product__model__model',
                'models.relative_id AS product__model__relative_id',
                'products.relative_id AS product__relative_id',
                'products.mac AS product__mac',
                'products.serie AS product__serie',
                'products.price_sale AS product__price_sale',
                'products.currency AS product__currency',
                'products.num_guia AS product__num_guia',
                'products.condition_product AS product__condition_product',
                'products.disponibility AS product__disponibility',
                'products.product_status AS product__product_status',
                'detail_sales.mount_new as mount_new',
                'detail_sales.mount_second as mount_second',
                'detail_sales.mount_ill_fated as mount_ill_fated',
                'detail_sales.description as description',
                'detail_sales._sales_product as _sales_product',
                'detail_sales.status as status',
            ])
                ->join('products', 'detail_sales._product', 'products.id')
                ->join('branches', 'products._branch', 'branches.id')
                ->join('models', 'products._model', 'models.id')
                ->join('brands', 'models._brand', 'brands.id')
                ->join('categories', 'models._category', 'categories.id')
                ->whereNotNull('detail_sales.status')
                ->where('_sales_product', $id)
                ->get();

            $details = array();
            foreach ($detailSaleJpa as $detailJpa) {
                $detail = gJSON::restore($detailJpa->toArray(), '__');
                if ($detail['product']['type'] == "MATERIAL") {
                    $productByTechnicalJpa = ProductByTechnical::select(
                        [
                            'id',
                            '_technical',
                            '_product',
                            'mount_new',
                            'mount_second',
                            'mount_ill_fated',
                        ]
                    )
                        ->where('_technical', $InstallationJpa->technical__id)
                        ->where('_model', $detail['product']['model']['id'])
                        ->whereNotNull('status')
                        ->whereNot('type', 'LEND')
                        ->first();

                    $detail['max_new'] = $productByTechnicalJpa->mount_new + $detail['mount_new'];
                    $detail['max_second'] = $productByTechnicalJpa->mount_second + $detail['mount_second'];
                    $detail['max_ill_fated'] = $productByTechnicalJpa->mount_ill_fated + $detail['mount_ill_fated'];
                }
                $details[] = $detail;
            }

            $installJpa = gJSON::restore($InstallationJpa->toArray(), '__');
            $installJpa['products'] = $details;

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($installJpa);
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'Ln:' . $th->getLine());
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

            if (!gValidate::check($role->permissions, $branch, 'faulds_pending', 'create')) {
                throw new Exception('No tienes permisos para listar modelos');
            }

            if (
                !isset($request->id) ||
                !isset($request->_technical)
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
            if (isset($request->price_installation)) {
                $salesProduct->price_installation = $request->price_installation;
            }
            if (isset($request->type_intallation)) {
                $salesProduct->type_intallation = $request->type_intallation;
            }
            if (isset($request->type_pay)) {
                $salesProduct->type_pay = $request->type_pay;
            }
            if (isset($request->mount_dues)) {
                $salesProduct->mount_dues = $request->mount_dues;
            }
            if (isset($request->status_sale)) {
                $salesProduct->status_sale = $request->status_sale;
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

                            $productByTechnicalJpa = ProductByTechnical::where('_technical', $request->_technical)
                                ->whereNotNull('status')
                                ->whereNot('type', 'LEND')
                                ->where('_model', $product['product']['model']['id'])->first();
                            if (intval($detailSale->mount_new) != intval($product['mount_new'])) {
                                if (intval($detailSale->mount_new) > intval($product['mount_new'])) {
                                    $mount_dif = intval($detailSale->mount_new) - intval($product['mount_new']);
                                    $productByTechnicalJpa->mount_new = intval($productByTechnicalJpa->mount_new) + $mount_dif;
                                } else if (intval($detailSale->mount_new) < intval($product['mount_new'])) {
                                    $mount_dif = intval($product['mount_new']) - intval($detailSale->mount_new);
                                    $productByTechnicalJpa->mount_new = intval($productByTechnicalJpa->mount_new) - $mount_dif;
                                }
                            }

                            if (intval($detailSale->mount_second) != intval($product['mount_second'])) {
                                if (intval($detailSale->mount_second) > intval($product['mount_second'])) {
                                    $mount_dif = intval($detailSale->mount_second) - intval($product['mount_second']);
                                    $productByTechnicalJpa->mount_second = intval($productByTechnicalJpa->mount_second) + $mount_dif;
                                } else if (intval($detailSale->mount_second) < intval($product['mount_second'])) {
                                    $mount_dif = intval($product['mount_second']) - intval($detailSale->mount_second);
                                    $productByTechnicalJpa->mount_second = intval($productByTechnicalJpa->mount_second) - $mount_dif;
                                }
                            }

                            if (intval($detailSale->mount_ill_fated) != intval($product['mount_ill_fated'])) {
                                if (intval($detailSale->mount_ill_fated) > intval($product['mount_ill_fated'])) {
                                    $mount_dif = intval($detailSale->mount_ill_fated) - intval($product['mount_ill_fated']);
                                    $productByTechnicalJpa->mount_ill_fated = intval($productByTechnicalJpa->mount_ill_fated) + $mount_dif;
                                } else if (intval($detailSale->mount_ill_fated) < intval($product['mount_ill_fated'])) {
                                    $mount_dif = intval($product['mount_ill_fated']) - intval($detailSale->mount_ill_fated);
                                    $productByTechnicalJpa->mount_ill_fated = intval($productByTechnicalJpa->mount_ill_fated) - $mount_dif;
                                }
                            }

                            $detailSale->mount_new = $product['mount_new'];
                            $detailSale->mount_second = $product['mount_second'];
                            $detailSale->mount_ill_fated = $product['mount_ill_fated'];
                            $productByTechnicalJpa->save();
                        }
                        $detailSale->description = $product['description'];
                        $detailSale->save();

                        if (isset($request->status_sale)) {
                            if ($request->status_sale == 'CULMINADA') {

                                $PeopleJpa = People::where('id', $salesProduct->_client)->first();

                                if ($product['product']['type'] == "EQUIPO") {
                                    $productJpa->disponibility = 'INSTALACION: ' . $PeopleJpa->name . ' ' . $PeopleJpa->lastname;
                                }
                                if (
                                    isset($request->image_qr)
                                ) {
                                    $salesProduct->image_type = $request->image_type;
                                    $salesProduct->image_qr = base64_decode($request->image_qr);
                                }
                                $salesProduct->issue_date = gTrace::getDate('mysql');
                                $salesProduct->_issue_user = $userid;
                            }
                        }
                        $productJpa->save();
                    } else {
                        $productJpa = Product::find($product['product']['id']);

                        if ($product['product']['type'] == "MATERIAL") {

                            $productByTechnicalJpa = ProductByTechnical::where('_technical', $request->_technical)
                                ->whereNotNull('status')
                                ->whereNot('type', 'LEND')
                                ->where('_model', $product['product']['model']['id'])->first();

                            if ($product['mount_new'] > 0) {
                                $productByTechnicalJpa->mount_new = $productByTechnicalJpa->mount_new - $product['mount_new'];
                            }

                            if ($product['mount_second'] > 0) {
                                $productByTechnicalJpa->mount_second = $productByTechnicalJpa->mount_second - $product['mount_second'];
                            }

                            if ($product['mount_ill_fated'] > 0) {
                                $productByTechnicalJpa->mount_ill_fated = $productByTechnicalJpa->mount_ill_fated - $product['mount_ill_fated'];
                            }

                            $productByTechnicalJpa->save();
                        } else {
                            $productJpa->disponibility = "VENDIENDO";
                            $stock = Stock::where('_model', $productJpa->_model)
                                ->where('_branch', $branch_->id)
                                ->first();
                            if ($productJpa->product_status == "NUEVO") {
                                $stock->mount_new = $stock->mount_new - 1;
                            } else if ($productJpa->product_status == "SEMINUEVO") {
                                $stock->mount_second = $stock->mount_second - 1;
                            }
                            $stock->save();
                        }

                        $productJpa->save();

                        $detailSale = new DetailSale();
                        $detailSale->_product = $productJpa->id;
                        $detailSale->mount_new = $product['mount_new'];
                        $detailSale->mount_second = $product['mount_second'];
                        $detailSale->mount_ill_fated = $product['mount_ill_fated'];
                        $detailSale->description = $product['description'];
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

    public function returnProduct(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);

            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'faulds_pending', 'update')) {
                throw new Exception('No tienes permisos para listar modelos');
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $ProductJpa = Product::find($request->product['id']);
            $ProductJpa->product_status = "SEMINUEVO";
            $ProductJpa->disponibility = "DISPONIBLE";
            $ProductJpa->description = $request->description . '... (Cliente:' . $request->people . ')';
            $ProductJpa->price_sale = $request->price;
            $ProductJpa->update_date = gTrace::getDate('mysql');
            $ProductJpa->_update_user = $userid;
            $ProductJpa->save();

            $stock = Stock::where('_model', $ProductJpa->_model)
                ->where('_branch', $branch_->id)
                ->first();

            $stock->mount_second = $stock->mount_second + 1;

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
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

    public function getSateByClient(Request $request, $idclient)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'faulds_pending', 'read')) {
                throw new Exception('No tienes permisos para listar averias pedientes');
            }

            $saleProductJpa = SalesProducts::where('_client', $idclient)->first();
            if (!$saleProductJpa) {
                throw new Exception("Error: No se encontro instalacion relacionada con este cliente");
            }

            $detailSaleJpa = DetailSale::select([
                'detail_sales.id as id',
                'products.id AS product__id',
                'products.type AS product__type',
                'branches.id AS product__branch__id',
                'branches.name AS product__branch__name',
                'branches.correlative AS product__branch__correlative',
                'brands.id AS product__brand__id',
                'brands.correlative AS product__brand__correlative',
                'brands.brand AS product__brand__brand',
                'brands.relative_id AS product__brand__relative_id',
                'categories.id AS product__category__id',
                'categories.category AS product__category__category',
                'models.id AS product__model__id',
                'models.model AS product__model__model',
                'models.relative_id AS product__model__relative_id',
                'products.relative_id AS product__relative_id',
                'products.mac AS product__mac',
                'products.serie AS product__serie',
                'products.price_sale AS product__price_sale',
                'products.currency AS product__currency',
                'products.num_gia AS product__num_gia',
                'products.status_product AS product__status_product',
                'detail_sales.mount as mount',
                'detail_sales._sales_product as _sales_product',
                'detail_sales.status as status',
            ])
                ->join('products', 'detail_sales._product', 'products.id')
                ->join('branches', 'products._branch', 'branches.id')
                ->join('brands', 'products._brand', 'brands.id')
                ->join('categories', 'products._category', 'categories.id')
                ->join('models', 'products._model', 'models.id')
                ->whereNotNull('detail_sales.status')
                ->where('_sales_product', $saleProductJpa->id)
                ->get();

            $details = array();
            foreach ($detailSaleJpa as $detailJpa) {
                $detail = gJSON::restore($detailJpa->toArray(), '__');
                $details[] = $detail;
            }

            $InstallationJpa = viewInstallations::where('type_operation__operation', 'INSTALACIÓN')->find($saleProductJpa->id);

            if (!$InstallationJpa) {
                throw new Exception("Error: La instalacion solicitada relaciona al cliente no existe");
            }

            $installJpa = gJSON::restore($InstallationJpa->toArray(), '__');
            $installJpa['products'] = $details;

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($installJpa);
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . ' ln: ' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function paginateFauldCompleted(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'faulds_finished', 'read')) {
                throw new Exception('No tienes permisos para listar las averias completadas');
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
                if ($column == 'id' || $column == '*') {
                    $q->orWhere('id', $type, $value);
                }
                if ($column == 'client__name' || $column == '*') {
                    $q->orWhere('client__name', $type, $value);
                }
                if ($column == 'user_creation__username' || $column == '*') {
                    $q->orWhere('user_creation__username', $type, $value);
                }
                if ($column == 'date_sale' || $column == '*') {
                    $q->orWhere('date_sale', $type, $value);
                }
            })
                ->where('type_operation__operation', 'AVERIA')
                ->where('status_sale', 'CULMINADA')
                ->where('branch__correlative', $branch);
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
            $response->setITotalRecords(Viewinstallations::where('status_sale', 'CULMINADA')->count());
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

    public function delete(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'faulds_pending', 'delete_restore')) {
                throw new Exception('No tienes permisos para eliminar averia pendientes');
            }
            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: Es necesario el ID para esta operación");
            }
            $saleProductJpa = SalesProducts::find($request->id);
            if (!$saleProductJpa) {
                throw new Exception("Este reguistro no existe");
            }

            $detailsSalesJpa = DetailSale::where('_sales_product', $saleProductJpa->id)
                ->get();

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            foreach ($detailsSalesJpa as $detail) {
                $detailSale = DetailSale::find($detail['id']);
                $detailSale->status = null;
                $productJpa = Product::find($detail['_product']);

                $stock = Stock::where('_model', $productJpa->_model)
                    ->where('_branch', $branch_->id)
                    ->first();

                $productJpa->disponibility = "DISPONIBLE";
                if ($productJpa->type == "MATERIAL") {
                    $productByTechnicalJpa = ProductByTechnical::where('_technical', $saleProductJpa->_technical)
                        ->whereNotNull('status')
                        ->whereNot('type', 'LEND')
                        ->where('_model', $productJpa->_model)->first();
                    $productByTechnicalJpa->mount_new = $productByTechnicalJpa->mount_new + $detail['mount_new'];
                    $productByTechnicalJpa->mount_second = $productByTechnicalJpa->mount_second + $detail['mount_second'];
                    $productByTechnicalJpa->mount_ill_fated = $productByTechnicalJpa->mount_ill_fated + $detail['mount_ill_fated'];
                    $productByTechnicalJpa->save();
                } else {
                    if ($productJpa->product_status == 'NUEVO') {
                        $stock->mount_new = $stock->mount_new + 1;
                    } else if ($productJpa->product_status == 'SEMINUEVO') {
                        $stock->mount_second = $stock->mount_second + 1;
                    }else{
                        $stock->mount_ill_fated = $stock->mount_ill_fated + 1;
                    }
                }
                $stock->save();
                $productJpa->save();
                $detailSale->save();
            }

            $saleProductJpa->update_date = gTrace::getDate('mysql');
            $saleProductJpa->status = null;
            $saleProductJpa->save();
            $response->setStatus(200);
            $response->setMessage('La instalación se elimino correctamente.');
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
