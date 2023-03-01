<?php

namespace App\Http\Controllers;

use App\gLibraries\gJSON;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\DetailSale;
use App\Models\Product;
use App\Models\Response;
use App\Models\SalesProducts;
use App\Models\viewInstallations;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesProductsController extends Controller
{

    public function imageQR($id)
    {
        $response = new Response();
        $content = null;
        $type = 'image/png';
        try {
            if (
                !isset($id)
            ) {
                throw new Exception("Error: No deje campos vacíos");
            }
            $saleProductQR = SalesProducts::select([
                "sales_products.image_qr as image_content",
                'sales_products.image_type',
            ])
                ->where('id', $id)
                ->first();
            if (!$saleProductQR) {
                throw new Exception('No se encontraron datos');
            }
            if (!$saleProductQR->image_content) {
                throw new Exception('No existe imagen');
            }
            $content = $saleProductQR->image_content;
            $response->setStatus(200);
        } catch (\Throwable$th) {
            $ruta = '../storage/images/QR-default.png';
            $fp = fopen($ruta, 'r');
            $datos_image = fread($fp, filesize($ruta));
            $datos_image = addslashes($datos_image);
            fclose($fp);
            $content = stripslashes($datos_image);
            $type = 'image/png';
            $response->setStatus(400);
        } finally {
            return response(
                $content,
                $response->getStatus()
            )->header('Content-Type', $type);
        }
    }
}



