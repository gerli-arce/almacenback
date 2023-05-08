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

class SalesController extends Controller
{
    public function paginate(Request $request){
        $response = new Response();
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'record_sales', 'read')) {
                throw new Exception('No tienes permisos para listar las salidas');
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

                if ($column == 'id') {
                    $value = intval(ltrim($request->search['value'], '0'));
                    $q->where('id', $value);
                }
                if ($column == 'technical__name' || $column == '*') {
                    $q->orWhere('technical__name', $type, $value);
                }
                if ($column == 'technical__lastname' || $column == '*') {
                    $q->orWhere('technical__lastname', $type, $value);
                }
                if ($column == 'client__lastname' || $column == '*') {
                    $q->orWhere('client__lastname', $type, $value);
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
            $response->setITotalRecords(Viewinstallations::where('branch__correlative', $branch)->count());
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
