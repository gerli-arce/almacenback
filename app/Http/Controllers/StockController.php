<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gValidate;
use App\Models\Response;
use App\Models\ViewModels;
use App\Models\ViewProducts;
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

            $subquery = ViewProducts::selectRaw(
                '
                    status_product,
                    branch__id,
                    branch__name,
                    branch__correlative,
                    brand__id,
                    brand__correlative,
                    brand__brand,
                    brand__relative_id,
                    category__id,
                    category__category,
                    model__id,
                    model__model,
                    model__relative_id,
                    sum(mount) as stock
                '
            )->groupBy('brand__brand', 'model__model', 'category__category')
                ->where('branch__correlative', $branch)
                ->where('status_product','!=','VENDIDO');

            $modelsJpa = ViewModels::select(['*'])->get();
            $models = array();
            foreach ($modelsJpa as $modelJpa) {
                $model = gJSON::restore($modelJpa->toArray(), '__');
                $models[] = $model;
            }

            $query = ViewProducts::fromSub($subquery, 'aggregate')
                ->select(
                    'brand__id',
                    'brand__correlative',
                    'brand__brand',
                    'brand__relative_id',
                    'category__id',
                    'category__category',
                    'model__id',
                    'model__model',
                    'model__relative_id',
                    'stock'
                )
                ->orderBy($request->order['column'], $request->order['dir'])
                ->where(function ($q) use ($request) {
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
                    if ($column == 'stock' || $column == '*') {
                        $q->orWhere('stock', $type, $value);
                    }
                });

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
            $resultados = [];
            foreach ($models as $modelo) {
                $encontrado = false;
                foreach ($products as $producto) {
                    if ($producto['model']['id'] == $modelo['id']) {
                        $resultados[] = [
                            'model' => $modelo['model'],
                            'brand' => $modelo['brand'],
                            'category' => $modelo['category'],
                            'stock' => $producto['stock'],
                            'id' => $modelo['id']
                        ];
                        $encontrado = true;
                        break;
                    }
                }

                if (!$encontrado) {
                    $resultados[] = [
                        'model' => $modelo['model'],
                        'brand' => $modelo['brand'],
                        'category' => $modelo['category'],
                        'stock' => 0,
                        'id' => $modelo['id']
                    ];
                }
            }

            $response->setStatus(200);
            $response->setMessage('Operación correcta');
            $response->setDraw($request->draw);
            $response->setITotalDisplayRecords($iTotalDisplayRecords);
            $response->setITotalRecords(ViewProducts::groupBy('brand__brand', 'model__model', 'category__category')
                    ->where('branch__correlative', $branch)
                    ->count());
            // $response->setData($result);
            $response->setData($resultados);
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
}
