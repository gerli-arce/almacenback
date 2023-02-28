<?php
namespace App\gLibraries;

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Writer;

class gQR
{
    public static function img(string $url)
    {

        $renderer = new ImageRenderer(
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $qrCode = $writer->writeString($url);

        return 'data:image/svg+xml;base64,'.base64_encode($qrCode);
    }

}
