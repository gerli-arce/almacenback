<?php

namespace App\Http\Controllers;

use App\gLibraries\gValidate;
use App\gLibraries\gJSON;
use App\Models\DetailSale;
use App\Models\SalesProducts;
use App\Models\viewInstallations;
use Illuminate\Support\Facades\DB;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Response;
use Exception;
use Illuminate\Http\Request;

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

    public function paginateInstallationsPending(Request $request){
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
               '*'
            ])
                ->orderBy($request->order['column'], $request->order['dir']);

            // if (!$request->all || !gValidate::check($role->permissions, 'views', 'see_trash')) {
            // }

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
            })->where('status_sale','PENDIENTE');
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
}
