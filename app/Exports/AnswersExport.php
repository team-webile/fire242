<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AnswersExport implements FromCollection, WithHeadings, WithStyles
{
    protected $answers;
    protected $request;

    public function __construct($answers, $request)
    {
        $this->answers = $answers;
        $this->request = $request;
    }

    public function collection()
    {
        return $this->answers->map(function ($answer) {
            return [
                'ID' => $answer->id,
                'Answer' => $answer->answer
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ID',
            'Answer' 
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $columnWidths = [
            'A' => 10, // ID
            'B' => 30, // Question
        ];

        foreach ($columnWidths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        return [
            1 => ['font' => ['bold' => true]],
        ]; 
    }
}