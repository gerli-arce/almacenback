<?php

namespace App\Models;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExcelExport implements FromCollection, WithHeadings, WithEvents
{

    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    // Define los encabezados del archivo Excel
    public function headings(): array
    {
        return [
            ['CONTROL DE TRANSPORTE', '', '', '', 'CONTROL DE GUIA DE PROVEEDOR', '', '', '', 'CONTROL DE FACTURA', '', '', '', '','','',''], 
            ['EMPRESA DE TRANSPORTE', 'PRECIO', 'FECHA DE RECOJO', 'Nº DE GUIA', 'PROVEEDOR', 'DESCRIPCION', 'MEDIDA', 'CANTIDAD','Nº DE FACTURA','EMPRESA DESIGNADA', 'VALOR UNITARIO','SUB TOTAL', 'IGV','PRECIO CON IGV', 'TOTAL'],
        ];
    }

    // Personaliza eventos del archivo Excel
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Combinar celdas para el encabezado principal
                $event->sheet->getDelegate()->mergeCells('A1:D1');
                $event->sheet->getDelegate()->getStyle('A1:D1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Combinar celdas para la segunda fila del encabezado
                $event->sheet->getDelegate()->mergeCells('E1:H1');
                $event->sheet->getDelegate()->getStyle('E1:H1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                 // Combinar celdas para la segunda fila del encabezado
                 $event->sheet->getDelegate()->mergeCells('I1:O1');
                 $event->sheet->getDelegate()->getStyle('I1:O1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Congelar la primera fila (encabezado principal)
                $event->sheet->freezePane('A3', 'A3');
            },
        ];
    }

    // Devuelve la colección de datos para el archivo Excel
    public function collection()
    {
        // Accede a los datos proporcionados en el constructor
        $data = $this->data;

        // Convierte los datos en una colección y devuélvela
        return collect($data);
    }
}
