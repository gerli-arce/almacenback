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
