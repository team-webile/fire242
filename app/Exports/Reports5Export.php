<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\ParameterBag;

class Reports5Export implements FromCollection, WithHeadings, WithStyles
{
    protected $results;
    protected $request;
    protected $columns;
    protected $parties;

    public function __construct($results, $request, $columns, $parties = null)
    {
        if ($columns instanceof ParameterBag) {
            $columns = $columns->all();
        }
        if (!is_array($columns)) {
            $columns = [$columns];
        }
        if (empty($columns)) {
            throw new \Exception('No columns specified for export');
        }
        $this->results = $results;
        $this->request = $request;
        $this->columns = $columns;
        $this->parties = $parties ?? collect();
    }

    public function collection()
    {
        $collection = [];
        foreach ($this->results as $row) {
            $dataRow = [];
            foreach ($this->columns as $column) {
                $col = strtolower(trim($column));
                switch ($col) {
                    case 'polling division':
                    case 'polling':
                        $dataRow[] = $row['polling_division'] ?? '';
                        break;
                    case 'constituency id':
                        $dataRow[] = $row['constituency_id'] ?? '';
                        break;
                    case 'constituency name':
                        $dataRow[] = $row['constituency_name'] ?? '';
                        break;
                    case 'total voters':
                        $dataRow[] = $row['total_voters'] ?? 0;
                        break;
                    case 'surveyed voters':
                        $dataRow[] = $row['surveyed_voters'] ?? 0;
                        break;
                    case 'not surveyed':
                        $dataRow[] = $row['not_surveyed_voters'] ?? 0;
                        break;
                    case 'surveyed %':
                        $pct = $row['surveyed_percentage'] ?? 0;
                        $dataRow[] = is_numeric($pct) ? $pct . '%' : $pct;
                        break;
                    default:
                        if (str_ends_with($col, ' %') && isset($row['parties'])) {
                            $partyKey = trim(str_replace(' %', '', $column));
                            $found = null;
                            foreach ($row['parties'] as $shortName => $data) {
                                if (strtolower(str_replace('-', '_', $shortName)) === strtolower(str_replace('-', '_', $partyKey))) {
                                    $found = $data;
                                    break;
                                }
                            }
                            $dataRow[] = $found !== null ? ($found['percentage'] ?? 0) . '%' : '0.00%';
                        } else {
                            $dataRow[] = '';
                        }
                }
            }
            $collection[] = $dataRow;
        }
        return collect($collection);
    }

    public function headings(): array
    {
        if (empty($this->columns)) {
            return [];
        }
        return array_map(function ($column) {
            return ucwords(trim($column));
        }, $this->columns);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
