<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithTitle;

class VoterCardsReportExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected $data;
    protected $columns;
    protected $request;

    public function __construct($data, $request, $columns)
    {
        $this->data = $data;
        $this->columns = $columns;
        $this->request = $request;
    }

    public function collection()
    {
        return $this->data->map(function ($row) {
            $exportRow = [];
            
            foreach ($this->columns as $column) {
                $column = strtolower(trim($column));
                
                switch ($column) {
                    case 'polling division':
                    case 'polling_division':
                        $exportRow[] = $row->polling_division ?? '';
                        break;
                    case 'fnm count':
                    case 'fnm_count':
                        $exportRow[] = $row->fnm_count ?? '0';
                        break;
                    case 'plp count':
                    case 'plp_count':
                        $exportRow[] = $row->plp_count ?? '0';
                        break;
                    case 'dna count':
                    case 'dna_count':
                        $exportRow[] = $row->dna_count ?? '0';
                        break;
                    case 'other count':
                    case 'other_count':
                        $exportRow[] = $row->other_count ?? '0';
                        break;
                    case 'no vote count':
                    case 'no_vote_count':
                        $exportRow[] = $row->no_vote_count ?? '0';
                        break;
                    case 'total count':
                    case 'total_count':
                        $exportRow[] = $row->total_count ??'0';
                        break;
                    case 'fnm %':
                    case 'fnm_percentage':
                        $exportRow[] = $row->fnm_percentage . '%';
                        break;
                    case 'plp %':
                    case 'plp_percentage':
                        $exportRow[] = $row->plp_percentage . '%';
                        break;
                    case 'dna %':
                    case 'dna_percentage':
                        $exportRow[] = $row->dna_percentage . '%';
                        break;
                    case 'other %':
                    case 'other_percentage':
                        $exportRow[] = $row->other_percentage . '%';
                        break;
                    case 'no vote %':
                    case 'no_vote_percentage':
                        $exportRow[] = $row->no_vote_percentage . '%';
                        break;
                    default:
                        $exportRow[] = '';
                }
            }
            
            return $exportRow;
        });
    }

    public function headings(): array
    {
        return array_map(function ($column) {
            return strtoupper(str_replace('_', ' ', $column));
        }, $this->columns);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E8E8E8']
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20,
            'B' => 15,
            'C' => 15,
            'D' => 15,
            'E' => 15,
            'F' => 15,
            'G' => 15,
            'H' => 15,
            'I' => 15,
            'J' => 15,
            'K' => 15,
            'L' => 15,
        ];
    }

    public function title(): string
    {
        return 'Voter Cards Report';
    }
}

