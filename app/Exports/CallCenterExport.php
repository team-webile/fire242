<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CallCenterExport implements FromCollection, WithHeadings, WithStyles
{
    protected $callCenters;
    protected $request;
    protected $columns;

    public function __construct($callCenters, $request, $columns)
    {
        $this->callCenters = $callCenters;
        $this->request = $request;
        $this->columns = $columns;
    }

    public function collection()
    {
        return $this->callCenters->map(function ($callCenter) {
            $row = [];
            $voter = $callCenter->voter;

            if (in_array('id', $this->columns)) {
                $row['ID'] = $callCenter->id;
            }
            if (in_array('voter id', $this->columns)) {
                $row['Voter ID'] = $voter ? $voter->voter : null;
            }
            if (in_array('first name', $this->columns)) {
                $row['First Name'] = $voter ? $voter->first_name : null;
            }
            if (in_array('last name', $this->columns)) {
                $row['Last Name'] = $voter ? $voter->surname : null;
            }
            if (in_array('constituency', $this->columns)) {
                $row['Constituency'] = $voter ? $voter->const : null;
            }
            if (in_array('constituency name', $this->columns)) {
                $row['Constituency Name'] = $voter && $voter->constituency ? $voter->constituency->name : null;
            }
            if (in_array('polling', $this->columns)) {
                $row['Polling'] = $voter ? $voter->polling : null;
            }
            if (in_array('address', $this->columns)) {
                $row['Address'] = $voter ? $voter->address : null;
            }
            if (in_array('caller name', $this->columns)) {
                $row['Caller Name'] = $callCenter->call_center_caller_name;
            }
            if (in_array('caller phone', $this->columns)) {
                $row['Caller Phone'] = $callCenter->call_center_phone;
            }
            if (in_array('caller email', $this->columns)) {
                $row['Caller Email'] = $callCenter->call_center_email;
            }
            if (in_array('date', $this->columns)) {
                $row['Date'] = $callCenter->call_center_date_time;
            }
            if (in_array('voting decision', $this->columns)) {
                $row['Voting Decision'] = $callCenter->call_center_voting_decisions;
            }
            if (in_array('voting for', $this->columns)) {
                $row['Voting For'] = $callCenter->call_center_voting_for;
            }
            if (in_array('follow up', $this->columns)) {
                $row['Follow Up'] = $callCenter->call_center_follow_up;
            }
            if (in_array('candidate follow up', $this->columns)) {
                $row['Candidate Follow Up'] = $callCenter->call_center_follow_up;
            }
            if (in_array('address special concerns', $this->columns)) {
                $row['Address Special Concerns'] = $callCenter->call_center_address_special_concerns;
            }
            if (in_array('voter contacts', $this->columns)) {
                $row['Voter Contacts'] = $callCenter->call_center_list_voter_contacts;
            }
            if (in_array('other comments', $this->columns)) {
                $row['Other Comments'] = $callCenter->call_center_other_comments;
            }
            if (in_array('soliciting volunteers', $this->columns)) {
                $row['Soliciting Volunteers'] = $callCenter->call_center_soliciting_volunteers;
            }
            if (in_array('number called', $this->columns)) {
                $row['Number Called'] = $callCenter->call_center_number_called;
            }
            if (in_array('number of calls', $this->columns)) {
                $row['Number of Calls'] = $callCenter->call_center_number_calls_made;
            }
            if (in_array('created at', $this->columns)) {
                $row['Created At'] = $callCenter->created_at
                    ? (is_object($callCenter->created_at) ? $callCenter->created_at->format('Y-m-d H:i:s') : $callCenter->created_at)
                    : null;
            }

            return $row;
        });
    }

    public function headings(): array
    {
        $headers = [];
        foreach ($this->columns as $column) {
            switch (strtolower(trim($column))) {
                case 'id': $headers[] = 'ID'; break;
                case 'voter id': $headers[] = 'Voter ID'; break;
                case 'first name': $headers[] = 'First Name'; break;
                case 'last name': $headers[] = 'Last Name'; break;
                case 'constituency': $headers[] = 'Constituency'; break;
                case 'constituency name': $headers[] = 'Constituency Name'; break;
                case 'polling': $headers[] = 'Polling'; break;
                case 'address': $headers[] = 'Address'; break;
                case 'caller name': $headers[] = 'Caller Name'; break;
                case 'caller phone': $headers[] = 'Caller Phone'; break;
                case 'caller email': $headers[] = 'Caller Email'; break;
                case 'date': $headers[] = 'Date'; break;
                case 'voting decision': $headers[] = 'Voting Decision'; break;
                case 'voting for': $headers[] = 'Voting For'; break;
                case 'follow up': $headers[] = 'Follow Up'; break;
                case 'candidate follow up': $headers[] = 'Candidate Follow Up'; break;
                case 'address special concerns': $headers[] = 'Address Special Concerns'; break;
                case 'voter contacts': $headers[] = 'Voter Contacts'; break;
                case 'other comments' : $headers[] = 'Other Comments'; break;
                case 'soliciting volunteers': $headers[] = 'Soliciting Volunteers'; break;
                case 'number called': $headers[] = 'Number Called'; break;
                case 'number of calls': $headers[] = 'Number of Calls'; break; 
                case 'created at': $headers[] = 'Created At'; break;
                default: $headers[] = $column;
            }
        }
        return $headers;
    }

    public function styles(Worksheet $sheet)
    {
        $columnCount = count($this->columns);
        for ($i = 0; $i < $columnCount; $i++) {
            $letter = $i < 26 ? chr(65 + $i) : chr(64 + (int)($i / 26)) . chr(65 + ($i % 26));
            $sheet->getColumnDimension($letter)->setWidth(20);
        }
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
