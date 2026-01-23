<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SurveyTargetExport implements FromCollection, WithHeadings, WithStyles
{
    protected $dailySurveyCount;
    protected $request;
    protected $columns;

    public function __construct($dailySurveyCount, $request, $columns)
    {
        $this->dailySurveyCount = $dailySurveyCount;
        $this->request = $request;
        $this->columns = $columns;
    }

    public function collection()
    {
        return $this->dailySurveyCount->map(function ($record) {
            $row = [];
            
            if (in_array('id', $this->columns)) {
                $row['ID'] = $record->id;
            }
            if (in_array('name', $this->columns)) {
                $row['Name'] = $record->user->name;
            }
            if (in_array('email', $this->columns)) {
                $row['Email'] = $record->user->email;
            }
            if (in_array('phone', $this->columns)) {
                $row['Phone'] = $record->user->phone;
            }
            if (in_array('date', $this->columns)) {
                $row['Date'] = $record->date;
            }
            if (in_array('total surveys', $this->columns)) {
                $row['Total Surveys'] = $record->total_surveys;
            }
            if (in_array('completion percentage', $this->columns)) {
                $row['Completion Percentage'] = $record->completion_percentage . '%';
            }
            if (in_array('created at', $this->columns)) {
                $row['Created At'] = $record->created_at;
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
        if (in_array('phone', $this->columns)) {
            $headers[] = 'Phone';
        }
        if (in_array('date', $this->columns)) {
            $headers[] = 'Date';
        }
        if (in_array('total surveys', $this->columns)) {
            $headers[] = 'Total Surveys';
        }
        if (in_array('completion percentage', $this->columns)) {
            $headers[] = 'Completion Percentage';
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
        
        if (in_array('id', $this->columns)) {
            $columnWidths[$column++] = 10; // ID
        }
        if (in_array('name', $this->columns)) {
            $columnWidths[$column++] = 25; // Name
        }
        if (in_array('email', $this->columns)) {
            $columnWidths[$column++] = 35; // Email
        }
        if (in_array('phone', $this->columns)) {
            $columnWidths[$column++] = 15; // Phone
        }
        if (in_array('date', $this->columns)) {
            $columnWidths[$column++] = 15; // Date
        }
        if (in_array('total surveys', $this->columns)) {
            $columnWidths[$column++] = 15; // Total Surveys
        }
        if (in_array('completion percentage', $this->columns)) {
            $columnWidths[$column++] = 20; // Completion Percentage
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