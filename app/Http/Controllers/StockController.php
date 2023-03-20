<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gValidate;
use App\Models\Response;
use App\Models\ViewStock;
use App\Models\Stock;
use App\Models\Models;
use App\Models\Branch;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    public function paginate(Request $request)
    {
        $response = new Response();
        try {
            [$branch, $status, $message, $role, $userid] = gValidate::get($request);

            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'stock', 'read')) {
                throw new Exception('No tienes permisos para listar el stock');
            }

            $query = ViewStock::select(['*'])
                ->orderBy($request->order['column'], $request->order['dir']);

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;

                if ($column == 'brand__brand' || $column == '*') {
                    $q->where('brand__brand', $type, $value);
                }
                if ($column == 'category__category' || $column == '*') {
                    $q->where('category__category', $type, $value);
                }
                if ($column == 'model__model' || $column == '*') {
                    $q->orWhere('model__model', $type, $value);
                }
            })->where('branch__correlative', $branch);

            $iTotalDisplayRecords = $query->count();

            $stocksJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $stocks = array();
            foreach ($stocksJpa as $stockJpa) {
                $stock = gJSON::restore($stockJpa->toArray(), '__');
                $stocks[] = $stock;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewStock::count());
            $response->setData($stocks);
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

    public function update(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }
            if (!gValidate::check($role->permissions, $branch, 'stock', 'update')) {
                throw new Exception('No tienes permisos para actualizar el stock');
            }

            if (
                !isset($request->id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }

            $stockJpa = Stock::find($request->id);

            if(isset($request->stock_min)){
                $stockJpa->stock_min = $request->stock_min;
            }

            // if (gValidate::check($role->permissions, $branch, 'products', 'change_status')) {
            //     if (isset($request->status)) {
            //         $stockJpa->status = $request->status;
            //     }
            // }

            $stockJpa->save();
            $response->setStatus(200);
            $response->setMessage('Producto actualizado correctamente');
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

    // public function regularizar(Request $request)
    // {
    //     $response = new Response();
    //     try {
    //         $models = Models::select('id','model')->get();
    //         $branchs = Branch::select('id', 'name')->get();
    //         $exist = [];
    //         foreach($branchs as $branch){
    //             foreach($models as $model){
    //                 $stockIsExist = Stock::select('id','_model','_branch')
    //                 ->where('_model', $model['id'])
    //                 ->where('_branch', $branch['id'])
    //                 ->first();
    //                 if(!$stockIsExist){
    //                     $stockJpa = new Stock();
    //                     $stockJpa->_model = $model['id'];
    //                     $stockJpa->mount = '0';
    //                     $stockJpa->stock_min = '5';
    //                     $stockJpa->_branch = $branch['id'];
    //                     $stockJpa->status = '1';
    //                     $stockJpa->save();
    //                 }else{
    //                     $exist[] = [
    //                         'model'=>$model['model'],
    //                         'branch'=>$branch['name']
    //                     ];
    //                 }
    //             }
    //         }

    //         $response->setData($exist);
    //         $response->setStatus(200);
    //         $response->setMessage('stocks actualizados correctamente');
    //     } catch (\Throwable$th) {
    //         $response->setStatus(400);
    //         $response->setMessage($th->getMessage());
    //     } finally {
    //         return response(
    //             $response->toArray(),
    //             $response->getStatus()
    //         );
    //     }
    // }
}
