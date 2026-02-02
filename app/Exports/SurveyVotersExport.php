<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SurveyVotersExport implements FromCollection, WithHeadings, WithStyles
{
    protected $voters;
    protected $request;
    protected $columns;

    public function __construct($voters, $request, $columns)
    {
        $this->voters = $voters;
        $this->request = $request;
        $this->columns = $columns;
    }

    public function collection()
    {    
        return $this->voters->map(function ($voter) {
            
            $row = [];
            
            if (in_array('id', $this->columns)) {
                $row['ID'] = isset($voter->survey_id) ? $voter->survey_id : $voter->id;
            }
            if (in_array('voter id', $this->columns)) {
                $row['Voter ID'] = $voter->voter;
            }
            if (in_array('polling', $this->columns)) {
                $row['Polling'] = $voter->polling;
            }
            if (in_array('constituency', $this->columns)) {
                $row['Constituency'] = $voter->const;
            }
            if (in_array('constituency name', $this->columns)) {
                $row['Constituency Name'] = $voter->constituency_name;
            }
            if (in_array('first name', $this->columns)) {
                $row['First Name'] = $voter->first_name;
            }
            if (in_array('second name', $this->columns)) {
                $row['Second Name'] = $voter->second_name;
            }
            if (in_array('last name', $this->columns)) {
                $row['Last Name'] = $voter->surname;
            }
            if (in_array('voter died', $this->columns)) {
                $row['Voter Died'] = $voter->is_died == '1' ? 'Yes' : 'No';
            }
            if (in_array('died date', $this->columns)) {
                $row['Died Date'] = $voter->died_date;
            }
            if (in_array('surveyed by', $this->columns)) {
                $row['Surveyed By'] = isset($voter->user) ? $voter->user->name : '';
            }
            if (in_array('surveyed by email', $this->columns)) {
                $row['Surveyed By Email'] = isset($voter->user) ? $voter->user->email : '';
            }
            if (in_array('date of birth', $this->columns)) {
                $row['Date of Birth'] = $voter->dob;
            }
            if (in_array('house number', $this->columns)) {
                $row['House Number'] = $voter->house_number;
            }
            if (in_array('email', $this->columns)) {
                $row['Email'] = $voter->email;
            }
            if (in_array('home phone', $this->columns)) {
                $row['Home Phone'] = ($voter->home_phone_code && $voter->home_phone) ? $voter->home_phone_code . ' ' . $voter->home_phone : 'N/A';
            }
            if (in_array('work phone', $this->columns)) {
                $row['Work Phone'] = ($voter->work_phone_code && $voter->work_phone) ? $voter->work_phone_code . ' ' . $voter->work_phone : 'N/A';
            }
            if (in_array('cell phone', $this->columns)) {
                $row['Cell Phone'] = ($voter->cell_phone_code && $voter->cell_phone) ? $voter->cell_phone_code . ' ' . $voter->cell_phone : 'N/A';
            }
            if (in_array('address', $this->columns)) {
                $row['Address'] = $voter->address;
            }
            if (in_array('located', $this->columns)) {
                $row['Located'] = $voter->located;
            }
            if (in_array('voting decision', $this->columns)) {
                $row['Voting Decision'] = $voter->voting_decision;
            }
            if (in_array('voting for', $this->columns)) {
                $row['Voting For'] = $voter->voting_for;
            }
            if (in_array('aptno', $this->columns)) {
                $row['Apt No'] = $voter->aptno;
            }
            if (in_array('blkno', $this->columns)) {
                $row['Block No'] = $voter->blkno;
            }
            if (in_array('pobcn', $this->columns)) {
                $row['POBCN'] = $voter->pobcn;
            }
            if (in_array('pobse', $this->columns)) {
                $row['POBSE'] = $voter->pobse;
            }
            if (in_array('surveyed date', $this->columns)) {
                $row['Surveyed Date'] = $voter->survey_date;
            }


            if (in_array('special comments', $this->columns)) {
                $row['Special Comments'] = $voter->special_comments;
            }
            if (in_array('other comments', $this->columns)) {
                $row['Other Comments'] = $voter->other_comments;
            }

            if (in_array('challenge', $this->columns)) {
                $row['Challenge'] = $voter->challenge;
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
                case 'polling': $headers[] = 'Polling'; break;
                case 'constituency': $headers[] = 'Constituency'; break;
                case 'constituency name': $headers[] = 'Constituency Name'; break;
                case 'first name': $headers[] = 'First Name'; break;
                case 'second name': $headers[] = 'Second Name'; break;
                case 'last name': $headers[] = 'Last Name'; break;
                case 'voter died': $headers[] = 'Voter Died'; break;
                case 'died date': $headers[] = 'Died Date'; break;
                case 'surveyed by': $headers[] = 'Surveyed By'; break;
                case 'surveyed by email': $headers[] = 'Surveyed By Email'; break;
                case 'date of birth': $headers[] = 'Date of Birth'; break;
                case 'house number': $headers[] = 'House Number'; break;
                case 'email': $headers[] = 'Email'; break;
                case 'home phone': $headers[] = 'Home Phone'; break;
                case 'work phone': $headers[] = 'Work Phone'; break;
                case 'cell phone': $headers[] = 'Cell Phone'; break;
                case 'address': $headers[] = 'Address'; break;
                case 'located': $headers[] = 'Located'; break;
                case 'voting decision': $headers[] = 'Voting Decision'; break;
                case 'voting for': $headers[] = 'Voting For'; break;
                case 'aptno': $headers[] = 'Apt No'; break;
                case 'blkno': $headers[] = 'Block No'; break;
                case 'pobcn': $headers[] = 'POBCN'; break;
                case 'pobse': $headers[] = 'POBSE'; break;
                case 'surveyed date': $headers[] = 'Surveyed Date'; break;
                case 'special comments': $headers[] = 'Special Comments'; break;
                case 'other comments': $headers[] = 'Other Comments'; break;
                case 'challenge': $headers[] = 'Challenge'; break;
            }
        }
        return $headers;
    }

    /**
     * Convert column index to Excel column letter (A, B, C, ..., Z, AA, AB, etc.)
     */
    private function getColumnLetter($index)
    {
        $letter = '';
        while ($index >= 0) {
            $letter = chr($index % 26 + 65) . $letter;
            $index = intval($index / 26) - 1;
        }
        return $letter;
    }

    public function styles(Worksheet $sheet)
    {
        $columnWidths = [];
        
        foreach ($this->columns as $index => $column) {
            // Use the proper column letter function
            $letter = $this->getColumnLetter($index);
            
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
                case 'aptno':
                case 'blkno':
                case 'pobcn':
                case 'pobse':
                case 'located':
                case 'voting decision':
                case 'voting for':
                case 'polling':
                case 'constituency':
                    $columnWidths[$letter] = 15;
                    break;
                case 'surveyed by':
                case 'surveyed by email':
                case 'home phone':
                case 'email':
                case 'work phone':
                case 'cell phone':
                    $columnWidths[$letter] = 25;
                    break;
                case 'surveyed date':
                case 'voter died':
                case 'died date':
                case 'date of birth':
                    $columnWidths[$letter] = 20;
                    break;
                case 'special comments':
                case 'other comments':
                case 'challenge':
                    $columnWidths[$letter] = 35;
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