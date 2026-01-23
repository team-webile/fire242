<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\ParameterBag;

class Reports2Export implements FromCollection, WithHeadings, WithStyles
{
    protected $results;
    protected $request;
    protected $columns;

    public function __construct($results, $request, $columns)
    {   
        // Convert ParameterBag to array if needed
        if ($columns instanceof ParameterBag) {
            $columns = $columns->all();
        }
        
        // Ensure columns is an array
        if (!is_array($columns)) {
            $columns = [$columns];
        }
        
        if (empty($columns)) {
            throw new \Exception('No columns specified for export');
        }
        
        $this->results = $results;
        $this->request = $request;
        $this->columns = $columns;
    }

    public function collection()
    {
        $collection = [];
        foreach ($this->results as $constituency) {
            $row = [];
            foreach ($this->columns as $column) {
                switch (strtolower($column)) {
                    case 'constituency id':
                        $row[] = $constituency->constituency_id;
                        break;
                    case 'constituency name':
                        $row[] = $constituency->constituency_name;
                        break;
                    case 'total surveyed':
                        $row[] = $constituency->total_surveyed ?? 0;
                        break;
                    case 'total voters':
                        $row[] = $constituency->total_voters ?? 0;
                        break;
                    case 'percentage':
                        $row[] = $constituency->percentage.'%';
                        break;
                    default:
                        $row[] = '';
                }
            }
            $collection[] = $row;
        }
        return collect($collection);
    }

    public function headings(): array
    {
        if (empty($this->columns)) {
            return [];
        }

        return array_map(function($column) {
            return ucwords($column);
        }, $this->columns);
    }

    public function styles(Worksheet $sheet)
    {
        $columnWidths = [
            'A' => 15, // constituency id
            'B' => 30, // constituency name  
            'C' => 15, // total surveyed
            'D' => 15, // total voters
            'E' => 15, // percentage
        ];

        foreach ($columnWidths as $column => $width) {
            if (isset($this->columns[ord($column) - 65])) { // Only set width if column exists
                $sheet->getColumnDimension($column)->setWidth($width);
            }
        }

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}