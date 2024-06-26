<?php

namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gValidate;

use App\Models\Response;
use App\Models\Stock;
use App\Models\ViewModels;
use App\Models\Branch;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function paginate(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'models', 'read')) {
                throw new Exception('No tienes permisos para listar modelos');
            }

            $query = ViewModels::select(['*'])
                ->orderBy($request->order['column'], $request->order['dir']);

            if (!$request->all) {
                $query->whereNotNull('status');
            }

            if ($request->star) {
                $query->where('star', 1);
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;
                if ($column == 'id' || $column == '*') {
                    $q->where('id', $type, $value);
                }
                if ($column == 'model' || $column == '*') {
                    $q->orWhere('model', $type, $value);
                }
                if ($column == 'brand__brand' || $column == '*') {
                    $q->orWhere('brand__brand', $type, $value);
                }
                if ($column == 'category__category' || $column == '*') {
                    $q->orWhere('category__category', $type, $value);
                }
                if ($column == 'description' || $column == '*') {
                    $q->orWhere('description', $type, $value);
                }
            });

            $iTotalDisplayRecords = $query->count();
            $modelsJpa = $query
                ->skip($request->start)
                ->take($request->length)
                ->get();

            $models = array();
            foreach ($modelsJpa as $modelJpa) {
                $model = gJSON::restore($modelJpa->toArray(), '__');
                $StockJpa = Stock::where('_model', $model['id'])->whereNot('_branch', '8')->whereNotNull('status')->get();
                $stock_mount_new = 0;
                $stock_mount_second = 0;
                $stock_mount_ill_fated = 0;
                foreach ($StockJpa as $stock) {
                    $stock_mount_new += $stock['mount_new'];
                    $stock_mount_second += $stock['mount_second'];
                    $stock_mount_ill_fated += $stock['mount_ill_fated'];
                }
                // $model['stock'] = $StockJpa;
                $model['stock_new'] = $stock_mount_new;
                $model['stock_second'] = $stock_mount_second;
                $model['stock_ill_fated'] = $stock_mount_ill_fated;
                $models[] = $model;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewModels::count());
            $response->setData($models);
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

    public function getStocksByModel(Request $request)
    {
        $response = new Response();
        try {

            [$branch, $status, $message, $role] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'models', 'read')) {
                throw new Exception('No tienes permisos para listar modelos');
            }

            $StockJpa = Stock::where('_model', $request->id)->whereNot('_branch', '8')->whereNotNull('status')->get();

            $stocks = array();
            foreach ($StockJpa as $stockJpa) {
                $branch_ = Branch::find($stockJpa['_branch']);
                $stockJpa['branch'] =  $branch_;
                $stocks[] = $stockJpa;
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setData($stocks);
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
