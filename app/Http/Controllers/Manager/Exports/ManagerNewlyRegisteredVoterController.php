<?php

namespace App\Http\Controllers\Manager\Exports;

use App\Http\Controllers\Controller;
use App\Models\Voter;
use Illuminate\Http\Request;
use App\Models\UnregisteredVoter;
use App\Models\Survey;
use App\Exports\NewlyRegisteredVoterExport;
use Maatwebsite\Excel\Facades\Excel;

class ManagerNewlyRegisteredVoterController extends Controller 
{
   
   public function getNewlyRegisteredVoters(Request $request)
   {
        
     $constituency_ids = explode(',', auth()->user()->constituency_id);

    $query = Voter::query()
    ->select('voters.*', 'constituencies.name as constituency_name')
    ->join('constituencies', 'voters.const', '=', 'constituencies.id')
    ->whereIn('voters.const', $constituency_ids);

    // Search fields - including all fillable columns
    $searchableFields = [
        'first_name' => 'First Name',
        'second_name' => 'Second Name',
        'surname' => 'Surname', 
        'address' => 'Address',
        'voter' => 'Voter ID',
        'const' => 'Constituency ID',
        'constituency_name' => 'Constituency Name',
        'under_age_25' => 'Under 25',
        'polling' => 'Polling Station',
        'house_number' => 'House Number',
        'pobse' => 'Place of Birth',
        'pobis' => 'Island',
        'pobcn' => 'Country'
    ];  

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
    $type = $request->input('type');
    $existsInDatabase = $request->input('exists_in_database');


    // Apply filters
    if (!empty($type) && $type === 'new') {
        $query->leftJoin('voter_history', 'voters.voter', '=', 'voter_history.voter_id')
              ->whereNull('voter_history.voter_id'); // Ensures no match in voter_history
    }

    if (!empty($type) && $type === 'update') {
        $query->join('voter_history', 'voters.voter', '=', 'voter_history.voter_id');
        $query->where('voters.newly_registered', true);
    }

    
    if (!empty($polling) && is_numeric($polling)) {
        $query->where('voters.polling', $polling);
    }


    if ($underAge25 === 'yes') {
        $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
    }

    // Apply exists_in_database filter
    // Note: exists_in_database is stored as INTEGER (int4) in database, not boolean
    // So we must use integer values (0 or 1) for comparison
    if ($existsInDatabase === 'true' || $existsInDatabase === '1' || $existsInDatabase === 1) {
        $query->where('voters.exists_in_database', 1);
    } elseif ($existsInDatabase === 'false' || $existsInDatabase === '0' || $existsInDatabase === 0) {
        $query->where('voters.exists_in_database', 0);
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

    
    $query->where(function($q) use ($houseNumber, $address, $pobse, $pobis, $pobcn) {
        if ($houseNumber !== null && $houseNumber !== '') {
            $q->whereRaw('LOWER(voters.house_number) = ?', [strtolower($houseNumber)]);
        }
        if ($address !== null && $address !== '') {
            $q->whereRaw('LOWER(voters.address) = ?', [strtolower($address)]);
        }
        if ($pobse !== null && $pobse !== '') {
            $q->whereRaw('LOWER(voters.pobse) = ?', [strtolower($pobse)]);
        }
        if ($pobis !== null && $pobis !== '') {
            $q->whereRaw('LOWER(voters.pobis) = ?', [strtolower($pobis)]);
        }
        if ($pobcn !== null && $pobcn !== '') {
            $q->whereRaw('LOWER(voters.pobcn) = ?', [strtolower($pobcn)]);
        }
    }); 


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
    // Fixed: Use whereRaw with boolean literal directly for PostgreSQL boolean column
    $voters = $query->orderBy('id', 'desc')
        ->whereRaw('voters.newly_registered = true')
        ->get();
               
        $columns = array_map(function($column) {
            return strtolower(urldecode(trim($column)));
        }, explode(',', $_GET['columns']));
        $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
        return Excel::download(new NewlyRegisteredVoterExport($voters, $request, $columns), 'Newly Registered Voters_' . $timestamp . '.xlsx');  


    }


}