<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithTitle;

class VotersCardResultExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected $voterCardImages;
    protected $columns;
    protected $request;

    public function __construct($voterCardImages, $request, $columns)
    {
        $this->voterCardImages = $voterCardImages;
        $this->columns = $columns;
        $this->request = $request;
    }

    public function collection()
    {
        // Remove 'image' and 'voting card image' from columns if present
        $filteredColumns = array_filter($this->columns, function ($column) {
            $column_lc = strtolower(trim($column));
            return $column_lc !== 'image' && $column_lc !== 'voting card image';
        });

        return $this->voterCardImages->map(function ($row) use ($filteredColumns) {
            $exportRow = [];
            foreach ($filteredColumns as $column) {
                $column_lc = strtolower(trim($column)); 

                switch ($column_lc) {
                    case 'id':
                        $exportRow[] = $row->id ?? '';
                        break;
                    case 'reg_no':
                        $exportRow[] = $row->reg_no ?? '';
                        break;
                    case 'voter id':
                    case 'voter_id':
                        $exportRow[] = $row->voter ? ($row->voter->id ?? '') : '';
                        break;
                    case 'party':
                    case 'exit_poll':
                        $exportRow[] = $row->exit_poll ?? '';
                        break;
                    case 'voter name':
                    case 'voter_name':
                        // Add voter_name from the attribute array/object, if present
                        // Depending on the structure, either object property or array
                        if (isset($row->voter_name)) {
                            $exportRow[] = $row->voter_name;
                        } elseif (is_array($row) && isset($row['voter_name'])) {
                            $exportRow[] = $row['voter_name'];
                        } else {
                            $exportRow[] = '';
                        }
                        break;
                    case 'voter':
                        // Handle 'voter' column - could be voter name or voter id
                        if (isset($row->voter_name)) {
                            $exportRow[] = $row->voter_name;
                        } elseif ($row->voter && isset($row->voter->name)) {
                            $exportRow[] = $row->voter->name;
                        } elseif (is_array($row) && isset($row['voter_name'])) {
                            $exportRow[] = $row['voter_name'];
                        } else {
                            $exportRow[] = '';
                        }
                        break;
                    default:
                        // fallback for any other column, use property if exists
                        if (property_exists($row, $column_lc)) {
                            $exportRow[] = $row->$column_lc;
                        } elseif (is_array($row) && isset($row[$column_lc])) {
                            $exportRow[] = $row[$column_lc];
                        } else {
                            $exportRow[] = '';
                        }
                }
            }
            return $exportRow;
        });
    }

    public function headings(): array
    {
        // Exclude 'image' and 'voting card image' from headings just like in collection()
        return array_map(function ($column) {
            return strtoupper(str_replace('_', ' ', $column));
        }, array_filter($this->columns, function ($column) {
            $column_lc = strtolower(trim($column));
            return $column_lc !== 'image' && $column_lc !== 'voting card image';
        }));
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
