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
                '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
            ],
            [
                'CONTROL DE TRANSPORTE', '', '', '', '', 'CONTROL DE GUIA DE PROVEEDOR', '', '', '', '', 'CONTROL DE FACTURA', '', '', '', '', '', '', 'PRECIO AL 35%', '', '',
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
                $event->sheet->getDelegate()->mergeCells('A1:T1');
                $event->sheet->getDelegate()->getStyle('A1:T1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                // $event->sheet->getDelegate()->getStyle('A1:T1')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $event->sheet->getDelegate()->getStyle('A1:T1')->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $event->sheet->getDelegate()->getStyle('A1:T1')->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $event->sheet->getDelegate()->getStyle('A1:T1')->getBorders()->getLeft()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $event->sheet->getDelegate()->getStyle('A1:T1')->getBorders()->getRight()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $event->sheet->getDelegate()->getStyle('A1:T1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF00'); // Color de fondo amarillo
                $event->sheet->getDelegate()->getStyle('A1:T1')->getFont()->getColor()->setARGB('000000');
                $event->sheet->getDelegate()->getStyle('A1:T1')->getFont()->setSize(22);

                // Combinar celdas para el encabezado principal CONTROL DE TRANSPORTE
                $event->sheet->getDelegate()->mergeCells('A2:E2');
                $event->sheet->getDelegate()->getStyle('A2:E2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $event->sheet->getDelegate()->getStyle('A2:E2')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $event->sheet->getDelegate()->getStyle('A2:E2')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('40FF00'); // Color de fondo amarillo
                $event->sheet->getDelegate()->getStyle('A2:E2')->getFont()->getColor()->setARGB('000000');
                $event->sheet->getDelegate()->getStyle('A2:E2')->getFont()->setSize(18);

                // Combinar celdas para la segunda fila del encabezado CONTROL DE GUIA DE PROVEEDOR
                $event->sheet->getDelegate()->mergeCells('F2:J2');
                $event->sheet->getDelegate()->getStyle('F2:J2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $event->sheet->getDelegate()->getStyle('F2:J2')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $event->sheet->getDelegate()->getStyle('F2:J2')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('2E64FE'); // Color de fondo amarillo
                $event->sheet->getDelegate()->getStyle('F2:J2')->getFont()->getColor()->setARGB('000000');
                $event->sheet->getDelegate()->getStyle('F2:J2')->getFont()->setSize(18);

                // Combinar celdas para la segunda fila del encabezado CONTROL DE FACTURA
                $event->sheet->getDelegate()->mergeCells('K2:Q2');
                $event->sheet->getDelegate()->getStyle('K2:Q2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $event->sheet->getDelegate()->getStyle('K2:Q2')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $event->sheet->getDelegate()->getStyle('K2:Q2')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('00BFFF'); // Color de fondo amarillo
                $event->sheet->getDelegate()->getStyle('K2:Q2')->getFont()->getColor()->setARGB('000000');
                $event->sheet->getDelegate()->getStyle('K2:Q2')->getFont()->setSize(18);

                // Combinar celdas para la segunda fila del encabezado PRECIO AL 35%
                $event->sheet->getDelegate()->mergeCells('R2:T2');
                $event->sheet->getDelegate()->getStyle('R2:T2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                // $event->sheet->getDelegate()->getStyle('R2:T2')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $event->sheet->getDelegate()->getStyle('R2:T2')->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $event->sheet->getDelegate()->getStyle('R2:T2')->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $event->sheet->getDelegate()->getStyle('R2:T2')->getBorders()->getLeft()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $event->sheet->getDelegate()->getStyle('R2:T2')->getBorders()->getRight()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $event->sheet->getDelegate()->getStyle('R2:T2')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('00BFFF'); // Color de fondo amarillo
                $event->sheet->getDelegate()->getStyle('R2:T2')->getFont()->getColor()->setARGB('000000');
                $event->sheet->getDelegate()->getStyle('R2:T2')->getFont()->setSize(18);

                // Establecer bordes para los datos en la fila 4 (por ejemplo, de A4 a T4)
                $event->sheet->getDelegate()->getStyle('A3:T3')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

                // Congelar la primera fila (encabezado principal)
                $event->sheet->freezePane('A4', 'T4');
            },
        ];
    }

    public function collection()
    {
        $data = $this->data;
        return collect($data);
    }
}
