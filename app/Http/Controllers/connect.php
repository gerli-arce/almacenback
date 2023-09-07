<?php

namespace App\Http\Controllers;

use App\Models\ExcelExport;
use App\Models\Product;
use App\Models\ProductByTechnical;
use App\Models\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

// use Illuminate\Http\Response;

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
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'ln: ' . $th->getLine());
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
                "Email" => "juan@example.com",
            ],
            [
                "Nombre" => "María",
                "Apellido" => "Gómez",
                "Edad" => 25,
                "Email" => "maria@example.com",
            ],
            [
                "Nombre" => "Carlos",
                "Apellido" => "López",
                "Edad" => 35,
                "Email" => "carlos@example.com",
            ],
        ];
        
        $export = new ExcelExport($data);

        // Genera el archivo Excel y obtén su contenido
        $tempFilePath = 'public/temp/archivo_excel.xlsx';
        Excel::store($export, $tempFilePath);
    
        // Obtiene la ruta completa al archivo temporal
        $tempFilePath = storage_path('app/' . $tempFilePath);
    
        // Lee el contenido del archivo temporal en una variable
        $excelContent = file_get_contents($tempFilePath);
    
        // Convierte el contenido a base64
        $base64Content = base64_encode($excelContent);
    
        // Retorna el contenido base64
        return response()->json(['base64' => $base64Content]);
    }

    public function changeByProductForModel(Request $request)
    {
        $response = new Response();
        try {
            $ProductByTechnical = ProductByTechnical::get();

            foreach ($ProductByTechnical as $product) {
                $productJpa = Product::find($product->_product);
                if ($productJpa) {
                    $product_technical = ProductByTechnical::find($product->id);
                    $product_technical->_model = $productJpa->_model;
                    $product_technical->save();
                } else {
                    $product_technical = ProductByTechnical::find($product->id);
                    $product_technical->_model = 72;
                    $product_technical->save();
                }

            }
            $response->setMessage('Operacion correcta');
            $response->setStatus('200');
            $response->setData($ProductByTechnical->toArray());
        } catch (\Throwable $th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . 'ln: ' . $th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

}
