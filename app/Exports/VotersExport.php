<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VotersExport implements FromCollection, WithHeadings, WithStyles
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
            if (in_array('cell phone number', $this->columns)) {
                $row['Cell Phone number'] = ($voter->cell_phone_code && $voter->cell_phone) ? $voter->cell_phone_code . ' ' . $voter->cell_phone : 'N/A' ?? 'N/A';
            }
            if (in_array('surveyed by', $this->columns)) {
                $row['Surveyed By'] = isset($voter->user) ? $voter->user->name : null;
            }
            if (in_array('surveyed by email', $this->columns)) {
                $row['Surveyed By Email'] = isset($voter->user) ? $voter->user->email : null;
            }
            if (in_array('date of birth', $this->columns)) {
                $row['Date of Birth'] = $voter->dob;
            }
            if (in_array('house number', $this->columns)) {
                $row['House Number'] = $voter->house_number;
            }
            if (in_array('address', $this->columns)) {
                $row['Address'] = $voter->address;
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
            
            if (in_array('located', $this->columns)) {
                $row['Located'] = $voter->located;
            }
            if (in_array('voting decision', $this->columns)) {
                $row['Voting Decision'] = $voter->voting_decision;
            }
            if (in_array('voting for', $this->columns)) {
                $row['Voting For'] = $voter->voting_for;
            }
           
            if (in_array('surveyed date', $this->columns)) {
                $row['Surveyed Date'] = $voter->survey_date;
            }
            // New column: celll phone (intentional spelling to match request)
            
            
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
                case 'constituency': $headers[] = 'Constituency'; break;
                case 'constituency name': $headers[] = 'Constituency Name'; break;
                case 'first name': $headers[] = 'First Name'; break;
                case 'second name': $headers[] = 'Second Name'; break;
                case 'last name': $headers[] = 'Last Name'; break;
                case 'cell phone number': $headers[] = 'Cell Phone number'; break;
                case 'surveyed by': $headers[] = 'Surveyed By'; break;
                case 'surveyed by email': $headers[] = 'Surveyed By Email'; break;
                case 'date of birth': $headers[] = 'Date of Birth'; break;
                case 'house number': $headers[] = 'House Number'; break;
                case 'address': $headers[] = 'Address'; break;
                case 'aptno': $headers[] = 'Apt No'; break;
                case 'blkno': $headers[] = 'Block No'; break;
                case 'pobcn': $headers[] = 'POBCN'; break;
                case 'pobse': $headers[] = 'POBSE'; break;
                case 'polling': $headers[] = 'Polling'; break;
                case 'located': $headers[] = 'Located'; break;
                case 'voting decision': $headers[] = 'Voting Decision'; break;
                case 'voting for': $headers[] = 'Voting For'; break;
                case 'special comments': $headers[] = 'Special Comments'; break;
                case 'other comments': $headers[] = 'Other Comments'; break;
                case 'surveyed date': $headers[] = 'Surveyed Date'; break;
               
              
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
                case 'aptno':
                case 'blkno': 
                case 'pobcn':
                case 'pobse':
                case 'located':
                case 'voting decision':
                case 'voting_for':
                    $columnWidths[$letter] = 15;
                    break;
                case 'surveyed by':
                case 'surveyed by email':
                    $columnWidths[$letter] = 25;
                    break;
                case 'surveyed date':
                    $columnWidths[$letter] = 20;
                    break;
                case 'cell phone number':
                    $columnWidths[$letter] = 20;
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