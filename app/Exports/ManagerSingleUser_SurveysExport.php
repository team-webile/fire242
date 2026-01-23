<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ManagerSingleUser_SurveysExport implements FromCollection, WithHeadings, WithStyles
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
            if (in_array('constituency', $this->columns)) {
                $row['Constituency'] = $survey->voter->const;
            }
            if (in_array('constituency name', $this->columns)) {
                $row['Constituency Name'] = $survey->voter->constituency->name;
            }
            if (in_array('voter died', $this->columns)) { 
                $row['Voter Died'] = $survey->is_died == '1' ? 'Yes' : 'No'; 
            }
            if (in_array('died date', $this->columns)) {
                $row['Died Date'] = $survey->died_date;
            }
            if (in_array('polling', $this->columns)) {
                $row['Polling'] = $survey->voter->polling;
            }
            if (in_array('voter id', $this->columns)) {
                $row['Voter ID'] = $survey->voter->voter;
            }
            if (in_array('first name', $this->columns)) {
                $row['First Name'] = $survey->voter->first_name;
            }
            if (in_array('second name', $this->columns)) {
                $row['Second Name'] = $survey->voter->second_name;
            }
            if (in_array('last name', $this->columns)) {
                $row['Last Name'] = $survey->voter->surname;
            }
            if (in_array('date of birth', $this->columns)) {
                $row['Date of Birth'] = $survey->voter->dob;
            }
            if (in_array('pobcn', $this->columns)) {
                $row['POBCN'] = $survey->voter->pobcn;
            }
            if (in_array('pobis', $this->columns)) {
                $row['POBIS'] = $survey->voter->pobis;
            }
            if (in_array('pobse', $this->columns)) {
                $row['POBSE'] = $survey->voter->pobse;
            }
            if (in_array('house number', $this->columns)) {
                $row['House Number'] = $survey->voter->house_number;
            }
            if (in_array('aptno', $this->columns)) {
                $row['Apt No'] = $survey->voter->aptno;
            }
            if (in_array('blkno', $this->columns)) {
                $row['Block No'] = $survey->voter->blkno;
            }
            if (in_array('address', $this->columns)) {
                $row['Address'] = $survey->voter->address;
            }
            if (in_array('voting decision', $this->columns)) {
                $row['Voting Decision'] = $survey->voting_decision;
            }
            if (in_array('voting for', $this->columns)) {
                $row['Voting For'] = $survey->voting_for;
            }
            if (in_array('located', $this->columns)) {
                $row['Located'] = $survey->located;
            }
            if (in_array('created at', $this->columns)) {
                $row['Created At'] = $survey->created_at;
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
                case 'constituency': $headers[] = 'Constituency'; break;
                case 'constituency name': $headers[] = 'Constituency Name'; break;
                case 'voter died': $headers[] = 'Voter Died'; break;
                case 'died date': $headers[] = 'Died Date'; break;
                case 'polling': $headers[] = 'Polling'; break;
                case 'voter id': $headers[] = 'Voter ID'; break;
                case 'first name': $headers[] = 'First Name'; break;
                case 'second name': $headers[] = 'Second Name'; break;
                case 'last name': $headers[] = 'Last Name'; break;
                case 'date of birth': $headers[] = 'Date of Birth'; break;
                case 'pobcn': $headers[] = 'POBCN'; break;
                case 'pobis': $headers[] = 'POBIS'; break;
                case 'pobse': $headers[] = 'POBSE'; break;
                case 'house number': $headers[] = 'House Number'; break;
                case 'aptno': $headers[] = 'Apt No'; break;
                case 'blkno': $headers[] = 'Block No'; break;
                case 'address': $headers[] = 'Address'; break;
                case 'voting decision': $headers[] = 'Voting Decision'; break;
                case 'voting for': $headers[] = 'Voting For'; break;
                case 'located': $headers[] = 'Located'; break;
                case 'created at': $headers[] = 'Created At'; break;
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
                case 'first name':
                case 'second name':
                case 'last name':
                    $columnWidths[$letter] = 30;
                    break;
                case 'constituency name':
                case 'address':
                    $columnWidths[$letter] = 40;
                    break;
                case 'house number':
                case 'pobcn':
                case 'pobis':
                case 'pobse':
                case 'aptno':
                case 'blkno':
                    $columnWidths[$letter] = 15;
                    break;
                case 'voting decision':
                case 'voting for':
                case 'located':
                    $columnWidths[$letter] = 25;
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