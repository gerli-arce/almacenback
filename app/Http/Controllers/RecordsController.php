<?php

namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Response;
use App\Models\Stock;
use App\Models\ViewDetailSale;
use App\Models\ViewProducts;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecordsController extends Controller
{

    public function paginateEquipment(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'record_equipment', 'read')) {
                throw new Exception('No tienes permisos para listar productos');
            }

            $query = ViewProducts::select(['*'])
                ->orderBy($request->order['column'], $request->order['dir']);

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            if (isset($request->search['brand'])) {
                $query->where('model__brand__id', $request->search['brand']);
            }
            if (isset($request->search['category'])) {
                $query->where('model__category__id', $request->search['category']);
            }
            if (isset($request->search['model'])) {
                $query->where('model__id', $request->search['model']);
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;

                if ($column == 'model__brand__brand' || $column == '*') {
                    $q->where('model__brand__brand', $type, $value);
                }
                if ($column == 'model__category__category' || $column == '*') {
                    $q->where('model__category__category', $type, $value);
                }
                if ($column == 'model__model' || $column == '*') {
                    $q->orWhere('model__model', $type, $value);
                }
                if ($column == 'mac' || $column == '*') {
                    $q->orWhere('mac', $type, $value);
                }
                if ($column == 'serie' || $column == '*') {
                    $q->orWhere('serie', $type, $value);
                }
                if ($column == 'price_buy' || $column == '*') {
                    $q->orWhere('price_buy', $type, $value);
                }
                if ($column == 'product_status' || $column == '*') {
                    $q->orWhere('product_status', $type, $value);
                }
                if ($column == 'status' || $column == '*') {
                    $q->orWhere('status', $type, $value);
                }
            })->where('branch__correlative', $branch)
                ->where('disponibility', '=', 'VENDIDO');
            $iTotalDisplayRecords = $query->count();

            $productsJpa = $query

                ->skip($request->start)
                ->take($request->length)
                ->get();

            $products = array();
            foreach ($productsJpa as $product_) {
                $product = gJSON::restore($product_->toArray(), '__');
                $products[] = $product;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewProducts::where('branch__correlative', $branch)->count());
            $response->setData($products);
        } catch (\Throwable$th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function searchOperationsByEquipment(Request $request, $idProduct)
    {
        $response = new Response();
        try {

            $detailSaleJpa = ViewDetailSale::where('product__id', $idProduct)->first();
            $detailtSale = gJSON::restore($detailSaleJpa->toArray(), '__');

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($detailtSale);
        } catch (\Throwable$th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'Ln: ' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function returnEqipment(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'record_equipment', 'read')) {
                throw new Exception('No tienes permisos para listar las instataciónes pendientes');
            }

            if (!isset($request->id)) {
                throw new Exception("Error: NO dejes campos vacios");
            }

            $branch_ = Branch::select('id', 'correlative')->where('correlative', $branch)->first();

            $detailSaleJpa = ViewDetailSale::select(
                [
                    'id',
                    'sale_product__client__name',
                    'sale_product__client__lastname',
                    'product__id',
                ]
            )->where('product__id', $request->id)->first();

            $detailtSale = gJSON::restore($detailSaleJpa->toArray(), '__');

            $productJpa = Product::find($request->id);
            $productJpa->condition_product = $request->condition_product;
            $productJpa->product_status = $request->product_status;
            $productJpa->disponibility = "DISPONIBLE";
            $productJpa->description = 'Devuelto del cliente: ' . $detailSaleJpa->sale_product__client__name . ' ' . $detailSaleJpa->sale_product__client__lastname . '; en la fecha: ' . gTrace::getDate('mysql');
            $productJpa->update_date = gTrace::getDate('mysql');
            $productJpa->_update_user = $userid;
            $productJpa->save();

            $stock = Stock::where('_model', $productJpa->_model)
                ->where('_branch', $branch_->id)
                ->first();
            if ($request->product_status == "SEMINUEVO") {
                $stock->mount_second = intval($stock->mount_second) + 1;
            } else if ($request->product_status == "NUEVO") {
                $stock->mount_new = intval($stock->mount_new) + 1;
            }
            
            $stock->save();

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($detailtSale);
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
