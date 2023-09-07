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

    public function __construct(array $data, string $month)
    {
        $this->data = $data;
        $this->month = $month;
    }

    // Define los encabezados del archivo Excel
    public function headings(): array
    {
        return [
            [
                'CONTROL DE GUIAS Y FATURAS DE COMPRA ALMACEN MES DE '.$this->month,
                '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',''
            ],
            [
                'CONTROL DE TRANSPORTE', 
                '', 
                '', 
                '', 
                'CONTROL DE GUIA DE PROVEEDOR', 
                '', 
                '', 
                '', 
                'CONTROL DE FACTURA', 
                '', 
                '', 
                '', 
                '',
                '',
                '',
                'PRECIO AL 35%',
                '',
                ''
            ], 
            [
                'FECHA DE ENVIO',
                'Nº DE COMPRPOBANTE',
                'EMPRESA DE TRANSPORTE', 
                'PRECIO', 
                'FECHA DE RECOJO', 
                'Nº DE GUIA', 
                'PROVEEDOR', 
                'DESCRIPCION', 
                'MEDIDA', 
                'CANTIDAD',
                'Nº DE FACTURA',
                'EMPRESA DESIGNADA', 
                'VALOR UNITARIO',
                'SUB TOTAL', 
                'IGV',
                'PRECIO CON IGV', 
                'TOTAL', 
                'PRECIO UNIT CON IGV',
                '35%',
                'PRECIO TOTAL'
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {

                // Combinar celdas para el encabezado principal CONTROL DE TITULO (horizontal y vertical)
                $event->sheet->getDelegate()->mergeCells('A1:T2');
                $event->sheet->getDelegate()->getStyle('A1:T2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $event->sheet->getDelegate()->getStyle('A1:T2')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                // Combinar celdas para el encabezado principal CONTROL DE TRANSPORTE
                $event->sheet->getDelegate()->mergeCells('A3:D3');
                $event->sheet->getDelegate()->getStyle('A3:D3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Combinar celdas para la segunda fila del encabezado CONTROL DE GUIA DE PROVEEDOR
                $event->sheet->getDelegate()->mergeCells('E3:H3');
                $event->sheet->getDelegate()->getStyle('E3:H3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                 // Combinar celdas para la segunda fila del encabezado CONTROL DE FACTURA
                 $event->sheet->getDelegate()->mergeCells('I3:O3');
                 $event->sheet->getDelegate()->getStyle('I3:O3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                  // Combinar celdas para la segunda fila del encabezado PRECIO AL 35%
                  $event->sheet->getDelegate()->mergeCells('P3:R3');
                  $event->sheet->getDelegate()->getStyle('P3:R3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Congelar la primera fila (encabezado principal)
                $event->sheet->freezePane('A4', 'A4');
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
