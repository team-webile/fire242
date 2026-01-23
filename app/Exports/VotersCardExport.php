<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VotersCardExport implements FromCollection, WithHeadings, WithStyles
{
    protected $voters;
    protected $request;
    protected $columns;

    public function __construct($getVoterCard, $request, $columns)
    {
        $this->voters = $getVoterCard;
        $this->request = $request;
        $this->columns = $columns; 
    }

    public function collection()
    { 
        return $this->voters->map(function ($cardvoter) {
            $row = [];
            foreach ($this->columns as $column) {
                switch (strtolower($column)) {
                    case 'id':
                        $row['ID'] = $cardvoter->id;
                        break;
                    case 'voter id':
                        $row['Voter ID'] = $cardvoter->voter->voter;
                        break;
                    case 'constituency':
                        $row['Constituency'] = $cardvoter->voter->constituency->id;
                        break;
                    case 'constituency name':
                        $row['Constituency Name'] = $cardvoter->voter->constituency->name;
                        break;
                    case 'first name':
                        $row['First Name'] = $cardvoter->voter->first_name;
                        break;
                    case 'second name':
                        $row['Second Name'] = $cardvoter->voter->second_name;
                        break;
                    case 'last name':
                        $row['Last Name'] = $cardvoter->voter->surname;
                        break;
                    case 'date of birth':
                        $row['Date of Birth'] = $cardvoter->voter->dob;
                        break;
                    case 'house number':
                        $row['House Number'] = $cardvoter->voter->house_number;
                        break;
                    case 'address':
                        $row['Address'] = $cardvoter->voter->address;
                        break;
                    case 'aptno':
                        $row['Apt No'] = $cardvoter->voter->aptno;
                        break;
                    case 'blkno':
                        $row['Block No'] = $cardvoter->voter->blkno;
                        break;
                    case 'pobcn':
                        $row['POBCN'] = $cardvoter->voter->pobcn;
                        break;
                    case 'pobse':
                        $row['POBSE'] = $cardvoter->voter->pobse;
                        break;
                    case 'polling':
                        $row['Polling'] = $cardvoter->voter->polling;
                        break;
                }
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
                case 'constituency': $headers[] = 'Constituency'; break;
                case 'constituency name': $headers[] = 'Constituency Name'; break;
                case 'first name': $headers[] = 'First Name'; break;
                case 'second name': $headers[] = 'Second Name'; break;
                case 'last name': $headers[] = 'Last Name'; break;
                case 'date of birth': $headers[] = 'Date of Birth'; break;
                case 'house number': $headers[] = 'House Number'; break;
                case 'address': $headers[] = 'Address'; break;
                case 'aptno': $headers[] = 'Apt No'; break;
                case 'blkno': $headers[] = 'Block No'; break;
                case 'pobcn': $headers[] = 'POBCN'; break;
                case 'pobse': $headers[] = 'POBSE'; break;
                case 'polling': $headers[] = 'Polling'; break;
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
                case 'polling':
                    $columnWidths[$letter] = 15;
                    break;
                case 'date of birth':
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