<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class QuestionsExport implements FromCollection, WithHeadings, WithStyles
{
    protected $parties;
    protected $request;

    public function __construct($questions, $request)
    {
        $this->questions = $questions;
        $this->request = $request;
    }

    public function collection()
    {
        return $this->questions->map(function ($question) {
            return [
                'ID' => $question->id,
                'Question' => $question->question
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ID',
            'Question' 
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