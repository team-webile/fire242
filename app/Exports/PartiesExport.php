<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PartiesExport implements FromCollection, WithHeadings, WithStyles
{
    protected $parties;
    protected $request;

    public function __construct($parties, $request)
    {
        $this->parties = $parties;
        $this->request = $request;
    }

    public function collection()
    {
        return $this->parties->map(function ($party) {
            return [
                'ID' => $party->id,
                'Name' => $party->name,
                'Short Name' => $party->short_name,
                'Status' => $party->status
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ID',
            'Name', 
            'Short Name',
            'Status'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $columnWidths = [
            'A' => 10, // ID
            'B' => 30, // Name
            'C' => 20, // Short Name
            'D' => 15  // Status
        ];

        foreach ($columnWidths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        return [
            1 => ['font' => ['bold' => true]],
        ]; 
    }
}