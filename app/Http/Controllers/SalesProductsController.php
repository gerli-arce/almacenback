<?php

namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\DetailSale;
use App\Models\Product;
use App\Models\Response;
use App\Models\SalesProducts;
use App\Models\viewInstallations;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesProductsController extends Controller
{
    public function registerInstallation(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'install_pending', 'create')) {
                throw new Exception('No tienes permisos para listar modelos');
            }

            if (!isset($request->_client) ||
                !isset($request->_technical) ||
                !isset($request->_type_operation) ||
                !isset($request->date_sale)) {
                throw new Exception('Error: No deje campos vacíos');
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $salesProduct = new SalesProducts();
            $salesProduct->_user = $userid;
            $salesProduct->_client = $request->_client;
            $salesProduct->_technical = $request->_technical;
            $salesProduct->_branch = $branch_->id;
            $salesProduct->_type_operation = $request->_type_operation;
            $salesProduct->date_sale = $request->date_sale;
            $salesProduct->status_sale = $request->status_sale;
            $salesProduct->price_all = $request->price_all;
            $salesProduct->status = "1";
            $salesProduct->save();

            if (isset($request->data)) {
                foreach ($request->data as $product) {
                    $productJpa = Product::find($product['id']);
                    $productJpa->status_product = "VENDIENDO";

                    if ($product['type'] == "MATERIAL") {
                        $mount = $productJpa->mount - $product['mount'];
                        $productJpa->mount = $mount;
                    }
                    $productJpa->save();

                    $detailSale = new DetailSale();
                    $detailSale->_product = $product['id'];
                    $detailSale->mount = $product['mount'];
                    $detailSale->_sales_product = $salesProduct->id;
                    $detailSale->status = '1';
                    $detailSale->save();
                }
            }
            $response->setStatus(200);
            $response->setMessage('Instalación agregada correctamente');
        } catch (\Throwable$th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function paginateInstallationsPending(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'installations_pending', 'read')) {
                throw new Exception('No tienes permisos para listar las instataciónes pendientes');
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
                    $q->where('technical__name', $type, $value);
                }
                if ($column == 'client__name' || $column == '*') {
                    $q->where('client__name', $type, $value);
                }
                if ($column == 'user__username' || $column == '*') {
                    $q->orWhere('user__username', $type, $value);
                }
                if ($column == 'date_sale' || $column == '*') {
                    $q->orWhere('date_sale', $type, $value);
                }
            })->where('status_sale', 'PENDIENTE');
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
        } catch (\Throwable$th) {
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
            if (!gValidate::check($role->permissions, $branch, 'installations_pending', 'read')) {
                throw new Exception('No tienes permisos para listar las instataciónes pendientes');
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
            ])
                ->join('products', 'detail_sales._product', 'products.id')
                ->join('branches', 'products._branch', 'branches.id')
                ->join('brands', 'products._brand', 'brands.id')
                ->join('categories', 'products._category', 'categories.id')
                ->join('models', 'products._model', 'models.id')
                ->where('_sales_product', $id)->get();

            $details = array();
            foreach ($detailSaleJpa as $detailJpa) {
                $detail = gJSON::restore($detailJpa->toArray(), '__');
                $details[] = $detail;
            }

            $InstallationJpa = viewInstallations::find($id);

            $installJpa = gJSON::restore($InstallationJpa->toArray(), '__');
            $installJpa['products'] = $details;

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($installJpa);
        } catch (\Throwable$th) {
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

            if (!gValidate::check($role->permissions, $branch, 'install_pending', 'create')) {
                throw new Exception('No tienes permisos para listar modelos');
            }

            if (!isset($request->id)) {
                throw new Exception('Error: No deje campos vacíos');
            }

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

            if (isset($request->status_sale)) {
                $salesProduct->status_sale = $request->status_sale;
            }

            $salesProduct->save();

            if (isset($request->data)) {
                foreach ($request->data as $product) {
                    $productJpa = Product::find($product['id']);

                    if ($product['type'] == "MATERIAL") {
                        $detailSale = DetailSale::where('_product', $product['id'])->first();
                        $detailSale->mount = $product['mount'];
                        if ($detailSale->mount != $product['mount']) {
                            if ($detailSale->mount > $product['mount']) {
                                $mount_dif = $detailSale->mount - $product['mount'];
                                $productJpa->mount = $productJpa->mount + $mount_dif;
                            } else if ($detailSale->mount < $product['mount']) {
                                $mount_dif = $product['mount'] - $detailSale->mount;
                                $productJpa->mount = $productJpa->mount - $mount_dif;
                            }
                        }
                        $detailSale->save();
                    }

                    if (isset($request->status_sale)) {
                        if ($request->status_sale == 'CULMINADA') {
                            if ($product['type'] == "EQUIPO") {
                                $productJpa->status_product = 'VENDIDO';
                            }
                        }
                    }

                    $productJpa->save();

                    
                }
            }
            $response->setStatus(200);
            $response->setMessage('Instalación atualizada correctamente');
        } catch (\Throwable$th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage().' ln:'.$th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }
}
