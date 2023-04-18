<?php

namespace App\Http\Controllers;

use App\gLibraries\gJson;
use App\gLibraries\gTrace;
use App\gLibraries\gValidate;
use App\Models\Branch;
use App\Models\Response;
use App\Models\ViewStock;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;
use Illuminate\Http\Request;

class PDFController extends Controller
{

    // public function QRinstallation(Request $request)
    // {
    //     $response = new Response();
    //     $content = null;
    //     $type = 'image/svg+xml';
    //     try {
    //         $renderer = new ImageRenderer(
    //             new SvgImageBackEnd()
    //         );
    //         $writer = new Writer($renderer);
    //         $qrCode = $writer->writeString($request->url);

    //         $content = $qrCode;
    //         $response->setStatus(200);
    //         $response->setMessage('Operacion correcta');
    //         $response->setData([base64_decode($qrCode)]);
    //     } catch (\Throwable$th) {
    //         $response->setStatus(400);
    //         $response->setMessage($th->getMessage().' ln:'.$th->getLine());
    //     } finally {
    //         return response(
    //             $response->toArray(),
    //             $response->getStatus()
    //         );
    //     }
    // }

    public function generateReportByStockByProducts(Request $request)
    {
        try {

            [$branch, $status, $message, $role, $userid] = gValidate::get($request);
            if ($status != 200) {
                throw new Exception($message);
            }

            if (!gValidate::check($role->permissions, $branch, 'stock', 'read')) {
                throw new Exception('No tienes permisos para listar stock');
            }

            $branch_ = Branch::select('id', 'name', 'correlative')->where('correlative', $branch)->first();

            $options = new Options();
            $options->set('isRemoteEnabled', true);

            $pdf = new Dompdf($options);

            $template = file_get_contents('../storage/templates/reportStockByProducts.html');

            $sumary = '';

            $query = ViewStock::select(['*']);

            $stocksJpa = $query->where('branch__correlative', $branch)->get();

            $stocks = array();
            foreach ($stocksJpa as $stockJpa) {
                $stock = gJSON::restore($stockJpa->toArray(), '__');
                $stocks[] = $stock;
            }

            foreach ($stocks as $models) {
                $currency = "$";
                if ($models['model']['currency'] == "SOLES") {
                    $currency = "S/.";
                }
                $curencies = "
                <p class='m-0 p-0'>Compra:
                <strong>{$currency}{$models['model']['price_buy']}
                </strong></p>
                <p class='m-0 p-0'>Venta nuevo: <strong>{$currency}{$models['model']['price_sale']}
                </strong></p>
                   <p class='m-0 p-0'>Venta seminuevo: <strong>{$currency}{$models['model']['price_sale_second']}
                }</strong></p>
                ";

                $stock = "<center>
                <p class='m-0 p-0'>Nuevos</p>
                <p class='m-0 p-0' style='font-size:20px;font-weight:800'>{$models['mount_new']}</p>
                <p class='m-0 p-0'>Seminuevos</p>
                <p class='m-0 p-0' style='font-size:20px;font-weight:800'>{$models['mount_second']}</p>
                </center>";

                $sumary .= "
                <tr>
                    <td class='text-center'>{$models['id']}</td>
                    <td><img src='https://almacendev.fastnetperu.com.pe/api/model/{$models['model']['relative_id']}/mini' style='background-color: #38414a;object-fit: cover; object-position: center center; cursor: pointer; height:50px;'></img></td>
                    <td>{$curencies}</td>
                    <td class='text-center'>{$stock}</td>
                    <td class='text-center'>{$models['model']['description']}</td>
                </tr>
            ";
            }

            $template = str_replace(
                [
                    '{branch_name}',
                    '{issue_long_date}',
                    '{summary}',
                ],
                [
                    $branch_->name,
                    gTrace::getDate('long'),
                    $sumary,
                ],
                $template
            );

            $pdf->loadHTML($template);
            $pdf->render();

            return $pdf->stream('Informe.pdf');
        } catch (\Throwable$th) {
            $response = new Response();
            $response->setStatus(400);
            $response->setMessage($th->getMessage() . ' ln:' . $th->getLine());

            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

    public function getStock($branch): array
    {

    }

    public function pruebaRender(Request $request)
    {

        try {
            // Plantilla HTML
            $template = '
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Informe</title>
                </head>
                <body>
                    <h1>Informe</h1>
                    <p>Este es un ejemplo de contenido para el informe en formato PDF.</p>
                    <ul>
                        <li>Elemento 1</li>
                        <li>Elemento 2</li>
                        <li>Elemento 3</li>
                    </ul>
                    <p>Gracias por usar nuestro servicio.</p>
                </body>
                </html>
            ';

            // Configuración de Dompdf
            $options = new Options();
            $options->set('isRemoteEnabled', true); // Habilitar carga de recursos remotos (CSS, imágenes, etc.)

            $pdf = new Dompdf($options);
            $pdf->loadHTML($template);
            $pdf->render();

            return $pdf->stream('Informe.pdf');
        } catch (\Throwable$th) {
            $response = new Response();
            $response->setStatus(400);
            $response->setMessage($th->getMessage());

            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }

}
