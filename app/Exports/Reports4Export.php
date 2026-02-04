<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\ParameterBag;

class Reports4Export implements FromCollection, WithHeadings, WithStyles
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
        
        // Filter out empty columns
        $columns = array_filter($columns, function($col) {
            return !empty(trim($col));
        });
        
        // Default columns if none specified
        if (empty($columns)) {
            $columns = ['polling division','total voters in polling',
             'fnm', 'plp', 'coi', 'other', 'vote not surveyed', 'subtotal', 'fnm %', 'plp %', 'coi %', 'other %', 'no vote %'];
        }
        
        $this->results = $results;
        $this->request = $request;
        $this->columns = $columns;
    }

    public function collection()
    {
        $collection = [];
        
        // Add data rows
        foreach ($this->results as $row) {
            $dataRow = [];
            foreach ($this->columns as $column) {
                switch (strtolower(trim($column))) {
                    case 'polling division':
                    case 'polling':
                        $dataRow[] = $row->polling_division ?? '';
                        break;
                    case 'total voters in polling':
                        $dataRow[] = $row->total_voters ?? 0;
                        break;
                    case 'fnm':
                    case 'fnm count':
                        $dataRow[] = $row->fnm_count ?? 0;
                        break;
                    case 'plp':
                    case 'plp count':
                        $dataRow[] = $row->plp_count ?? 0;
                        break;
                    case 'coi':
                    case 'coi count':
                        $dataRow[] = $row->coi_count ?? 0;
                        break;
                    case 'other':
                    case 'other count':
                        $dataRow[] = $row->other_count ?? 0;
                        break;
                    case 'vote not surveyed':
                    case 'vote not surveyed count':
                        $dataRow[] = $row->no_vote_count ?? 0; 
                        break;
                    case 'subtotal':
                    case 'subtotal count':
                        $dataRow[] = $row->total_party_count ?? 0;
                        break;
                    case 'total party':
                    case 'total party count':
                        $dataRow[] = $row->total_party_count ?? 0;
                        break;
                    case 'fnm %':
                    case 'fnm percentage':
                        $dataRow[] = ($row->fnm_percentage ?? 0) . '%';
                        break;
                    case 'plp %':
                    case 'plp percentage':
                        $dataRow[] = ($row->plp_percentage ?? 0) . '%';
                        break;
                    case 'coi %':
                    case 'coi percentage':
                        $dataRow[] = ($row->coi_percentage ?? 0) . '%';
                        break;
                    case 'other %':
                    case 'other percentage':
                        $dataRow[] = ($row->other_percentage ?? 0) . '%';
                        break;
                    case 'no vote %':
                    case 'no vote percentage':
                        $dataRow[] = ($row->no_vote_percentage ?? 0) . '%';
                        break;
                    default:
                        $dataRow[] = '';
                }
            }
            $collection[] = $dataRow;
        }

        // Add totals row at the end - only count columns get totals, percentages are blank
        $totalsRow = [];
        foreach ($this->columns as $column) {
            switch (strtolower(trim($column))) {
                case 'polling division':
                case 'polling':
                    $totalsRow[] = 'TOTALS';
                    break;
                case 'total voters in polling':
                    $totalsRow[] = $this->results->sum('total_voters');
                    break;
                case 'fnm':
                case 'fnm count':
                    $totalsRow[] = $this->results->sum('fnm_count');
                    break;
                case 'plp':
                case 'plp count':
                    $totalsRow[] = $this->results->sum('plp_count');
                    break;
                case 'coi':
                case 'coi count':
                    $totalsRow[] = $this->results->sum('coi_count');
                    break;
                case 'other':
                case 'other count':
                    $totalsRow[] = $this->results->sum('other_count');
                    break;
                case 'vote not surveyed':
                case 'vote not surveyed count':
                case 'no vote':
                case 'no vote count':
                    $totalsRow[] = $this->results->sum('no_vote_count');
                    break;
                case 'subtotal':
                case 'subtotal count':
                    $totalsRow[] = $this->results->sum('total_party_count');
                    break;
                case 'total count':
                case 'total':
                case 'total voters':
                    $totalsRow[] = $this->results->sum('total_count');
                    break;
                case 'total party':
                case 'total party count':
                    $totalsRow[] = $this->results->sum('total_party_count');
                    break;
                // Percentage columns - leave blank in totals row
                case 'fnm %':
                case 'fnm percentage':
                case 'plp %':
                case 'plp percentage':
                case 'coi %':
                case 'coi percentage':
                case 'dna %':
                case 'dna percentage':
                case 'other %':
                case 'other percentage':
                case 'no vote %':
                case 'no vote percentage':
                    $totalsRow[] = '';
                    break;
                default:
                    $totalsRow[] = '';
            }
        }
        $collection[] = $totalsRow;

        return collect($collection);
    }

    public function headings(): array
    {
        if (empty($this->columns)) {
            return [];
        }

        return array_map(function($column) {
            return ucwords(trim($column));
        }, $this->columns);
    }

    public function styles(Worksheet $sheet)
    {
        $columnWidths = [
            'A' => 18, // polling division
            'B' => 12, // fnm
            'C' => 12, // plp  
            'D' => 12, // coi
            'E' => 12, // other
            'F' => 12, // no vote
            'G' => 12, // total
            'H' => 12, // fnm %
            'I' => 12, // plp %
            'J' => 12, // coi %
            'K' => 12, // other %
            'L' => 12, // no vote %
        ];

        foreach ($columnWidths as $column => $width) {
            $colIndex = ord($column) - 65;
            if (isset($this->columns[$colIndex])) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }
        }

        // Get the last row number (header + data rows + totals row)
        $lastRow = count($this->results) + 2; // +1 for header, +1 for totals

        return [
            1 => ['font' => ['bold' => true]], // Header row bold
            $lastRow => ['font' => ['bold' => true]], // Totals row bold
        ];
    }
}
