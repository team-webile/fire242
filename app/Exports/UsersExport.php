<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UsersExport implements FromCollection, WithHeadings, WithStyles
{
    protected $users;
    protected $request;
    protected $columns;

    public function __construct($users, $request, $columns)
    {
        $this->users = $users;
        $this->request = $request;
        $this->columns = $columns;
    }

    public function collection()
    {
        return $this->users->map(function ($user) {
            $row = [];
            
            if (in_array('name', $this->columns)) {
                $row['Name'] = $user->name;
            }
            if (in_array('email', $this->columns)) {
                $row['Email'] = $user->email;
            }
            if (in_array('phone number', $this->columns)) {
                $row['Phone Number'] = $user->phone;
            }
            if (in_array('address', $this->columns)) {
                $row['Address'] = $user->address;
            }
            if (in_array('constituency', $this->columns)) {
                $row['Constituency'] = $user->constituencies ? $user->constituencies->pluck('name')->implode(', ') : '';
            }
            if (in_array('today survey', $this->columns)) {
                $row['Today Survey'] = $user->daily_survey_count ? $user->daily_survey_count->count : 0;
            }
            if (in_array('daily quota', $this->columns)) {
                $row['Daily Quota'] = $user->daily_survey_count ? $user->daily_survey_count->target : 0;
            }
            if (in_array('total survey', $this->columns)) {
                $row['Total Survey'] = $user->surveys_count;
            }
            if (in_array('status', $this->columns)) {
                $row['Status'] = $user->is_active ? 'Active' : 'Inactive';
            }
            if (in_array('created at', $this->columns)) {
                $row['Created At'] = $user->created_at->format('Y-m-d H:i:s');
            }
            
            return $row;
        });
    }

    public function headings(): array
    {
        $headers = [];
        
        if (in_array('name', $this->columns)) {
            $headers[] = 'Name';
        }
        if (in_array('email', $this->columns)) {
            $headers[] = 'Email';
        }
        if (in_array('phone number', $this->columns)) {
            $headers[] = 'Phone Number';
        }
        if (in_array('address', $this->columns)) {
            $headers[] = 'Address';
        }
        if (in_array('constituency', $this->columns)) {
            $headers[] = 'Constituency';
        }
        if (in_array('today survey', $this->columns)) {
            $headers[] = 'Today Survey';
        }
        if (in_array('daily quota', $this->columns)) {
            $headers[] = 'Daily Quota';
        }
        if (in_array('total survey', $this->columns)) {
            $headers[] = 'Total Survey';
        }
        if (in_array('status', $this->columns)) {
            $headers[] = 'Status';
        }
        if (in_array('created at', $this->columns)) {
            $headers[] = 'Created At';
        }

        return $headers;
    }

    public function styles(Worksheet $sheet)
    {
        $columnWidths = [];
        $column = 'A';
        
        if (in_array('name', $this->columns)) {
            $columnWidths[$column++] = 25; // Name
        }
        if (in_array('email', $this->columns)) {
            $columnWidths[$column++] = 35; // Email
        }
        if (in_array('phone number', $this->columns)) {
            $columnWidths[$column++] = 15; // Phone
        }
        if (in_array('address', $this->columns)) {
            $columnWidths[$column++] = 35; // Address
        }
        if (in_array('constituency', $this->columns)) {
            $columnWidths[$column++] = 30; // Constituency
        }
        if (in_array('today survey', $this->columns)) {
            $columnWidths[$column++] = 15; // Today Survey
        }
        if (in_array('daily quota', $this->columns)) {
            $columnWidths[$column++] = 15; // Daily Quota
        }
        if (in_array('total survey', $this->columns)) {
            $columnWidths[$column++] = 15; // Total Survey
        }
        if (in_array('status', $this->columns)) {
            $columnWidths[$column++] = 15; // Status
        }
        if (in_array('created at', $this->columns)) {
            $columnWidths[$column++] = 20; // Created At
        } 

        foreach ($columnWidths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}