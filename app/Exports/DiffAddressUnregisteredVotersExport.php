<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;

class DiffAddressUnregisteredVotersExport implements FromCollection, WithHeadings, WithStyles
{
    protected $unregisteredVoters;
    protected $request;
    protected $columns;

    public function __construct($unregisteredVoters, $request, $columns)
    {
        $this->unregisteredVoters = $unregisteredVoters;
        $this->request = $request;
        $this->columns = $columns;
    }

    /**
     * @return Collection
     */
    public function collection()
    {
        return $this->unregisteredVoters->map(function ($voter) {
            $row = array_fill_keys($this->columns, ''); // Initialize with empty values

            foreach ($this->columns as $column) {
                switch ($column) {
                    case 'id':
                        $row['id'] = $voter->id;
                        break;

                    case 'surveyer constituency':
                        $row['surveyer constituency'] = $voter->surveyerConstituency->name ?? '';
                        break;
                    case 'living constituency':
                        $row['living constituency'] = $voter->livingConstituency->name ?? '';
                        break;

                    case 'first name':
                        $row['first name'] = $voter->first_name;
                        break;
                    case 'last name':
                        $row['last name'] = $voter->last_name;
                        break;
                    case 'constituency':
                        $row['constituency'] = $voter->living_constituency;
                        break;
                    case 'constituency name':
                        $row['constituency name'] = $voter->living_constituency_name;
                        break;
                    case 'email':
                        $row['email'] = $voter->new_email;
                        break;
                    case 'gender':
                        $row['gender'] = $voter->gender;
                        break;
                    case 'date of birth':
                        $row['date of birth'] = $voter->date_of_birth;
                        break;
                    case 'phone number':
                        $row['phone number'] = $voter->phone_number;
                        break;
                    case 'address':
                        $row['address'] = $voter->new_address;
                        break;
                    case 'ref by voter id':
                        $row['ref by voter id'] = $voter->voter_id;
                        break;
                    case 'ref by first name':
                        $row['ref by first name'] = $voter->voter->first_name ?? '';
                        break;
                    case 'ref by second name':
                        $row['ref by second name'] = $voter->voter->second_name ?? '';
                        break;
                    case 'ref by last name':
                        $row['ref by last name'] = $voter->voter->surname ?? '';
                        break;
                    case 'survey id':
                        $row['survey id'] = $voter->survey_id;
                        break;
                    case 'note':
                        $row['note'] = $voter->note;
                        break;
                    case 'created at':
                        $row['created at'] = $voter->created_at instanceof \Carbon\Carbon ? 
                            $voter->created_at->format('Y-m-d H:i:s') : $voter->created_at;
                        break;
                    case 'updated at':
                        $row['updated at'] = $voter->updated_at instanceof \Carbon\Carbon ? 
                            $voter->updated_at->format('Y-m-d H:i:s') : $voter->updated_at;
                        break;
                }
            }
            return $row;
        });
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return array_map('ucwords', $this->columns);
    }

    /**
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        $columnWidths = [];

        foreach ($this->columns as $index => $column) { 
            $letter = chr(65 + $index);
            switch ($column) {
                case 'id':
                    $columnWidths[$letter] = 10;
                    break;
                case 'first name':
                case 'last name':
                case 'ref by first name':
                case 'ref by second name':
                case 'ref by last name':
                    $columnWidths[$letter] = 30;
                    break;
                case 'constituency':
                case 'constituency name':
                    $columnWidths[$letter] = 20;
                    break;
                case 'phone number':
                case 'ref by voter id':
                case 'survey id':
                    $columnWidths[$letter] = 15;
                    break;
                case 'address':
                case 'email':
                case 'note':
                    $columnWidths[$letter] = 40; 
                    break;
                case 'created at':
                case 'updated at':
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