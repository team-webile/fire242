<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SurveysExport implements FromCollection, WithHeadings, WithStyles
{
    protected $surveys;
    protected $request;
    protected $columns;

    public function __construct($surveys, $request, $columns)
    {
        $this->surveys = $surveys;
        $this->request = $request;
        $this->columns = $columns; 
        
    }

    public function collection()
    {
        return $this->surveys->map(function ($survey) {
            $row = [];
             
            if (in_array('id', $this->columns)) {
                $row['ID'] = $survey->id;
            }
            if (in_array('voter id', $this->columns)) {
                $row['Voter ID'] = $survey->voter_id;
            }
            if (in_array('sex', $this->columns)) {
                $row['Sex'] = $survey->sex;
            }
            if (in_array('marital status', $this->columns)) {
                $row['Marital Status'] = $survey->marital_status;
            }
            if (in_array('employment type', $this->columns)) {
                $row['Employment Type'] = $survey->employment_type;
            }
            if (in_array('religion', $this->columns)) {
                $row['Religion'] = $survey->religion;
            }
            if (in_array('email', $this->columns)) {
                $row['Email'] = $survey->email;
            }
            if (in_array('special comments', $this->columns)) {
                $row['Special Comments'] = $survey->special_comments;
            }
            if (in_array('voting for', $this->columns)) {
                $row['Voting For'] = $survey->voted_for_party;
            }
            if (in_array('voted in 2017', $this->columns)) {
                $row['Voted in 2017'] = $survey->last_voted;
            }
            if (in_array('where voted in 2017', $this->columns)) {
                $row['Where Voted in 2017'] = $survey->voted_where;
            }
            
            return $row;
        });
    }

    public function headings(): array
    {
        $headers = [];
        foreach($this->columns as $column) {
            switch(strtolower($column)) {
                case 'id': $headers[] = 'ID'; break;
                case 'voter id': $headers[] = 'Voter ID'; break;
                case 'sex': $headers[] = 'Sex'; break;
                case 'marital status': $headers[] = 'Marital Status'; break;
                case 'employment type': $headers[] = 'Employment Type'; break;
                case 'religion': $headers[] = 'Religion'; break;
                case 'email': $headers[] = 'Email'; break;
                case 'special comments': $headers[] = 'Special Comments'; break;
                case 'voting for': $headers[] = 'Voting For'; break;
                case 'voted in 2017': $headers[] = 'Voted in 2017'; break;
                case 'where voted in 2017': $headers[] = 'Where Voted in 2017'; break;
            }
        }
        return $headers;
    }

    public function styles(Worksheet $sheet)
    {
        $columnWidths = [];
        
        foreach ($this->columns as $index => $column) {
            $letter = chr(65 + $index);
            switch (strtolower($column)) {
                case 'id':
                case 'voter id':
                    $columnWidths[$letter] = 10;
                    break;
                case 'sex':
                case 'marital status':
                case 'employment type':
                case 'religion':
                case 'email':
                    $columnWidths[$letter] = 20;
                    break;
                case 'special comments':
                case 'voting for':
                case 'where voted in 2017':
                    $columnWidths[$letter] = 30;
                    break;
                default:
                    $columnWidths[$letter] = 15;
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