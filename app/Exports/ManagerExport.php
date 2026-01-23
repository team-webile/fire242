<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ManagerExport implements FromCollection, WithHeadings, WithStyles
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
            
            if (in_array('id', $this->columns)) {
                $row['ID'] = $user->id;
            }
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
            if (in_array('type', $this->columns)) {
                $row['Type'] = $user->is_coordinator == 1 ? 'Coordinator' : 'Manager';
            }
            if (in_array('status', $this->columns)) {
                $row['Status'] = $user->is_active ? 'Active' : 'Inactive';
            }
            if (in_array('time management', $this->columns)) {
                $row['Time Management'] = $user->time_active == 1 ? 'Yes' : 'No';
            }
            if (in_array('created at', $this->columns)) {
                $row['Created At'] = $user->created_at;
            }
            if (in_array('updated', $this->columns)) {
                $row['Updated'] = $user->updated_at;
            }
            
            return $row;
        });
    }

    public function headings(): array
    {
        $headers = [];
        
        if (in_array('id', $this->columns)) {
            $headers[] = 'ID';
        }
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
        if (in_array('type', $this->columns)) {
            $headers[] = 'Type';
        }
        if (in_array('status', $this->columns)) {
            $headers[] = 'Status';
        }
        if (in_array('time management', $this->columns)) {
            $headers[] = 'Time Management';
        }
        if (in_array('created at', $this->columns)) {
            $headers[] = 'Created At';
        }
        if (in_array('updated', $this->columns)) {
            $headers[] = 'Updated';
        }

        return $headers;
    }

    public function styles(Worksheet $sheet)
    {
        $columnWidths = [];
        $column = 'A';
        
        if (in_array('id', $this->columns)) {
            $columnWidths[$column++] = 10; // ID
        }
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
        if (in_array('type', $this->columns)) {
            $columnWidths[$column++] = 15; // Type
        }
        if (in_array('status', $this->columns)) {
            $columnWidths[$column++] = 12; // Status
        }
        if (in_array('time management', $this->columns)) {
            $columnWidths[$column++] = 20; // Time Management
        }
        if (in_array('created at', $this->columns)) {
            $columnWidths[$column++] = 20; // Created At
        }
        if (in_array('updated', $this->columns)) {
            $columnWidths[$column++] = 20; // Updated
        }

        foreach ($columnWidths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        return [
            1 => ['font' => ['bold' => true]], 
        ];
    }
}