<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ElectionDayReportOneExport implements FromCollection, WithHeadings, WithStyles
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

            foreach ($this->columns as $column) {
                switch (strtolower($column)) {
                    case 'id':
                        $row['ID'] = $voter->voter_table_id;
                        break;
                    case 'constituency':
                        $row['Constituency'] = $voter->const;
                        break;
                    case 'constituency name':
                        $row['Constituency Name'] = $voter->constituency_name;
                        break;
                    case 'polling':
                        $row['Polling'] = $voter->polling;
                        break;
                    case 'voter id':
                        $row['Voter ID'] = $voter->voter;
                        break;
                    case 'first name':
                        $row['First Name'] = $voter->first_name;
                        break;
                    case 'second name':
                        $row['Second Name'] = $voter->second_name;
                        break;
                    case 'last name':
                        $row['Last Name'] = $voter->surname;
                        break;
                    case 'cell phone':
                        $cellPhone = '';
                        if (!empty($voter->cell_phone_code) && !empty($voter->cell_phone)) {
                            $cellPhone = $voter->cell_phone_code . ' ' . $voter->cell_phone;
                        } elseif (!empty($voter->cell_phone)) {
                            $cellPhone = $voter->cell_phone;
                        }
                        $row['Cell Phone'] = $cellPhone;
                        break;
                    case 'home phone':
                        $homePhone = '';
                        if (!empty($voter->home_phone_code) && !empty($voter->home_phone)) {
                            $homePhone = $voter->home_phone_code . ' ' . $voter->home_phone;
                        } elseif (!empty($voter->home_phone)) {
                            $homePhone = $voter->home_phone;
                        }
                        $row['Home Phone'] = $homePhone;
                        break;
                    case 'work phone':
                        $workPhone = '';
                        if (!empty($voter->work_phone_code) && !empty($voter->work_phone)) {
                            $workPhone = $voter->work_phone_code . ' ' . $voter->work_phone;
                        } elseif (!empty($voter->work_phone)) {
                            $workPhone = $voter->work_phone;
                        }
                        $row['Work Phone'] = $workPhone;
                        break;
                    case 'voting decision':
                        $row['Voting Decision'] = $voter->voting_for ?? '';
                        break;
                    case 'voting day decision':
                        $row['Voting Day Decision'] = $voter->exit_poll ?? '';
                        break;
                    case 'date of birth':
                        $row['Date of Birth'] = $voter->dob;
                        break;
                    case 'pobcn':
                        $row['POBCN'] = $voter->pobcn;
                        break;
                    case 'pobis':
                        $row['POBIS'] = $voter->pobis;
                        break;
                    case 'pobse':
                        $row['POBSE'] = $voter->pobse;
                        break;
                    case 'house number':
                        $row['House Number'] = $voter->house_number;
                        break;
                    case 'aptno':
                        $row['Apt No'] = $voter->aptno;
                        break;
                    case 'blkno':
                        $row['Block No'] = $voter->blkno;
                        break;
                    case 'address':
                        $row['Address'] = $voter->address;
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
                case 'constituency': $headers[] = 'Constituency'; break;
                case 'constituency name': $headers[] = 'Constituency Name'; break;
                case 'polling': $headers[] = 'Polling'; break;
                case 'voter id': $headers[] = 'Voter ID'; break;
                case 'first name': $headers[] = 'First Name'; break;
                case 'second name': $headers[] = 'Second Name'; break;
                case 'last name': $headers[] = 'Last Name'; break;
                case 'cell phone': $headers[] = 'Cell Phone'; break;
                case 'home phone': $headers[] = 'Home Phone'; break;
                case 'work phone': $headers[] = 'Work Phone'; break;
                case 'voting decision': $headers[] = 'Voting Decision'; break;
                case 'voting day decision': $headers[] = 'Voting Day Decision'; break;
                case 'date of birth': $headers[] = 'Date of Birth'; break;
                case 'pobcn': $headers[] = 'POBCN'; break;
                case 'pobis': $headers[] = 'POBIS'; break;
                case 'pobse': $headers[] = 'POBSE'; break;
                case 'house number': $headers[] = 'House Number'; break;
                case 'aptno': $headers[] = 'Apt No'; break;
                case 'blkno': $headers[] = 'Block No'; break;
                case 'address': $headers[] = 'Address'; break;
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
                case 'polling':
                case 'constituency':
                case 'house number':
                case 'aptno':
                case 'blkno':
                    $columnWidths[$letter] = 10;
                    break;
                case 'first name':
                case 'second name':
                case 'last name':
                case 'pobcn':
                case 'pobis':
                case 'pobse':
                    $columnWidths[$letter] = 20;
                    break;
                case 'cell phone':
                case 'home phone':
                case 'work phone':
                    $columnWidths[$letter] = 20;
                    break;
                case 'constituency name':
                case 'address':
                    $columnWidths[$letter] = 40;
                    break;
                case 'voting decision':
                case 'voting day decision':
                    $columnWidths[$letter] = 20;
                    break;
                case 'date of birth':
                    $columnWidths[$letter] = 15;
                    break;
                default:
                    $columnWidths[$letter] = 20;
                    break;
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