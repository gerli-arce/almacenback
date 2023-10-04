<?php

namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\guid;
use App\gLibraries\gValidate;
use App\Models\{Branch, People, Response, ViewPeople, ViewModels, ViewStock, User, SalesProducts,};
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
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
            ->whereNotNull('status')
                ->orderBy($request->order['column'], $request->order['dir']);

            if ($request->all) {
                $query->where(function ($q) use ($request) {
                    $q->where('mount_new', '>', '0')
                        ->orWhere('mount_second', '>', '0');
                });
            }

            $query->where(function ($q) use ($request) {
                $column = $request->search['column'];
                $type = $request->search['regex'] ? 'like' : '=';
                $value = $request->search['value'];
                $value = $type == 'like' ? DB::raw("'%{$value}%'") : $value;

                if ($column == 'brand__brand' || $column == '*') {
                    $q->where('brand__brand', $type, $value);
                }
                if ($column == 'category__category' || $column == '*') {
                    $q->orWhere('category__category', $type, $value);
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
        } catch (\Throwable $th) {
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
