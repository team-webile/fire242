<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UserActivitiesExport implements FromCollection, WithHeadings, WithStyles
{
    protected $activities;
    protected $columns;

    public function __construct($activities, $columns)
    {
        $this->activities = $activities;
        $this->columns = $columns; 
    }

    public function collection()
    { 
        return $this->activities->map(function ($activity) {
            $row = [];
           
            if (in_array('id', $this->columns)) {
                $row['ID'] = $activity['id'];
            }
            if (in_array('name', $this->columns)) {
                $row['Name'] = $activity['causer']['name'] ?? null;
            }
            if (in_array('email', $this->columns)) {
                $row['Email'] = $activity['causer']['email'] ?? null;
            }
            if (in_array('canvasser info', $this->columns)) {
                $row['Canvasser Info'] = $activity['causer'] ? $activity['causer']['name'] . ' (' . $activity['causer']['email'] . ')' : null;
            }
            if (in_array('ip address', $this->columns)) {
                $row['IP Address'] = $activity['properties']['ip'] ?? null;
            }
            if (in_array('user agent', $this->columns)) {
                $row['User Agent'] = $activity['properties']['user_agent'] ?? null;
            }
            if (in_array('login time', $this->columns)) {
                $row['Login Time'] = $activity['created_at'];
            }
            if (in_array('country', $this->columns)) {
                $geo = json_decode($activity['properties']['geo'] ?? '{}', true); 
                $row['Country'] = $geo['country']['names']['en'] ?? null;
            }
            if (in_array('city', $this->columns)) {
                $geo = json_decode($activity['properties']['geo'] ?? '{}', true);
                $row['City'] = $geo['city']['names']['en'] ?? null;
            }
            if (in_array('region', $this->columns)) {
                $geo = json_decode($activity['properties']['geo'] ?? '{}', true);
                $row['Region'] = $geo['subdivisions'][0]['names']['en'] ?? null;
            }
            if (in_array('postal code', $this->columns)) {
                $geo = json_decode($activity['properties']['geo'] ?? '{}', true);
                $row['Postal Code'] = $geo['postal']['code'] ?? null;
            }
            if (in_array('latitude', $this->columns)) {
                $geo = json_decode($activity['properties']['geo'] ?? '{}', true);
                $row['Latitude'] = $geo['location']['latitude'] ?? null;
            }
            if (in_array('longitude', $this->columns)) {
                $geo = json_decode($activity['properties']['geo'] ?? '{}', true);
                $row['Longitude'] = $geo['location']['longitude'] ?? null;
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
                case 'name': $headers[] = 'Name'; break;
                case 'email': $headers[] = 'Email'; break;
                case 'canvasser info': $headers[] = 'Canvasser Info'; break;
                case 'ip address': $headers[] = 'IP Address'; break;
                case 'user agent': $headers[] = 'User Agent'; break;
                case 'login time': $headers[] = 'Login Time'; break;
                case 'country': $headers[] = 'Country'; break;
                case 'city': $headers[] = 'City'; break;
                case 'region': $headers[] = 'Region'; break;
                case 'postal code': $headers[] = 'Postal Code'; break;
                case 'latitude': $headers[] = 'Latitude'; break;
                case 'longitude': $headers[] = 'Longitude'; break;
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
                case 'name':
                case 'email':
                    $columnWidths[$letter] = 15;
                    break;
                case 'description':
                case 'canvasser info':
                case 'user agent':
                case 'country':
                case 'city':
                case 'region':
                case 'postal code':
                case 'latitude':
                case 'longitude':
                    $columnWidths[$letter] = 30;
                    break;
                case 'ip address':
                case 'login time':
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