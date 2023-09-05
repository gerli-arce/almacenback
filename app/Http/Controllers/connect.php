<?php

namespace App\Http\Controllers;

use App\gLibraries\guid;
use App\gLibraries\gTrace;
use App\Models\Category;
use App\Models\Stock;
use App\Models\Models;
use App\gLibraries\gValidate;
use App\Models\Response;
use App\Models\ProductByTechnical;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

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

    public function exportDataToExcel(Request $request)
    {
        $data = [
            [
                "Nombre" => "Juan",
                "Apellido" => "Pérez",
                "Edad" => 30,
                "Email" => "juan@example.com"
            ],
            [
                "Nombre" => "María",
                "Apellido" => "Gómez",
                "Edad" => 25,
                "Email" => "maria@example.com"
            ],
            [
                "Nombre" => "Carlos",
                "Apellido" => "López",
                "Edad" => 35,
                "Email" => "carlos@example.com"
            ]
        ];

       return Excel::create('archivo_excel', function($excel) use ($data) {
            $excel->sheet('Sheet 1', function($sheet) use ($data) {
                $sheet->fromArray($data);
            });
        })->download('xlsx'); 
    }

    public function changeByProductForModel(Request $request){
        $response = new Response();
        try {
            $ProductByTechnical = ProductByTechnical::get();

            foreach($ProductByTechnical as $product){
                $productJpa = Product::find($product->_product);
                if($productJpa){
                    $product_technical = ProductByTechnical::find($product->id);
                    $product_technical->_model = $productJpa->_model;
                    $product_technical->save();
                }else{
                    $product_technical = ProductByTechnical::find($product->id);
                    $product_technical->_model = 72;
                    $product_technical->save();
                }
                
            }
            $response->setMessage('Operacion correcta');
            $response->setStatus('200');
            $response->setData($ProductByTechnical->toArray());
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
