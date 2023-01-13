<?php

namespace App\gLibraries;

class gUbigeo
{
    static public function find(?string $ubigeo): array
    {
        $ubigeo = $ubigeo ?? '150101';
        $lista_ubigeo = gJSON::parse(file_get_contents('../storage/json/ubigeoINEI.json'));
        $result = array_filter($lista_ubigeo, function ($data) use ($ubigeo) {
            return $data['ubigeo'] == $ubigeo;
        });
        return reset($result);
    }
}
