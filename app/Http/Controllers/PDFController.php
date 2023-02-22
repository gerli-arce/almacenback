<?php

namespace App\Http\Controllers;

use App\Models\Response;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Writer;
use Illuminate\Http\Request;

class PDFController extends Controller
{
    public function QRinstallation(Request $request)
    {
        $response = new Response();
        $content = null;
        $type = 'image/svg+xml';
        try {
            $renderer = new ImageRenderer(
                new SvgImageBackEnd()
            );
            $writer = new Writer($renderer);
            $qrCode = $writer->writeString($request->url);

            $content = $qrCode;
            $response->setStatus(200);
            $response->setMessage('Operacion correcta');
            $response->setData([base64_decode($qrCode)]);
        } catch (\Throwable$th) {
            $response->setStatus(400);
            $response->setMessage($th->getMessage().' ln:'.$th->getLine());
        } finally {
            return response(
                $response->toArray(),
                $response->getStatus()
            );
        }
    }
}
