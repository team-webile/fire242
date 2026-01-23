<?php

namespace App\Http\Controllers\Manager\Exports;

use App\Http\Controllers\Controller;
use App\Models\Voter;
use Illuminate\Http\Request;
use App\Models\UnregisteredVoter;
use App\Models\Survey;
use App\Exports\UpcomingBirthdaysExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
class ManagerUpcomingBirthdaysController extends Controller 
{
   
   public function getUpcomingBirthdays(Request $request)
   {
        $const = auth()->user()->constituency_id;
        $constituency_id = explode(',', $const);
        $query = Voter::whereIn('const', $constituency_id);

        $searchableFields = [
            'first_name' => 'First Name',
            'second_name' => 'Second Name',
            'surname' => 'Surname', 
            'address' => 'Address',
            'voter' => 'Voter ID',
            'const' => 'Constituency ID',
            'constituency_name' => 'Constituency Name'
        ];  
        
        $perPage = $request->input('per_page', 10);

        // Get search parameters
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $month = $request->input('month');
        $const = $request->input('const');
        $surname = $request->input('surname');
        $firstName = $request->input('first_name');
        $secondName = $request->input('second_name');
        $address = $request->input('address');

        $voterId = $request->input('voter_id');
        $constituencyName = $request->input('constituency_name');
        $constituencyId = $request->input('const');
        $underAge25 = $request->input('under_age_25');
        $houseNumber = $request->input('house_number');
        $pobse = $request->input('pobse');
        $pobis = $request->input('pobis');
        $pobcn = $request->input('pobcn');
        $polling = $request->input('polling');
        $isSurveyed = $request->input('is_surveyed');
        $existsInDatabase = $request->input('exists_in_database');

        if ($existsInDatabase === 'true') { 
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false);
        }
        if (!empty($polling)) {
            $query->where('voters.polling', $polling);
        }
        
        $query->where(function($q) use ($houseNumber, $address, $pobse, $pobis, $pobcn) {
            if ($houseNumber !== null && $houseNumber !== '') {
                $q->whereRaw('LOWER(voters.house_number) = ?', [strtolower($houseNumber)]);
            }
            if ($address !== null && $address !== '') {
                $q->WhereRaw('LOWER(voters.address) = ?', [strtolower($address)]);
            }
            if ($pobse !== null && $pobse !== '') {
                $q->WhereRaw('LOWER(voters.pobse) = ?', [strtolower($pobse)]);
            }
            if ($pobis !== null && $pobis !== '') {
                $q->WhereRaw('LOWER(voters.pobis) = ?', [strtolower($pobis)]);
            }
            if ($pobcn !== null && $pobcn !== '') {
                $q->WhereRaw('LOWER(voters.pobcn) = ?', [strtolower($pobcn)]);
            }
        });

        if ($underAge25 === 'yes') {
            $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
        }



        if ($startDate && $endDate) {
            $query->whereRaw("TO_CHAR(dob, 'MM-DD') BETWEEN TO_CHAR(?::date, 'MM-DD') AND TO_CHAR(?::date, 'MM-DD')", 
                [$startDate, $endDate]);
        } elseif ($startDate) {
            $query->whereRaw("TO_CHAR(dob, 'MM-DD') = TO_CHAR(?::date, 'MM-DD')", 
                [$startDate]);
        } else {
            // If no dates specified, get current month birthdays
            $startOfMonth = now()->startOfMonth()->format('Y-m-d');
            $endOfMonth = now()->endOfMonth()->format('Y-m-d');
            $query->whereRaw("TO_CHAR(dob, 'MM-DD') BETWEEN TO_CHAR(?::date, 'MM-DD') AND TO_CHAR(?::date, 'MM-DD')", 
                [$startOfMonth, $endOfMonth]);
        }

        // Apply filters
        if (!empty($const)) {
            $query->where('voters.const', $const);
        }
        if (!empty($surname)) {
            $query->whereRaw('LOWER(voters.surname) LIKE ?', ['%' . strtolower($surname) . '%']);
        }

        if (!empty($firstName)) {
            $query->whereRaw('LOWER(voters.first_name) LIKE ?', ['%' . strtolower($firstName) . '%']);
        }

        if (!empty($secondName)) {
            $query->whereRaw('LOWER(voters.second_name) LIKE ?', ['%' . strtolower($secondName) . '%']);
        }

        if (!empty($address)) {
            $query->whereRaw('LOWER(voters.address) LIKE ?', ['%' . strtolower($address) . '%']);
        }

        if (!empty($voterId) && is_numeric($voterId)) {
            $query->where('voters.voter', $voterId);
        }

        if (!empty($constituencyName)) {
            $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
        }

        if (!empty($constituencyId)) {
            $query->where('voters.const', $constituencyId);
        }

        if (isset($isSurveyed)) {
            if ($isSurveyed) {
                $query->whereExists(function ($query) {
                    $query->select(1)
                          ->from('surveys')
                          ->whereColumn('surveys.voter_id', 'voters.id');
                });
            } else {
                $query->whereNotExists(function ($query) {
                    $query->select(1)
                          ->from('surveys')
                          ->whereColumn('surveys.voter_id', 'voters.id');
                });
            }
        }

        // $voters = $query
        // ->select('voters.*', 'constituencies.name as constituency_name')
        // ->join('constituencies', 'voters.const', '=', 'constituencies.id')
        // ->selectRaw('EXTRACT(DAY FROM dob) as birth_day')
        // ->selectRaw('CASE WHEN EXISTS (
        //     SELECT 1 FROM surveys 
        //     WHERE surveys.voter_id = voters.id
        // ) THEN true ELSE false END as is_surveyed')
        // ->orderByRaw('EXTRACT(MONTH FROM dob), EXTRACT(DAY FROM dob), dob ASC')->get();


        $voters = $query
                ->leftJoin(DB::raw('
                (
                    SELECT DISTINCT ON (voter_id) voter_id, cell_phone_code, cell_phone
                    FROM surveys
                    ORDER BY voter_id, created_at DESC
                ) as surveys
                '), 'voters.id', '=', 'surveys.voter_id')
                ->select(
                    'voters.*',
                    'constituencies.name as constituency_name',
                    'surveys.cell_phone_code',
                    'surveys.cell_phone',
                    DB::raw('
                        CASE WHEN surveys.voter_id IS NOT NULL
                        THEN true ELSE false END as is_surveyed
                    ')
                )
        ->join('constituencies', 'voters.const', '=', 'constituencies.id')
        ->selectRaw('EXTRACT(DAY FROM dob) as birth_day')
        ->selectRaw('CASE WHEN EXISTS (
            SELECT 1 FROM surveys 
            WHERE surveys.voter_id = voters.id
        ) THEN true ELSE false END as is_surveyed')
        ->orderByRaw('EXTRACT(MONTH FROM dob), EXTRACT(DAY FROM dob), dob ASC')->get();
               
        $columns = array_map(function($column) {
            return strtolower(urldecode(trim($column)));
        }, explode(',', $_GET['columns']));
        
        $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
        return Excel::download(
            new UpcomingBirthdaysExport($voters, $request, $columns),
            'Upcoming Birthdays_' . $timestamp . '.xlsx'
        );  



    }


}