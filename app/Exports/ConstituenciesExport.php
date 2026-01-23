<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ConstituenciesExport implements FromCollection, WithHeadings, WithStyles
{
    protected $constituencies;
    protected $request;
    protected $columns;

    public function __construct($constituencies, $request, $columns)
    {
        $this->constituencies = $constituencies;
        $this->request = $request;
        $this->columns = $columns;
    }

    public function collection()
    {
        return $this->constituencies->map(function ($constituency) {
            $row = [];
            
            if (in_array('id', $this->columns)) {
                $row['ID'] = $constituency->id;
            }
            if (in_array('name', $this->columns)) {
                $row['Name'] = $constituency->name;
            }
            if (in_array('island', $this->columns)) {
                $row['Island'] = $constituency->island->name;
            }
            if (in_array('status', $this->columns)) {
                $row['Status'] = $constituency->is_active ? 'active' : 'inactive';
            }
            
            return $row; 
        });
    }

    public function headings(): array
    {
        $headers = [];
        
        if (in_array('id', $this->columns)) {
            $headers[] = 'ID';
        }
        if (in_array('name', $this->columns)) {
            $headers[] = 'Name';
        }
        if (in_array('island', $this->columns)) {
            $headers[] = 'Island';
        }
        if (in_array('status', $this->columns)) {
            $headers[] = 'Status';
        }

        return $headers;
    }

    public function styles(Worksheet $sheet)
    {
        $columnWidths = [];
        $column = 'A';
        
        if (in_array('id', $this->columns)) {
            $columnWidths[$column++] = 10; // ID
        }
        if (in_array('name', $this->columns)) {
            $columnWidths[$column++] = 30; // Name
        }
        if (in_array('island', $this->columns)) {
            $columnWidths[$column++] = 20; // Island
        }
        if (in_array('status', $this->columns)) {
            $columnWidths[$column++] = 15; // Status
        }

        foreach ($columnWidths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        return [
            1 => ['font' => ['bold' => true]],
        ]; 
    }
}