<?php

namespace App\Http\Controllers;

use App\gLibraries\gJSON;

use App\Models\ExcelExport;
use App\Models\Product;
use App\Models\ProductByTechnical;
use App\Models\Response;
use App\Models\ViewParcelsRegisters;
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

        $query = ViewParcelsRegisters::select(['*'])
            ->orderBy('id', 'desc');

        // if (isset($request->date_start) && isset($request->date_end)) {
        //     $query->where('date_entry', '<=', $request->date_end)
        //         ->where('date_entry', '>=', $request->date_start);
        // }

        $parcelsJpa = $query->get();
        
        $parcels = array();

        foreach ($parcelsJpa as $parcelJpa) {
            // $parcel = gJSON::restore($parcelJpa->toArray(), '__');
            $parcel['date_send'] = $parcelJpa->date_send;
            // $parcel['date_send'] = $parcelJpa->date_send;

            $parcels[] = $parcel;
        } 

        // return $parcels;

        // foreach ($parcelsJpa as $parcelJpa) {
        //     $parcel = gJSON::restore($parcelJpa->toArray(), '__');
        //     $parcels[] = $parcel;
        // }

        // return $parcelsJpa;


        // $data = [
        //     [
        //         'date_send' => 'marzo xs',
        //         'num_voucher' => 'comprobante',
        //         "buisnes" => "GALVANIZADOS",
        //         "price_transport" => "120",
        //         "date_pickup" => '12/12/2012',
        //         "num_guia" => "GIA",
        //         "provider" => "PROVIDER",
        //         "description" => 'DESCRIPCION DE PRODUCTO',
        //         "extent" => 'UNIDAD',
        //         "mount" => '120',
        //         "nun_bill" => 'NUM FACTURA',
        //         'business_destination' => "FASTNETPERU",
        //         'val_unit' => '12',
        //         'subtotal' => '140',
        //         'igv' => '2',
        //         'price_with_igv' => '160',
        //         'price' => '160',
        //         'price_unit_with_igv' => '16',
        //         'margin_revenue35' => '3',
        //         'price_all' => '130',
        //     ],

        // ];

        $export = new ExcelExport($parcels, 'ENERO');
        $tempFilePath = 'public/temp/archivo_excel.xlsx';
        Excel::store($export, $tempFilePath);
        $tempFilePath = storage_path('app/' . $tempFilePath);
        $excelContent = file_get_contents($tempFilePath);
        $base64Content = base64_encode($excelContent);
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
