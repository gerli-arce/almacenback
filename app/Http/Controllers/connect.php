<?php

namespace App\Http\Controllers;

use App\gLibraries\gTrace;
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
            
            $menuespecial = DB::connection('mysql_sisgein')->table('marcas')->get();

            $response->setStatus(200);
            $response->setData($menuespecial->toArray());
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
