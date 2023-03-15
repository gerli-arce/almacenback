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
            
            $data = DB::connection('mysql_sisgein')
            ->table('unidades')
            ->get();
        
            // foreach($data as $unity){
            //     $unity
            //     // $stock->save();
            // }

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
