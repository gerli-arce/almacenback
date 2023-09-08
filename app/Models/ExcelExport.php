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

    public function headings(): array
    {
        return [
            [
                'CONTROL DE GUIAS Y FACTURAS DE COMPRA ALMACEN MES DE '.$this->month,
                '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
            ],
            [
                'CONTROL DE TRANSPORTE', '', '', '', 'CONTROL DE GUIA DE PROVEEDOR', '', '', '', 'CONTROL DE FACTURA', '', '', '', '', '', '', 'PRECIO AL 35%', '', '',
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
                'PRECIO TOTAL',
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {

                // Combinar celdas para el encabezado principal CONTROL DE GUIAS Y FACTURAS DE COMPRA ALMACEN MES DE (horizontal y vertical)
                $event->sheet->getDelegate()->mergeCells('A1:S1');
                $event->sheet->getDelegate()->getStyle('A1:S1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Combinar celdas para el encabezado principal CONTROL DE TRANSPORTE
                $event->sheet->getDelegate()->mergeCells('A2:D2');
                $event->sheet->getDelegate()->getStyle('A2:D2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Combinar celdas para la segunda fila del encabezado CONTROL DE GUIA DE PROVEEDOR
                $event->sheet->getDelegate()->mergeCells('E2:H2');
                $event->sheet->getDelegate()->getStyle('E2:H2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Combinar celdas para la segunda fila del encabezado CONTROL DE FACTURA
                $event->sheet->getDelegate()->mergeCells('I2:O2');
                $event->sheet->getDelegate()->getStyle('I2:O2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Combinar celdas para la segunda fila del encabezado PRECIO AL 35%
                $event->sheet->getDelegate()->mergeCells('P2:R2');
                $event->sheet->getDelegate()->getStyle('P2:R2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Congelar la primera fila (encabezado principal)
                $event->sheet->freezePane('A3', 'A3');
            },
        ];
    }


    public function collection()
    {
        $data = $this->data;
        return collect($data);
    }
}
