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
                'CONTROL DE GUIAS Y FACTURAS DE COMPRA ALMACEN MES DE ' . $this->month,
                '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '','',
            ],
            [
                'CONTROL DE TRANSPORTE', '', '', '', '', 'CONTROL DE GUIA DE PROVEEDOR','' ,'', '', '', '', 'CONTROL DE FACTURA', '', '', '', '', '', '', 'PRECIO AL 35%', '', '',
            ],
            [
                'FECHA DE ENVIO',
                'Nº DE COMPRPOBANTE',
                'EMPRESA DE TRANSPORTE',
                'PRECIO',
                'FECHA DE RECOJO',
                'Nº DE GUIA',
                'PROVEEDOR',
                'MODELO',
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
                $event->sheet->getDelegate()->mergeCells('A1:U1');
                $event->sheet->getDelegate()->getStyle('A1:U1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                // $event->sheet->getDelegate()->getStyle('A1:U1')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $event->sheet->getDelegate()->getStyle('A1:U1')->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $event->sheet->getDelegate()->getStyle('A1:U1')->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $event->sheet->getDelegate()->getStyle('A1:U1')->getBorders()->getLeft()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $event->sheet->getDelegate()->getStyle('A1:U1')->getBorders()->getRight()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $event->sheet->getDelegate()->getStyle('A1:U1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF00'); // Color de fondo amarillo
                $event->sheet->getDelegate()->getStyle('A1:U1')->getFont()->getColor()->setARGB('000000');
                $event->sheet->getDelegate()->getStyle('A1:U1')->getFont()->setSize(22);

                // Combinar celdas para el encabezado principal CONTROL DE TRANSPORTE
                $event->sheet->getDelegate()->mergeCells('A2:E2');
                $event->sheet->getDelegate()->getStyle('A2:E2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $event->sheet->getDelegate()->getStyle('A2:E2')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $event->sheet->getDelegate()->getStyle('A2:E2')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('40FF00'); // Color de fondo amarillo
                $event->sheet->getDelegate()->getStyle('A2:E2')->getFont()->getColor()->setARGB('000000');
                $event->sheet->getDelegate()->getStyle('A2:E2')->getFont()->setSize(18);

                // Combinar celdas para la segunda fila del encabezado CONTROL DE GUIA DE PROVEEDOR
                $event->sheet->getDelegate()->mergeCells('F2:K2');
                $event->sheet->getDelegate()->getStyle('F2:K2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $event->sheet->getDelegate()->getStyle('F2:K2')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $event->sheet->getDelegate()->getStyle('F2:K2')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('2E64FE'); // Color de fondo amarillo
                $event->sheet->getDelegate()->getStyle('F2:K2')->getFont()->getColor()->setARGB('000000');
                $event->sheet->getDelegate()->getStyle('F2:K2')->getFont()->setSize(18);

                // Combinar celdas para la segunda fila del encabezado CONTROL DE FACTURA
                $event->sheet->getDelegate()->mergeCells('L2:R2');
                $event->sheet->getDelegate()->getStyle('L2:R2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $event->sheet->getDelegate()->getStyle('L2:R2')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $event->sheet->getDelegate()->getStyle('L2:R2')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('00BFFF'); // Color de fondo amarillo
                $event->sheet->getDelegate()->getStyle('L2:R2')->getFont()->getColor()->setARGB('000000');
                $event->sheet->getDelegate()->getStyle('L2:R2')->getFont()->setSize(18);

                // Combinar celdas para la segunda fila del encabezado PRECIO AL 35%
                $event->sheet->getDelegate()->mergeCells('S2:U2');
                $event->sheet->getDelegate()->getStyle('S2:U2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                // $event->sheet->getDelegate()->getStyle('S2:U2')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $event->sheet->getDelegate()->getStyle('S2:U2')->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $event->sheet->getDelegate()->getStyle('S2:U2')->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $event->sheet->getDelegate()->getStyle('S2:U2')->getBorders()->getLeft()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $event->sheet->getDelegate()->getStyle('S2:U2')->getBorders()->getRight()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $event->sheet->getDelegate()->getStyle('S2:U2')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF8000'); // Color de fondo amarillo
                $event->sheet->getDelegate()->getStyle('S2:U2')->getFont()->getColor()->setARGB('000000');
                $event->sheet->getDelegate()->getStyle('S2:U2')->getFont()->setSize(18);

                // Establecer bordes para los datos en la fila 4 (por ejemplo, de A4 a T4)
                $event->sheet->getDelegate()->getStyle('A3:U3')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

                // Congelar la primera fila (encabezado principal)
                $event->sheet->freezePane('A4', 'U4');
            },
        ];
    }

    public function collection()
    {
        $data = $this->data;
        return collect($data);
    }
}
