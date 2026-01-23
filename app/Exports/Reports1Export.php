<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class Reports1Export implements FromCollection, WithHeadings, WithStyles
{
    protected $results;
    protected $request;
    protected $columns;

    public function __construct($results, $columns, $request)
    {
        $this->results = $results;
        $this->columns = $columns;
        $this->request = $request;
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
                    case 'not surveyed voters':
                        $row[] = $constituency['not_surveyed_voters'] ?? 0;
                        break;
                    case 'surveyed percentage':
                        $row[] = $constituency['surveyed_percentage'] . '%';
                        break;
                    case 'fnm count':
                        $row[] = $constituency['parties']['FNM']['count'] ?? 0;
                        break;
                    case 'fnm percentage':
                        $row[] = ($constituency['parties']['FNM']['percentage'] ?? '0.00') . '%';
                        break;
                    case 'plp count':
                        $row[] = $constituency['parties']['PLP']['count'] ?? 0;
                        break;
                    case 'plp percentage':
                        $row[] = ($constituency['parties']['PLP']['percentage'] ?? '0.00') . '%';
                        break;
                    case 'male count':
                        $row[] = $constituency['gender']['male']['count'] ?? 0;
                        break;
                    case 'male percentage':
                        $row[] = ($constituency['gender']['male']['percentage'] ?? '0.00') . '%';
                        break;
                    case 'female count':
                        $row[] = $constituency['gender']['female']['count'] ?? 0;
                        break;
                    case 'female percentage':
                        $row[] = ($constituency['gender']['female']['percentage'] ?? '0.00') . '%';
                        break;
                }
            }
            $collection[] = $row;
        }
        return collect($collection);
    }

    public function headings(): array
    {
        return array_map('ucwords', $this->columns);
    }

    public function styles(Worksheet $sheet)
    {
        $columnWidths = [];
        
        foreach ($this->columns as $index => $column) {
            $letter = chr(65 + $index);
            switch (strtolower($column)) {
                case 'constituency id':
                    $columnWidths[$letter] = 15;
                    break;
                case 'constituency name':
                    $columnWidths[$letter] = 40;
                    break;
                case 'total voters':
                case 'surveyed voters':
                case 'not surveyed voters':
                    $columnWidths[$letter] = 15;
                    break;
                case 'surveyed percentage':
                    $columnWidths[$letter] = 18;
                    break;
                case 'fnm count':
                case 'plp count':
                case 'male count':
                case 'female count':
                    $columnWidths[$letter] = 12;
                    break;
                case 'fnm percentage':
                case 'plp percentage':
                case 'male percentage':
                case 'female percentage':
                    $columnWidths[$letter] = 15;
                    break;
                default:
                    $columnWidths[$letter] = 20;
            }
        }

        foreach ($columnWidths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}