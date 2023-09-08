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

use Exception;

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

        if(!isset($request->month)){
            throw new Exception('Debes escoger un mes para generar el informe');
        }

        $query = ViewParcelsRegisters::select(['*'])
            ->orderBy('id', 'desc');

        if (isset($request->date_start) && isset($request->date_end)) {
            $query->where('date_entry', '<=', $request->date_end)
                ->where('date_entry', '>=', $request->date_start);
        }

        $month = [
            '1' => 'ENERO',
            '2' => 'FEBRERO',
            '3' => 'MARZO',
            '4' => 'ABRIL',
            '5' => 'MAYO',
            '6' => 'JUNIO',
            '7' => 'JULIO',
            '8' => 'AGOSTO',
            '9' => 'SEPTIEMBRE',
            '10' => 'OCTUBRE',
            '11' => 'NOVIEMBRE',
            '12' => 'DICIEMBRE',
        ];

        $parcelsJpa = $query->get();
        
        $parcels = array();

        foreach ($parcelsJpa as $parcelJpa) {
            // $parcel = gJSON::restore($parcelJpa->toArray(), '__');
            $parcel['date_send'] = $parcelJpa->date_send;
            $parcel['num_voucher'] = $parcelJpa->num_voucher;
            $parcel['business_transport__name'] = $parcelJpa->provider__name;
            $parcel['price_transport'] = $parcelJpa->price_transport;
            $parcel['date_entry'] = $parcelJpa->date_entry;
            $parcel['num_guia'] = $parcelJpa->num_guia;
            $parcel['provider__name'] = $parcelJpa->provider__name;
            $parcel['description'] = $parcelJpa->description;
            $parcel['model__unity__name'] = $parcelJpa->model__unity__name;
            $parcel['mount_product'] = $parcelJpa->mount_product;
            $parcel['num_bill'] = $parcelJpa->num_bill;
            $parcel['business_designed__name'] = $parcelJpa->business_designed__name;
            $parcel['value_unity'] = $parcelJpa->value_unity;
            $parcel['amount'] = $parcelJpa->amount;
            $parcel['igv'] = $parcelJpa->igv;
            $parcel['price_unity'] = $parcelJpa->price_unity;
            $parcel['price'] =round($parcelJpa->amount + $parcelJpa->igv, 2);
            $parcel['price_with_igv'] =round($parcel['price'] / $parcel['mount_product'], 2);
            $parcel['35%'] = round($parcel['price_with_igv']*0.35,2);
            $parcel['price_all'] = round($parcel['price_with_igv']+$parcel['35%'],2);
            $parcels[] = $parcel;
        } 

        $export = new ExcelExport($parcels, $month[$request->month]);
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
