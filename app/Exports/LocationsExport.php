<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LocationsExport implements FromCollection, WithHeadings, WithStyles
{
    protected $locations;
    protected $request;
    protected $columns;

    public function __construct($locations, $request, $columns)
    {
        $this->locations = $locations;
        $this->request = $request;
        $this->columns = $columns;
    }

    public function collection()
    {
        return $this->locations->map(function ($location) {
            $row = [];
            
            if (in_array('id', $this->columns)) {
                $row['ID'] = $location->id;
            }
            if (in_array('name', $this->columns)) {
                $row['Name'] = $location->name;
            }
            if (in_array('country', $this->columns)) {
                $row['Country'] = $location->country ? $location->country->name : '';
            }
            if (in_array('status', $this->columns)) {
                $row['Status'] = $location->is_active ? 'Active' : 'Inactive';
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
        if (in_array('country', $this->columns)) {
            $headers[] = 'Country';
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
            $columnWidths[$column++] = 25; // Name
        }
        if (in_array('country', $this->columns)) {
            $columnWidths[$column++] = 25; // Country
        }
        if (in_array('status', $this->columns)) {
            $columnWidths[$column++] = 12; // Status
        }

        foreach ($columnWidths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}