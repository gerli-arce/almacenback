<?php

namespace App\Http\Controllers;

use App\gLibraries\guid;
use App\gLibraries\gTrace;
use App\Models\Category;
use App\Models\Stock;
use App\Models\Models;
use App\gLibraries\gValidate;
use App\Models\Response;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Http\Request;

class connect extends Controller
{
    public function dats(Request $request)
    {
        $response = new Response();
        try {
            
            // $data = DB::connection('mysql_sisgein')
            // ->table('kardex')
            // ->get();
            
            $models = Models::select([
                'id',
                'model',
                'relative_id'
            ])->get();

            foreach($models as $model){
                $stock = new Stock();
                $stock->_model = $model->id;
                $stock->mount = '0';
                $stock->stock_min = '5';
                $stock->_branch ='1';
                $stock->status = "1";
                // $stock->save();
            }

            $response->setMessage('Operación correcta');
            $response->setStatus(200);
            $response->setData($models->toArray());
        } catch (\Throwable$th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage().'ln: '.$th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }
}
