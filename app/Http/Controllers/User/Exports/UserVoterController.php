<?php

namespace App\Http\Controllers\User\Exports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Voter;
use App\Exports\VotersExport;
use App\Models\Constituency;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
class UserVoterController extends Controller
{ 
  
 
  
public function NotSurveyedExport(Request $request)
{
    try {
        $user = Auth::user();
        // Add validation for constituency_id
        if (empty($user->constituency_id)) {
            return response()->json([
                'success' => false,
                'message' => 'No constituency assigned to user',
                'data' => [],
                'meta' => [
                    'total' => 0,
                    'per_page' => 0,
                    'current_page' => 1,
                    'last_page' => 1,
                ]
            ]);
        }

        $constituency_ids = explode(',', $user->constituency_id);
        $perPage = $request->input('per_page', 20);

        // Get search parameters for each column
        $surname = $request->input('surname');
        $const = $request->input('const');
        $firstName = $request->input('first_name');
        $secondName = $request->input('second_name');
        $address = $request->input('address');
        $voterId = $request->input('voter');
        $constituencyName = $request->input('constituency_name');
        $constituencyId = $request->input('const');
          
        // Query voters who don't have a survey entry
        $query = Voter::select('voters.*', 'constituencies.name as constituency_name')
            ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
            ->whereIn('voters.const', $constituency_ids)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('surveys')
                    ->whereRaw('surveys.voter_id = voters.id')
                    ->where('surveys.user_id', Auth::id());
            }); 

        // Apply individual column filters
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

        // Add sorting
        $query->orderBy('voters.id', 'desc');

        // Get paginated results
        $voters = $query->get();
    
        $columns = array_map(function($column) {
            return strtolower(urldecode(trim($column)));
        }, explode(',', $_GET['columns']));
        
        return Excel::download(new VotersExport($voters, $request, $columns), 'Not Surveyed Voters.xlsx');


         

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error retrieving unsurveyed voters',
            'error' => $e->getMessage()
        ], 500);
    }
} 


public function SurveyedExport(Request $request)
{   
   
    try {
        $user = Auth::user();
        // Add validation for constituency_id
        if (empty($user->constituency_id)) {
            return response()->json([
                'success' => false,
                'message' => 'No constituency assigned to user',
                'data' => [],
                'meta' => [
                    'total' => 0,
                    'per_page' => 0,
                    'current_page' => 1,
                    'last_page' => 1, 
                ]
            ]);
        }
        $constituency_ids = explode(',', $user->constituency_id);
        
        // Log constituency IDs for debugging
        \Log::info('Constituency IDs:', $constituency_ids);
         
        $perPage = $request->input('per_page', 20);
        
        // Get search parameters for each column
        $surname = $request->input('surname');
        $const = $request->input('const');
        $firstName = $request->input('first_name');
        $secondName = $request->input('second_name');
        $address = $request->input('address');
        $voterId = $request->input('voter');
        $constituencyName = $request->input('constituency_name');

          
            $query = Voter::with('user')
            ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date','surveys.user_id')
            ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
            ->join('surveys', 'voters.id', '=', 'surveys.voter_id')
            ->whereExists(function ($query) {
                $query->select('id')
                    ->from('surveys')
                    ->whereColumn('surveys.voter_id', 'voters.id')
                    ->where('surveys.user_id', Auth::id());
            })
            
            ->orderBy('surveys.id', 'desc');

       
        // Apply individual column filters
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

        // Order by surveys.id desc
        $query->orderBy('surveys.id', 'desc');

        // Get paginated results
        $voters = $query->get();

        $columns = array_map(function($column) {
            return strtolower(urldecode(trim($column)));
        }, explode(',', $_GET['columns']));
        
        return Excel::download(new VotersExport($voters, $request, $columns), 'Surveyed Voters.xlsx');

    } catch (\Exception $e) {
        return response()->json([ 
            'success' => false,
            'message' => 'Error retrieving surveyed voters', 
            'error' => $e->getMessage()
        ], 500);
    }
}



} 