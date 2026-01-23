<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\ParameterBag;

class Reports3Export implements FromCollection, WithHeadings, WithStyles
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
                        $row[] = $constituency['constituency_id'];
                        break;
                    case 'constituency name':
                        $row[] = $constituency['constituency_name'];
                        break;
                    case 'total voters':
                        $row[] = $constituency['total_voters'] ?? 0;
                        break;
                    case 'surveyed voters':
                        $row[] = $constituency['surveyed_voters'] ?? 0;
                        break;
                    case 'not surveyed':
                        $row[] = $constituency['not_surveyed_voters'] ?? 0;
                        break;
                    case 'surveyed %':
                        $row[] = $constituency['surveyed_percentage'] . '%';
                        break;
                    case 'fnm %':
                        $row[] = isset($constituency['parties']['FNM']) ? $constituency['parties']['FNM']['percentage'] . '%' : '0.00%';
                        break;
                    case 'l-fnm %':
                        $row[] = isset($constituency['parties']['L-FNM']) ? $constituency['parties']['L-FNM']['percentage'] . '%' : '0.00%';
                        break;
                    case 'plp %':
                        $row[] = isset($constituency['parties']['PLP']) ? $constituency['parties']['PLP']['percentage'] . '%' : '0.00%';
                        break;
                    case 'dna %':
                        $row[] = isset($constituency['parties']['DNA']) ? $constituency['parties']['DNA']['percentage'] . '%' : '0.00%';
                        break;
                    case 'coi %':
                        $row[] = isset($constituency['parties']['COI']) ? $constituency['parties']['COI']['percentage'] . '%' : '0.00%';
                        break;
                    case 'bcp %':
                        $row[] = isset($constituency['parties']['BCP']) ? $constituency['parties']['BCP']['percentage'] . '%' : '0.00%';
                        break;
                    case 'bdm %':
                        $row[] = isset($constituency['parties']['BDM']) ? $constituency['parties']['BDM']['percentage'] . '%' : '0.00%';
                        break;
                    case 'other %':
                        $row[] = isset($constituency['parties']['Other']) ? $constituency['parties']['Other']['percentage'] . '%' : '0.00%';
                        break;
                    case 'unavailable %':
                        $row[] = isset($constituency['parties']['unavail']) ? $constituency['parties']['unavail']['percentage'] . '%' : '0.00%';
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