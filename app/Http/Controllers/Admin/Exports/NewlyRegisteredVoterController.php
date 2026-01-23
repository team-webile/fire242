<?php

namespace App\Http\Controllers\Admin\Exports;

use App\Http\Controllers\Controller;
use App\Models\Voter;
use Illuminate\Http\Request;
use App\Models\UnregisteredVoter;
use App\Models\Survey;
use App\Exports\NewlyRegisteredVoterExport;
use Maatwebsite\Excel\Facades\Excel;

class NewlyRegisteredVoterController extends Controller 
{
   
   public function getNewlyRegisteredVoters(Request $request)
   {
       
        // Check if user is authenticated and has admin role
        if (!auth()->check() || auth()->user()->role->name !== 'Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }

        $query = Voter::query()
        ->select('voters.*', 'constituencies.name as constituency_name')
        ->join('constituencies', 'voters.const', '=', 'constituencies.id');


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

        if ($existsInDatabase === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false);
        }


        if ($underAge25 === 'yes') {
            $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
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

        // Get paginated results
        $voters = $query->orderBy('id', 'desc')->where('newly_registered', 1)->get();
               
        $columns = array_map(function($column) {
            return strtolower(urldecode(trim($column)));
        }, explode(',', $_GET['columns']));
        //dd($columns); 
        $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
        return Excel::download(new NewlyRegisteredVoterExport($voters, $request, $columns), 'Newly Registered Voters_' . $timestamp . '.xlsx');  


    }


}