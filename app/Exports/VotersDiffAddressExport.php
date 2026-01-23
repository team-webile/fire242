<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VotersDiffAddressExport implements FromCollection, WithHeadings, WithStyles
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
            $row = array_fill_keys($this->columns, ''); // Initialize array with empty values for all columns

            foreach ($this->columns as $column) {
                switch (strtolower($column)) {
                    case 'id':
                        $row['id'] = $voter->id;
                        break;
                    case 'constituency':
                        $row['constituency'] = $voter->const;
                        break;
                    case 'new constituency':
                        $row['new constituency'] = $voter->living_constituency;
                        break;
                    case 'constituency name':
                        $row['constituency name'] = $voter->constituency_name;
                        break;
                    case 'new constituency name':
                        $row['new constituency name'] = $voter->new_constituency_name;
                        break;
                    case 'polling':
                        $row['polling'] = $voter->polling;
                        break;
                    case 'voter id':
                        $row['voter id'] = $voter->voter;
                        break;
                    case 'first name':
                        $row['first name'] = $voter->first_name;
                        break;
                    case 'second name':
                        $row['second name'] = $voter->second_name;
                        break;
                    case 'last name':
                        $row['last name'] = $voter->surname;
                        break;
                    case 'date of birth':
                        $row['date of birth'] = $voter->dob;
                        break;
                    case 'pobcn':
                        $row['pobcn'] = $voter->pobcn;
                        break;
                    case 'pobis':
                        $row['pobis'] = $voter->pobis;
                        break;
                    case 'pobse':
                        $row['pobse'] = $voter->pobse;
                        break;
                    case 'house number':
                        $row['house number'] = $voter->house_number;
                        break;
                    case 'aptno':
                        $row['aptno'] = $voter->aptno;
                        break;
                    case 'blkno':
                        $row['blkno'] = $voter->blkno;
                        break;
                    case 'address':
                        $row['address'] = $voter->address;
                        break;
                }
            }
            return $row;
        });
    }

    public function headings(): array
    {
        $headers = [];
        foreach ($this->columns as $column) {
            // Convert column names to title case for headers
            $headers[] = ucwords($column);
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
                case 'new constituency name':
                case 'address':
                    $columnWidths[$letter] = 40;
                    break;
                case 'house number':
                case 'pobcn':
                case 'pobse':
                case 'pobis':
                    $columnWidths[$letter] = 15;
                    break;
                case 'created at':
                    $columnWidths[$letter] = 20;
                    break;
                case 'latitude':
                case 'longitude':
                    $columnWidths[$letter] = 12;
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