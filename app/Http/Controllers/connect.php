<?php

namespace App\Http\Controllers;

use App\gLibraries\guid;
use App\gLibraries\gTrace;
use App\Models\Category;
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
            
            $data = DB::connection('mysql_sisgein')->table('herramientas')->get();

            // foreach($data as $dat){

            //     $categoriesJpa = new Category();
            //     $categoriesJpa->category = $dat->descripcion;
            //     $categoriesJpa->creation_date = gTrace::getDate('mysql');
            //     $categoriesJpa->_creation_user = "2";
            //     $categoriesJpa->update_date = gTrace::getDate('mysql');
            //     $categoriesJpa->_update_user = "2";
            //     $categoriesJpa->status = "1";
            //     $categoriesJpa->save();

            // }

            $response->setStatus(200);
            $response->setData($data->toArray());
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
