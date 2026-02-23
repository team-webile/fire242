<?php

namespace App\Http\Controllers\Manager\Exports;

use App\Http\Controllers\Controller;
use App\Models\Voter;
use Illuminate\Http\Request;
use App\Models\UnregisteredVoter;
use App\Models\Survey;
use App\Exports\DuplicateVoterExport;
use Maatwebsite\Excel\Facades\Excel;

class ManagerDuplicateVoterController extends Controller 
{
   
 

   public function getDuplicateVoters(Request $request)
   {
       
         $const = auth()->user()->constituency_id;
        $constituency_id = explode(',', $const);
        $query = Voter::query()
        ->select('voters.*', 'constituencies.name as constituency_name') 
        ->join('constituencies', 'voters.const', '=', 'constituencies.id') 
        ->whereExists(function ($subquery) {
            $subquery->select(\DB::raw(1))
                ->from('voters as v2')
                ->whereColumn([
                     ['v2.surname', 'voters.surname'],
                     ['v2.first_name', 'voters.first_name'],
                     ['v2.dob', 'voters.dob'],
                     ['v2.second_name', 'voters.second_name'],
                ])
                ->whereColumn('v2.id', '!=', 'voters.id');
        })
        ->whereIn('voters.const', $constituency_id);

        // Get search parameters
        $const = $request->input('const');
        $surname = $request->input('surname');
        $firstName = $request->input('first_name');
        $secondName = $request->input('second_name');
        $address = $request->input('address');
        $voterId = $request->input('voter');
        $constituencyName = $request->input('constituency_name');
        $constituencyId = $request->input('const');
        $underAge25 = $request->input('under_age_25');
        $polling = $request->input('polling');
        $houseNumber = $request->input('house_number');
        $pobse = $request->input('pobse');
        $pobis = $request->input('pobis');
        $pobcn = $request->input('pobcn');
        $existsInDatabase = $request->input('exists_in_database');  
      
        if ($existsInDatabase === 'true') {   
            $query->where('voters.exists_in_database', true); 
             
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false);
        } 


        // Apply filters

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

        // Get paginated results
        $voters = $query->orderBy('first_name')
                ->orderBy('second_name')
                ->orderBy('dob')
                ->get();

        //dd($voters);
        $columns = array_map(function($column) {
            return strtolower(urldecode(trim($column)));
        }, explode(',', $_GET['columns']));
        
        $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
        return Excel::download(
            new DuplicateVoterExport($voters, $request, $columns),
            'Duplicate Voters_' . $timestamp . '.xlsx'
        );  

    }


}