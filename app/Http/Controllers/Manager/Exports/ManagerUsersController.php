<?php

namespace App\Http\Controllers\Manager\Exports;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Constituency;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\UsersExport;
use App\Models\DailySurveyTrack;
use App\Exports\SurveyTargetExport;
use App\Exports\MangerFNMVotersExport;
use App\Exports\VotersDiffAddressExport;
use App\Models\Voter;
use Illuminate\Support\Facades\Auth;
use App\Models\Survey;
use Illuminate\Support\Facades\DB;
use App\Exports\Reports1Export;
use App\Exports\Reports2Export;
use App\Exports\Reports3Export;      
use App\Exports\Reports4Export;
use App\Exports\Reports5Export;     
class ManagerUsersController extends Controller
{
     
    
    public function export(Request $request)  
    {

     
        
        $query = User::where('manager_id', auth()->user()->id)->where('role_id', 2)
                    ->withCount('surveys'); // Add surveys count
 
                    if ($request->has('search')) {
                        $searchTerm = trim(strtolower($request->search));
                        $query->where(function($q) use ($searchTerm) {
                            $q->whereRaw('LOWER(name) LIKE ?', ['%' . $searchTerm . '%']);
                        });
                    }
            
                    if ($request->has('constituency_name') && !empty($request->constituency_name)) {
                        $searchTerm = strtolower($request->constituency_name);
                        $constituencies = Constituency::whereRaw('LOWER(name) LIKE ?', ['%' . $searchTerm . '%'])
                            ->pluck('id')
                            ->toArray();
            
                        if (!empty($constituencies)) {
                            $query->where(function($q) use ($constituencies) {
                                foreach($constituencies as $constituencyId) {
                                    $q->orWhereRaw("constituency_id ~ ?", ["(^|,)" . $constituencyId . "($|,)"]);
                                }
                            });
                        }
                    }
            
                    if ($request->has('address')) {
                        $address = strtolower($request->address);
                        $query->whereRaw('LOWER(address) LIKE ?', ['%' . $address . '%']); 
                    }
            
                    if ($request->has('email')) {
                        $email = strtolower($request->email);
                        $query->whereRaw('LOWER(email) LIKE ?', ['%' . $email . '%']);
                    } 
            
            
                    if ($request->has('status')) {
                        $status = strtolower($request->status);
                        if ($status === 'active') {
                            $query->where('is_active', 1);
                        } else if ($status === 'inactive') {
                            $query->where('is_active', 0);
                        }
                    } 
            

        $users = $query->orderBy('id', 'desc')->paginate(10);
        
        $users->getCollection()->transform(function($user) {
            $constituencyIds = $user->constituency_id ? explode(',', $user->constituency_id) : [];
            $constituencies = empty($constituencyIds) ? null : 
                Constituency::whereIn('id', $constituencyIds)
                    ->select('id', 'name', 'is_active')
                    ->get();
            
            $user->constituencies = $constituencies;

            // Get survey count for this user
            $surveyCount = \App\Models\Survey::where('user_id', $user->id)->count();
            $user->survey_count = $surveyCount;

            return $user;
        });

        $columns = array_map(function($column) {
            return strtolower(urldecode(trim($column)));
        }, explode(',', $_GET['columns']));
        
        $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
        return Excel::download(
            new UsersExport($users, $request, $columns),
            'Canvassers_' . $timestamp . '.xlsx'
        );   
    }


    public function getVotersDiffAddress(Request $request)
{
    try {
        // Get authenticated user's constituency_id and split into array
        $constituency_ids = explode(',', auth()->user()->constituency_id);

        if (empty($constituency_ids)) {
            return response()->json([
                'success' => false,
                'message' => 'User does not have an assigned constituency'
            ], 400);
        }

     
            $query = Voter::query()
            ->select(
                'voters.*',
                'constituencies.name as constituency_name',
                'new_constituencies.name as new_constituency_name' // Alias for the second join
            )
            ->join('constituencies', 'voters.const', '=', 'constituencies.id')
            ->leftJoin('constituencies as new_constituencies', 'voters.living_constituency', '=', 'new_constituencies.id') // Second join
            ->whereIn('voters.const', $constituency_ids)
            ->where('voters.diff_address', 'yes');
            
        
            $searchableFields = [
                'first_name' => 'First Name',
                'second_name' => 'Second Name',
                'surname' => 'Surname', 
                'address' => 'Address',
                'voter' => 'Voter ID',
                'const' => 'Constituency ID',
                'constituency_name' => 'Constituency Name',
                'polling' => 'Polling Station'
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
            $new_constituency = $request->input('new_constituency');
            $new_constituencyName = $request->input('new_constituencyName'); 
            $existsInDatabase = $request->input('exists_in_database'); 
        

            if ($existsInDatabase === 'true') {
                $query->where('voters.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $query->where('voters.exists_in_database', false);
            }

            // Apply filters
        
            if (!empty($polling) && is_numeric($polling)) {
                $query->where('voters.polling', $polling);
            }
        
            if ($underAge25 === 'yes') {
                $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
            }
            if (!empty($const)) {
                $query->where('voters.const', $const);
            }
            if (!empty($new_constituency)) {
             $query->where('voters.living_constituency', $new_constituency);
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
        
        
            if (!empty($voterId) && is_numeric($voterId)) {
                $query->where('voters.voter', $voterId);
            }
        
            if (!empty($constituencyName)) {
                $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
            }
        
            if (!empty($new_constituencyName)) {
             $query->whereRaw('LOWER(new_constituencies.name) LIKE ?', ['%' . strtolower($new_constituencyName) . '%']);
             }
        
            if (!empty($constituencyId)) {
                $query->where('voters.const', $constituencyId);
            }
        

        // Get paginated results
        $voters = $query->get();

        $columns = array_map(function($column) {
            return strtolower(urldecode(trim($column)));
        }, explode(',', $_GET['columns']));
        
        $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
        return Excel::download(
            new VotersDiffAddressExport($voters, $request, $columns),
            'Diff Address Voters_' . $timestamp . '.xlsx'
        );  

        

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error retrieving voters list',
            'error' => $e->getMessage()
        ], 500);
    }
}


    public function getUserSurveyCount(Request $request, $id){
        $query = DailySurveyTrack::with('user')->where('user_id', $id); 
        
        if ($request->filled('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('date', '<=', $request->end_date);
        }

        $dailySurveyCount = $query->orderBy('date', 'desc')->get();

        $columns = array_map(function($column) {
            return strtolower(urldecode(trim($column)));
        }, explode(',', $_GET['columns']));
        $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
        return \Maatwebsite\Excel\Facades\Excel::download(new SurveyTargetExport($dailySurveyCount, $request, $columns), 'Canvassers-Target-Surveys_' . $timestamp . '.xlsx');
    }


   
    public function statsList(Request $request)
    {  
        $surveyor = User::where('id', Auth::user()->id)->first();
        $type = $request->get('type', 'registered');
        
        
        $perPage = $request->get('per_page', 20); 
        $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
        
        if($type == 'fnm'){
            $results = $this->getFNMVoters($request,$surveyor, $perPage);
            $columns = array_map(function($column) {
                return strtolower(urldecode(trim($column)));
            }, explode(',', $_GET['columns']));
 
            return Excel::download(new MangerFNMVotersExport($results, $request, $columns), 'FNM voters_' . $timestamp . '.xlsx');   

        }

        if($type == 'total_unknown'){ 
            $results = $this->getTotalUnknown($request,$surveyor, $perPage);
            $columns = array_map(function($column) {
                return strtolower(urldecode(trim($column)));
            }, explode(',', $_GET['columns']));
           
            return Excel::download(new MangerFNMVotersExport($results, $request, $columns), 'Unknown voters_' . $timestamp . '.xlsx');   

        }
        
        if($type == 'total_naver_vote'){ 
            $results = $this->getTotalnaverVote($request,$surveyor, $perPage);
            $columns = array_map(function($column) {
                return strtolower(urldecode(trim($column)));
            }, explode(',', $_GET['columns']));
           
            return Excel::download(new MangerFNMVotersExport($results, $request, $columns), 'Unknown voters_' . $timestamp . '.xlsx');   

        }
        if($type == 'coi'){ 
            $results = $this->getCOIVoters($request,$surveyor, $perPage);
            $columns = array_map(function($column) {
                return strtolower(urldecode(trim($column)));
            }, explode(',', $_GET['columns']));
           
            return Excel::download(new MangerFNMVotersExport($results, $request, $columns), 'Unknown voters_' . $timestamp . '.xlsx');   

        }
        if($type == 'plp'){ 
            $results = $this->getPLPVoters($request,$surveyor, $perPage);
            $columns = array_map(function($column) {
                return strtolower(urldecode(trim($column)));
            }, explode(',', $_GET['columns']));
           
            return Excel::download(new MangerFNMVotersExport($results, $request, $columns), 'Unknown voters_' . $timestamp . '.xlsx');   

        }
        if($type == 'total_others'){ 
            $results = $this->getOters($request,$surveyor, $perPage);
            $columns = array_map(function($column) {
                return strtolower(urldecode(trim($column)));
            }, explode(',', $_GET['columns']));
           
            return Excel::download(new MangerFNMVotersExport($results, $request, $columns), 'Unknown voters_' . $timestamp . '.xlsx');   

        }
        if($type == 'total_other_parties'){ 
            $results = $this->getOtherPartyVoters($request,$surveyor, $perPage);
           
            $columns = array_map(function($column) {
                return strtolower(urldecode(trim($column)));
            }, explode(',', $_GET['columns']));
           
            return Excel::download(new MangerFNMVotersExport($results, $request, $columns), 'Unknown voters_' . $timestamp . '.xlsx');    

        }

    }



    private function getOters($request,$surveyor, $perPage) 
    {
          
       
        $surname = $request->input('surname');
        $firstName = $request->input('first_name');
        $secondName = $request->input('second_name'); 
        $last_name = $request->input('last_name'); 
        $address = $request->input('address');
        $voterId = $request->input('voter_id');
        $constituencyName = $request->input('constituency_name');
        $constituencyId = $request->input('constituency_id'); 
        $underAge25 = $request->input('under_age_25');
        $houseNumber = $request->input('house_number');
        $pobse = $request->input('pobse');
        $pobis = $request->input('pobis');
        $pobcn = $request->input('pobcn');
        $polling = $request->input('polling');
    
        $existsInDatabase = $request->input('exists_in_database');

        $query = Survey::with('user')->join('voters', 'surveys.voter_id', '=', 'voters.id')
        ->join(DB::raw("(
            SELECT DISTINCT ON (voter_id) id
            FROM surveys
            ORDER BY voter_id, created_at DESC
        ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
        ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date', 'surveys.user_id', 'surveys.voting_for')
        ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
        ->where('voting_for', 'Other')
        ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
        ->orderBy('surveys.id', 'desc');

     // Apply search filters

     $searchableFields = [
         'first_name' => 'First Name',
         'second_name' => 'Second Name',
         'surname' => 'Surname', 
         'address' => 'Address',
         'voter_id' => 'Voter ID',
         'polling' => 'Polling Station',
         'const' => 'Constituency ID',
         'constituency_name' => 'Constituency Name',
         'user_id' => 'User ID',
         'polling' => 'Polling Station',
         'voting_decision' => 'Voting Decision'
 
     ];  

        if ($existsInDatabase === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false); 
        }
       

        if (!empty($polling) && is_numeric($polling)) {
            $query->where('voters.polling', $polling);
        }
        if (!empty($voting_decision)) {
            $query->where('surveys.voting_decision', $voting_decision);
        }

        if ($underAge25 === 'yes') {
            $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
        }

        // Get search parameters
        if (isset($request->user_id) && !empty($request->user_id)) { 
            $query->where('surveys.user_id', $request->user_id);
        }
        if (isset($request->start_date) && !empty($request->start_date)) {
            $query->where('surveys.created_at', '>=', $request->start_date . ' 00:00:00');
        }

        if (isset($request->end_date) && !empty($request->end_date)) {
            $query->where('surveys.created_at', '<=', $request->end_date . ' 23:59:59');
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

        if (!empty($last_name)) {
            $query->whereRaw('LOWER(voters.surname) LIKE ?', ['%' . strtolower($last_name) . '%']);
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
    
        if (!empty($voterId) && is_numeric($voterId)) {
            $query->where('voters.voter', $voterId); // Changed from voters.voter to voters.id since voter ID should match the primary key
        }

        if (!empty($constituencyName)) {
            $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
        }

        if (!empty($constituencyId)) {
            $query->where('voters.const', $constituencyId);
        }

        // Get paginated results with all surveys
        $voters = $query->get();
      
        
        return $voters; 

    }
    private function getOtherPartyVoters($request,$surveyor, $perPage) 
    {
         
       
        $surname = $request->input('surname');
        $firstName = $request->input('first_name');
        $secondName = $request->input('second_name'); 
        $last_name = $request->input('last_name'); 
        $address = $request->input('address');
        $voterId = $request->input('voter_id');
        $constituencyName = $request->input('constituency_name');
        $constituencyId = $request->input('constituency_id'); 
        $underAge25 = $request->input('under_age_25');
        $houseNumber = $request->input('house_number');
        $pobse = $request->input('pobse');
        $pobis = $request->input('pobis');
        $pobcn = $request->input('pobcn');
        $polling = $request->input('polling');
    
        $existsInDatabase = $request->input('exists_in_database');

        $query = Survey::with('user')->join('voters', 'surveys.voter_id', '=', 'voters.id')
        ->join(DB::raw("(
            SELECT DISTINCT ON (voter_id) id
            FROM surveys
            ORDER BY voter_id, created_at DESC
        ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
        ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date', 'surveys.user_id', 'surveys.voting_for')
        ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
        ->where(function($query) {
            $query->whereNotIn('voting_for', ['FNM', 'Free National Movement', 'PLP', 'Progressive Liberal Party', 'COI', 'Coalition of Independents', 'Other'])
                  ->whereNotNull('voting_for');
            
        })
        ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
        ->orderBy('surveys.id', 'desc');


        
   
           

     // Apply search filters

     $searchableFields = [
         'first_name' => 'First Name',
         'second_name' => 'Second Name',
         'surname' => 'Surname', 
         'address' => 'Address',
         'voter_id' => 'Voter ID',
         'polling' => 'Polling Station',
         'const' => 'Constituency ID',
         'constituency_name' => 'Constituency Name',
         'user_id' => 'User ID',
         'polling' => 'Polling Station',
         'voting_decision' => 'Voting Decision'
 
     ];  

        if ($existsInDatabase === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false); 
        }
       

        if (!empty($polling) && is_numeric($polling)) {
            $query->where('voters.polling', $polling);
        }
        if (!empty($voting_decision)) {
            $query->where('surveys.voting_decision', $voting_decision);
        }

        if ($underAge25 === 'yes') {
            $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
        }

        // Get search parameters
        if (isset($request->user_id) && !empty($request->user_id)) { 
            $query->where('surveys.user_id', $request->user_id);
        }
        if (isset($request->start_date) && !empty($request->start_date)) {
            $query->where('surveys.created_at', '>=', $request->start_date . ' 00:00:00');
        }

        if (isset($request->end_date) && !empty($request->end_date)) {
            $query->where('surveys.created_at', '<=', $request->end_date . ' 23:59:59');
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

        if (!empty($last_name)) {
            $query->whereRaw('LOWER(voters.surname) LIKE ?', ['%' . strtolower($last_name) . '%']);
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
    
        if (!empty($voterId) && is_numeric($voterId)) {
            $query->where('voters.voter', $voterId); // Changed from voters.voter to voters.id since voter ID should match the primary key
        }

        if (!empty($constituencyName)) {
            $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
        }

        if (!empty($constituencyId)) {
            $query->where('voters.const', $constituencyId);
        }

        // Get paginated results with all surveys
        $voters = $query->get();
      
        
        return $voters; 

    }

    private function getRegisteredVoters($surveyor, $perPage)
    {
        return Voter::query()
            ->whereIn('const', explode(',', $surveyor->constituency_id))
            ->paginate($perPage);
    }

    private function getFNMVoters($request,$surveyor, $perPage) 
    {
          
       
        $surname = $request->input('surname');
        $firstName = $request->input('first_name');
        $secondName = $request->input('second_name'); 
        $last_name = $request->input('last_name'); 
        $address = $request->input('address');
        $voterId = $request->input('voter_id');
        $constituencyName = $request->input('constituency_name');
        $constituencyId = $request->input('constituency_id'); 
        $underAge25 = $request->input('under_age_25');
        $houseNumber = $request->input('house_number');
        $pobse = $request->input('pobse');
        $pobis = $request->input('pobis');
        $pobcn = $request->input('pobcn');
        $polling = $request->input('polling');
    
        $existsInDatabase = $request->input('exists_in_database');

        $query = Survey::with('user')->join('voters', 'surveys.voter_id', '=', 'voters.id')
        ->join(DB::raw("(
            SELECT DISTINCT ON (voter_id) id
            FROM surveys
            ORDER BY voter_id, created_at DESC
        ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
        ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date', 'surveys.user_id', 'surveys.voting_for')
        ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
        ->where(function($query) {
            $query->where('surveys.voting_for', 'FNM')
                  ->orWhere('surveys.voting_for', 'Free National Movement');
        })
        ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
        ->orderBy('surveys.id', 'desc');

     // Apply search filters

     $searchableFields = [
         'first_name' => 'First Name',
         'second_name' => 'Second Name',
         'surname' => 'Surname', 
         'address' => 'Address',
         'voter_id' => 'Voter ID',
         'polling' => 'Polling Station',
         'const' => 'Constituency ID',
         'constituency_name' => 'Constituency Name',
         'user_id' => 'User ID',
         'polling' => 'Polling Station',
         'voting_decision' => 'Voting Decision'
 
     ];  

        if ($existsInDatabase === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false); 
        }
       

        if (!empty($polling) && is_numeric($polling)) {
            $query->where('voters.polling', $polling);
        }
        if (!empty($voting_decision)) {
            $query->where('surveys.voting_decision', $voting_decision);
        }

        if ($underAge25 === 'yes') {
            $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
        }

        // Get search parameters
        if (isset($request->user_id) && !empty($request->user_id)) { 
            $query->where('surveys.user_id', $request->user_id);
        }
        if (isset($request->start_date) && !empty($request->start_date)) {
            $query->where('surveys.created_at', '>=', $request->start_date . ' 00:00:00');
        }

        if (isset($request->end_date) && !empty($request->end_date)) {
            $query->where('surveys.created_at', '<=', $request->end_date . ' 23:59:59');
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

        if (!empty($last_name)) {
            $query->whereRaw('LOWER(voters.surname) LIKE ?', ['%' . strtolower($last_name) . '%']);
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
    
        if (!empty($voterId) && is_numeric($voterId)) {
            $query->where('voters.voter', $voterId); // Changed from voters.voter to voters.id since voter ID should match the primary key
        }

        if (!empty($constituencyName)) {
            $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
        }

        if (!empty($constituencyId)) {
            $query->where('voters.const', $constituencyId);
        }

        // Get paginated results with all surveys
        $voters = $query->get();
      
        
        return $voters; 

    }
    private function getCOIVoters($request,$surveyor, $perPage) 
    {
          
       
        $surname = $request->input('surname');
        $firstName = $request->input('first_name');
        $secondName = $request->input('second_name'); 
        $last_name = $request->input('last_name'); 
        $address = $request->input('address');
        $voterId = $request->input('voter_id');
        $constituencyName = $request->input('constituency_name');
        $constituencyId = $request->input('constituency_id'); 
        $underAge25 = $request->input('under_age_25');
        $houseNumber = $request->input('house_number');
        $pobse = $request->input('pobse');
        $pobis = $request->input('pobis');
        $pobcn = $request->input('pobcn');
        $polling = $request->input('polling');
        $existsInDatabase = $request->input('exists_in_database');


    // $query = Voter::with('user')
    //     ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date','surveys.user_id','surveys.voting_for','surveys.voting_for')
    //     ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
    //     ->join('surveys', 'voters.id', '=', 'surveys.voter_id')
    //     ->whereExists(function ($query) {
    //         $query->select('id')
    //             ->from('surveys')
    //             ->whereColumn('surveys.voter_id', 'voters.id');
    //     })
    //     ->where(function($query) {
    //         $query->where('voting_for', 'FNM')
    //               ->orWhere('voting_for', 'Coalition of Independents');
    //     })
    //     ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
    //     ->orderBy('surveys.id', 'desc');
    $query = Survey::with('user')
    ->join('voters', 'surveys.voter_id', '=', 'voters.id')
    ->join(DB::raw("(
        SELECT DISTINCT ON (voter_id) id 
        FROM surveys
        ORDER BY voter_id, created_at DESC
    ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
    ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date', 'surveys.user_id', 'surveys.voting_for')
    ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
    ->where(function($query) {
        $query->where('surveys.voting_for', 'FNM')
              ->orWhere('surveys.voting_for', 'Coalition of Independents');
    })
    ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
    ->orderBy('surveys.id', 'desc');


     // Apply search filters

     $searchableFields = [
         'first_name' => 'First Name',
         'second_name' => 'Second Name',
         'surname' => 'Surname', 
         'address' => 'Address',
         'voter_id' => 'Voter ID',
         'polling' => 'Polling Station',
         'const' => 'Constituency ID',
         'constituency_name' => 'Constituency Name',
         'user_id' => 'User ID',
         'polling' => 'Polling Station',
         'voting_decision' => 'Voting Decision'
 
     ];  

        if ($existsInDatabase === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false);
        }

        if (!empty($polling) && is_numeric($polling)) {
            $query->where('voters.polling', $polling);
        }
        if (!empty($voting_decision)) {
            $query->where('surveys.voting_decision', $voting_decision);
        }

        if ($underAge25 === 'yes') {
            $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
        }

        // Get search parameters
        if (isset($request->user_id) && !empty($request->user_id)) { 
            $query->where('surveys.user_id', $request->user_id);
        }
        if (isset($request->start_date) && !empty($request->start_date)) {
            $query->where('surveys.created_at', '>=', $request->start_date . ' 00:00:00');
        }

        if (isset($request->end_date) && !empty($request->end_date)) {
            $query->where('surveys.created_at', '<=', $request->end_date . ' 23:59:59');
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

        if (!empty($last_name)) {
            $query->whereRaw('LOWER(voters.surname) LIKE ?', ['%' . strtolower($last_name) . '%']);
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
    
        if (!empty($voterId) && is_numeric($voterId)) {
            $query->where('voters.voter', $voterId); // Changed from voters.voter to voters.id since voter ID should match the primary key
        }

        if (!empty($constituencyName)) {
            $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
        }

        if (!empty($constituencyId)) {
            $query->where('voters.const', $constituencyId);
        }

        // Get paginated results with all surveys
        $voters = $query->get();
      
        
        return $voters; 

    }

    private function getPLPVoters($request,$surveyor, $perPage)
    {
        
        $surname = $request->input('surname');
        $firstName = $request->input('first_name');
        $secondName = $request->input('second_name'); 
        $last_name = $request->input('last_name'); 
        $address = $request->input('address');
        $voterId = $request->input('voter_id');
        $constituencyName = $request->input('constituency_name');
        $constituencyId = $request->input('constituency_id'); 
        $underAge25 = $request->input('under_age_25');
        $houseNumber = $request->input('house_number');
        $pobse = $request->input('pobse');
        $pobis = $request->input('pobis');
        $pobcn = $request->input('pobcn');
        $polling = $request->input('polling');
        $existsInDatabase = $request->input('exists_in_database');

    $query =   $query = Survey::with('user')->join('voters', 'surveys.voter_id', '=', 'voters.id')
            ->join(DB::raw("(
                SELECT DISTINCT ON (voter_id) id 
                FROM surveys
                ORDER BY voter_id, created_at DESC
            ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
            ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date', 'surveys.user_id', 'surveys.voting_for')
            ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
            ->where(function($query) {
                $query->where('surveys.voting_for', 'PLP')
                      ->orWhere('surveys.voting_for', 'Progressive Liberal Party');
            })
            ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
            ->orderBy('surveys.id', 'desc');

     // Apply search filters

     $searchableFields = [
         'first_name' => 'First Name',
         'second_name' => 'Second Name',
         'surname' => 'Surname', 
         'address' => 'Address',
         'voter_id' => 'Voter ID',
         'polling' => 'Polling Station',
         'const' => 'Constituency ID',
         'constituency_name' => 'Constituency Name',
         'user_id' => 'User ID',
         'polling' => 'Polling Station',
         'voting_decision' => 'Voting Decision'
 
     ];  

        if ($existsInDatabase === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false);
        }

        if (!empty($polling) && is_numeric($polling)) {
            $query->where('voters.polling', $polling);
        }
        if (!empty($voting_decision)) {
            $query->where('surveys.voting_decision', $voting_decision);
        }

        if ($underAge25 === 'yes') {
            $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
        }

        // Get search parameters
        if (isset($request->user_id) && !empty($request->user_id)) { 
            $query->where('surveys.user_id', $request->user_id);
        }
        if (isset($request->start_date) && !empty($request->start_date)) {
            $query->where('surveys.created_at', '>=', $request->start_date . ' 00:00:00');
        }

        if (isset($request->end_date) && !empty($request->end_date)) {
            $query->where('surveys.created_at', '<=', $request->end_date . ' 23:59:59');
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

        if (!empty($last_name)) {
            $query->whereRaw('LOWER(voters.surname) LIKE ?', ['%' . strtolower($last_name) . '%']);
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
    
        if (!empty($voterId) && is_numeric($voterId)) {
            $query->where('voters.voter', $voterId); // Changed from voters.voter to voters.id since voter ID should match the primary key
        }

        if (!empty($constituencyName)) {
            $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
        }

        if (!empty($constituencyId)) {
            $query->where('voters.const', $constituencyId);
        }

        // Get paginated results with all surveys
        $voters = $query->get();
      
        
        return $voters; 
    }

    

    private function getTotalSurveys($request,$surveyor, $perPage)
    {
        $query = Survey::with('voter');
        

        // Search fields based on Survey model's fillable columns
        // Apply all search filters directly from URL parameters with case-insensitive search
        if ($request->has('sex')) {
            $query->whereRaw('LOWER(sex) = ?', [strtolower($request->sex)]);
        }
        if ($request->has('voting_decision')) {
            $query->where('voting_decision',$request->voting_decision);
        }

        if ($request->has('marital_status')) {
            $query->whereRaw('LOWER(marital_status) = ?', [strtolower($request->marital_status)]);
        }

        if ($request->has('employed')) {
            $query->where('employed', filter_var($request->employed, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('children')) {
            $query->where('children', filter_var($request->children, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('employment_type')) {
            $query->whereRaw('LOWER(employment_type) = ?', [strtolower($request->employment_type)]);
        }

        if ($request->has('religion')) {
            $query->whereRaw('LOWER(religion) = ?', [strtolower($request->religion)]);
        }

        if ($request->has('located')) {
            $query->whereRaw('LOWER(located) = ?', [strtolower($request->located)]);
        }

        if ($request->has('home_phone')) {
            $query->whereRaw('LOWER(home_phone) LIKE ?', ['%' . strtolower($request->home_phone) . '%']);
        }

        if ($request->has('work_phone')) {
            $query->whereRaw('LOWER(work_phone) LIKE ?', ['%' . strtolower($request->work_phone) . '%']);
        }

        if ($request->has('cell_phone')) {
            $query->whereRaw('LOWER(cell_phone) LIKE ?', ['%' . strtolower($request->cell_phone) . '%']);
        }

        if ($request->has('email')) {
            $query->whereRaw('LOWER(email) LIKE ?', ['%' . strtolower($request->email) . '%']);
        }

        if ($request->has('special_comments')) {
            $query->whereRaw('LOWER(special_comments) LIKE ?', ['%' . strtolower($request->special_comments) . '%']);
        }

        if ($request->has('other_comments')) {
            $query->whereRaw('LOWER(other_comments) LIKE ?', ['%' . strtolower($request->other_comments) . '%']);
        }

        if ($request->has('voting_for')) {
            $query->whereRaw('LOWER(voting_for) = ?', [strtolower($request->voting_for)]);
        }

        if ($request->has('voted_in_2017')) {
            $query->where('voted_in_2017', filter_var($request->voted_in_2017, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('where_voted_in_2017')) {
            $query->whereRaw('LOWER(where_voted_in_2017) LIKE ?', ['%' . strtolower($request->where_voted_in_2017) . '%']);
        }

        if ($request->has('voted_in_house')) {
            $query->whereRaw('LOWER(voted_in_house) = ?', [strtolower($request->voted_in_house)]);
        }

        if ($request->has('voter_id')) {
            $query->whereHas('voter', function($q) use ($request) {
                $q->where('id', $request->voter_id);
            });
        }

        if ($request->has('constituency_id')) {
            $query->whereHas('voter', function($q) use ($request) {
                $q->where('const', $request->constituency_id);
            });
        }

        if ($request->has('first_name')) {
            $query->whereHas('voter', function($q) use ($request) {
                $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->first_name) . '%']);
            });
        }
            

        if ($request->has('last_name')) {
            $query->whereHas('voter', function($q) use ($request) {
                $q->whereRaw('LOWER(surname) LIKE ?', ['%' . strtolower($request->last_name) . '%']);
            });
        } 

        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->start_date . ' 00:00:00');
        }

        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->end_date . ' 23:59:59');
        }

        $query->whereHas('voter', function($q) use ($surveyor) {
            $q->whereIn('voters.const', explode(',', $surveyor->constituency_id));
        });
 

        // Get paginated results
        $surveys = $query->orderBy('id', 'desc')->paginate($perPage);

        return $surveys;
    }

    private function getTotalUnknown($request,$surveyor, $perPage)
    {
         

            $surname = $request->input('surname');
            $firstName = $request->input('first_name');
            $secondName = $request->input('second_name');
            $last_name = $request->input('last_name');
            $address = $request->input('address');
            $voterId = $request->input('voter_id');
            $constituencyName = $request->input('constituency_name');
            $constituencyId = $request->input('constituency_id');  
            $underAge25 = $request->input('under_age_25');
            $houseNumber = $request->input('house_number');
            $pobse = $request->input('pobse');
            $pobis = $request->input('pobis');
            $pobcn = $request->input('pobcn');
            $polling = $request->input('polling'); 
            $existsInDatabase = $request->input('exists_in_database');
    
    
        $query = Survey::with('user')->join('voters', 'surveys.voter_id', '=', 'voters.id')
        ->join(DB::raw("(
            SELECT DISTINCT ON (voter_id) id 
            FROM surveys
            ORDER BY voter_id, created_at DESC
        ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
        ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date', 'surveys.user_id', 'surveys.voting_for')
        ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
        ->where(function($query) {
            $query->whereNull('surveys.voting_for')->where('surveys.voting_decision','undecided');
        })
        ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
        ->orderBy('surveys.id', 'desc');
            
            
            if ($existsInDatabase === 'true') {
                $query->where('voters.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $query->where('voters.exists_in_database', false);
            }
            if (!empty($polling) && is_numeric($polling)) {
                $query->where('voters.polling', $polling);
            }
            if (!empty($voting_decision)) {
                $query->where('surveys.voting_decision', $voting_decision);
            }
    
            if ($underAge25 === 'yes') {
                $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
            }
    
            // Get search parameters
            if (isset($request->user_id) && !empty($request->user_id)) { 
                $query->where('surveys.user_id', $request->user_id);
            }
            if (isset($request->start_date) && !empty($request->start_date)) {
                $query->where('surveys.created_at', '>=', $request->start_date . ' 00:00:00');
            }
    
            if (isset($request->end_date) && !empty($request->end_date)) {
                $query->where('surveys.created_at', '<=', $request->end_date . ' 23:59:59');
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
            
            if (!empty($last_name)) {
                $query->whereRaw('LOWER(voters.surname) LIKE ?', ['%' . strtolower($last_name) . '%']);
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
        
            if (!empty($voterId) && is_numeric($voterId)) {
                $query->where('voters.voter', $voterId); // Changed from voters.voter to voters.id since voter ID should match the primary key
            }
    
            if (!empty($constituencyName)) {
                $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
            }
    
            if (!empty($constituencyId)) {
              
                $query->where('voters.const', $constituencyId);
            } 
    
            // Get paginated results with all surveys
            $voters = $query->get();
    
            
            return $voters;
    }
    private function getTotalnaverVote($request,$surveyor, $perPage)
    {
       

            $surname = $request->input('surname');
            $firstName = $request->input('first_name');
            $secondName = $request->input('second_name');
            $last_name = $request->input('last_name');
            $address = $request->input('address');
            $voterId = $request->input('voter_id');
            $constituencyName = $request->input('constituency_name');
            $constituencyId = $request->input('constituency_id');  
            $underAge25 = $request->input('under_age_25');
            $houseNumber = $request->input('house_number');
            $pobse = $request->input('pobse');
            $pobis = $request->input('pobis');
            $pobcn = $request->input('pobcn');
            $polling = $request->input('polling'); 
            $existsInDatabase = $request->input('exists_in_database');
    
    
        $query = Voter::with('user')
            ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date','surveys.user_id','surveys.voting_for')
            ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
            ->join('surveys', 'voters.id', '=', 'surveys.voter_id')
            ->whereExists(function ($query) {
                $query->select('id')
                    ->from('surveys')
                    ->whereColumn('surveys.voter_id', 'voters.id');
            })
            ->whereNull('surveys.voting_for')
            ->where('surveys.voting_decision','no')
            ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
            // ->where('surveys.user_id', Auth::id())
            ->orderBy('surveys.id', 'desc'); 
            
            if ($existsInDatabase === 'true') {
                $query->where('voters.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $query->where('voters.exists_in_database', false);
            }
            if (!empty($polling) && is_numeric($polling)) {
                $query->where('voters.polling', $polling);
            }
            if (!empty($voting_decision)) {
                $query->where('surveys.voting_decision', $voting_decision);
            }
    
            if ($underAge25 === 'yes') {
                $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
            }
    
            // Get search parameters
            if (isset($request->user_id) && !empty($request->user_id)) { 
                $query->where('surveys.user_id', $request->user_id);
            }
            if (isset($request->start_date) && !empty($request->start_date)) {
                $query->where('surveys.created_at', '>=', $request->start_date . ' 00:00:00');
            }
    
            if (isset($request->end_date) && !empty($request->end_date)) {
                $query->where('surveys.created_at', '<=', $request->end_date . ' 23:59:59');
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
            
            if (!empty($last_name)) {
                $query->whereRaw('LOWER(voters.surname) LIKE ?', ['%' . strtolower($last_name) . '%']);
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
        
            if (!empty($voterId) && is_numeric($voterId)) {
                $query->where('voters.voter', $voterId); // Changed from voters.voter to voters.id since voter ID should match the primary key
            }
    
            if (!empty($constituencyName)) {
                $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
            }
    
            if (!empty($constituencyId)) {
                $query->where('voters.const', $constituencyId);
            } 
    
            // Get paginated results with all surveys
            $voters = $query->get();
    
            
            return $voters;
    }

    private function getFirstTimeVoters($surveyor, $perPage)
    {
        return Voter::query()
            ->whereRaw("EXTRACT(YEAR FROM AGE(CURRENT_DATE, dob::date)) <= 25")
            ->whereIn('const', explode(',', $surveyor->constituency_id))
            ->paginate($perPage);
    }
    // new functiona //


    public function getConstituencyReports(Request $request)  
    { 
        // Get constituency IDs for logged in manager
        $constituencyIds = explode(',', auth()->user()->constituency_id);
        $existsInDatabase = $request->input('exists_in_database');
        // First get all active parties
        $parties = DB::table('parties')
            ->where('status', 'active')
            ->orderBy('position')
            ->get();

        // Build the select statement dynamically
        $selects = [
            'c.id as constituency_id',
            'c.name as constituency_name',
            DB::raw('COUNT(DISTINCT v.id) as total_voters'),
            DB::raw('COUNT(DISTINCT s.id) as surveyed_voters'),
            DB::raw('COUNT(DISTINCT v.id) - COUNT(DISTINCT s.id) as not_surveyed_voters'),
            DB::raw('ROUND((COUNT(DISTINCT s.id) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as surveyed_percentage'),
        ];

        // Add party-specific counts and percentages
        foreach ($parties as $party) {
            $partyName = $party->name;
            // Replace hyphens with underscores and make lowercase for column names
            $shortName = str_replace('-', '_', strtolower($party->short_name));
            
            // Add count for this party
            $selects[] = DB::raw("COUNT(DISTINCT CASE WHEN s.voting_for = '$partyName' THEN s.id END) as {$shortName}_count");
            
            // Add percentage for this party
            $selects[] = DB::raw("ROUND((COUNT(DISTINCT CASE WHEN s.voting_for = '$partyName' THEN s.id END) * 100.0) / NULLIF(COUNT(DISTINCT s.id), 0), 2) as {$shortName}_percentage");
        }

        // Add gender statistics
        $selects = array_merge($selects, [
            DB::raw("COUNT(DISTINCT CASE WHEN s.sex = 'Male' THEN s.id END) as total_male_surveyed"),
            DB::raw("COUNT(DISTINCT CASE WHEN s.sex = 'Female' THEN s.id END) as total_female_surveyed"), 
            DB::raw("COUNT(DISTINCT CASE WHEN s.sex IS NULL OR s.sex = '' THEN s.id END) as total_no_gender_surveyed"),
            DB::raw("ROUND((COUNT(DISTINCT CASE WHEN s.sex = 'Male' THEN s.id END) * 100.0) / NULLIF(COUNT(DISTINCT s.id), 0), 2) as male_percentage"),
            DB::raw("ROUND((COUNT(DISTINCT CASE WHEN s.sex = 'Female' THEN s.id END) * 100.0) / NULLIF(COUNT(DISTINCT s.id), 0), 2) as female_percentage")
        ]);

        $rawResults = DB::table('constituencies as c')
            ->leftJoin('voters as v', 'v.const', '=', 'c.id')
            ->leftJoin(DB::raw("(
                SELECT DISTINCT ON (voter_id) * 
                FROM surveys 
                ORDER BY voter_id, created_at DESC
            ) as s"), 'v.id', '=', 's.voter_id')
            ->select($selects)
            ->whereIn('c.id', $constituencyIds); // Add whereIn for manager's constituencies


            if ($existsInDatabase === 'true') {
         
                $rawResults->where('v.exists_in_database', true);
        
        } elseif ($existsInDatabase === 'false') {
            $rawResults->where('v.exists_in_database', false);
        }

        if ($request->has('constituency_id')) {
            $rawResults->where('c.id', $request->constituency_id);
        }

        if ($request->has('constituency_name')) {
            $rawResults->whereRaw('LOWER(c.name) LIKE ?', ['%' . strtolower($request->constituency_name) . '%']);
        }

        $rawResults = $rawResults->groupBy('c.id', 'c.name')
            ->orderBy('c.id', 'asc')
            ->get();

        // Transform the results to include party data as a map
        $results = $rawResults->map(function($row) use ($parties) {
            $transformedRow = [
                'constituency_id' => $row->constituency_id,
                'constituency_name' => $row->constituency_name,
                'total_voters' => $row->total_voters,
                'surveyed_voters' => $row->surveyed_voters,
                'not_surveyed_voters' => $row->not_surveyed_voters,
                'surveyed_percentage' => $row->surveyed_percentage,
                'parties' => [],
                'gender' => [
                    'male' => [
                        'count' => $row->total_male_surveyed,
                        'percentage' => $row->male_percentage
                    ],
                    'female' => [
                        'count' => $row->total_female_surveyed,
                        'percentage' => $row->female_percentage
                    ],
                    'unspecified' => [
                        'count' => $row->total_no_gender_surveyed,
                        'percentage' => 100 - ($row->male_percentage + $row->female_percentage)
                    ]
                ]
            ];

            // Add party data as a map
            foreach ($parties as $party) {
                $shortName = str_replace('-', '_', strtolower($party->short_name));
                $countKey = "{$shortName}_count";
                $percentageKey = "{$shortName}_percentage";
                
                $transformedRow['parties'][$party->short_name] = [
                    'count' => $row->$countKey,
                    'percentage' => $row->$percentageKey
                ];
            }

            return $transformedRow;
        });

        $columns = array_map(function($column) {
            return strtolower(urldecode(trim($column)));
        }, explode(',', $_GET['columns']));

        $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
        return Excel::download(new Reports1Export($results, $columns, $request), 'Constituency Reports_' . $timestamp . '.xlsx'); 

        // dd( $results,$request->columns);  
    }



    public function getConstituencyReport1(Request $request)
    {
      

            $constituencyIds = explode(',', auth()->user()->constituency_id);

            $existsInDatabase = $request->input('exists_in_database');
            $query = DB::table('constituencies as c')
            ->leftJoin('voters as v', 'v.const', '=', 'c.id')
            ->leftJoin(DB::raw("(
                SELECT DISTINCT ON (voter_id) * 
                FROM surveys 
                ORDER BY voter_id, created_at DESC
            ) as s"), 'v.id', '=', 's.voter_id')
            ->whereIn('c.id', $constituencyIds);

            if ($existsInDatabase === 'true') {
         
                $query->where('v.exists_in_database', true);
        
        } elseif ($existsInDatabase === 'false') {
            $query->where('v.exists_in_database', false);
        }

        if ($request->has('constituency_id')) {
            $query->where('c.id', $request->constituency_id);
        }

        if ($request->has('constituency_name')) {
            $query->whereRaw('LOWER(c.name) LIKE ?', ['%' . strtolower($request->constituency_name) . '%']);
        }

        $results = $query->select(
                'c.id as constituency_id',
                'c.name as constituency_name', 
                DB::raw('COUNT(DISTINCT s.id) as total_surveyed'),
                DB::raw('COUNT(DISTINCT v.id) as total_voters'),
                DB::raw('ROUND((COUNT(DISTINCT s.id) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as percentage')
            )
            ->groupBy('c.id', 'c.name')
            ->orderBy('c.id', 'asc');
             


            $results = $results->get();

            $columns = array_map(function($column) {
                return strtolower(urldecode(trim($column)));
            }, explode(',', $_GET['columns']));
       
            $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
            return Excel::download(new Reports2Export($results, $request, $columns), 'Constituency Reports_' . $timestamp . '.xlsx'); 
            
        }


        public function getConstituencyReport2(Request $request) 
        {
            $constituencyIds = explode(',', auth()->user()->constituency_id);
            $existsInDatabase = $request->input('exists_in_database');

            $parties = DB::table('parties')
                ->where('status', 'active')
                ->orderBy('position')
                ->get();
        
            // Build the select statement dynamically
            $selects = [
                'c.id as constituency_id',
                'c.name as constituency_name',
                DB::raw('COUNT(DISTINCT v.id) as total_voters'),
                DB::raw('COUNT(DISTINCT s.id) as surveyed_voters'),
                DB::raw('COUNT(DISTINCT v.id) - COUNT(DISTINCT s.id) as not_surveyed_voters'),
                DB::raw('ROUND((COUNT(DISTINCT s.id) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as surveyed_percentage'),
            ];
        
            // Add party-specific counts and percentages
            foreach ($parties as $party) {
                $partyName = $party->name;
                $shortName = str_replace('-', '_', strtolower($party->short_name));
                $selects[] = DB::raw("COUNT(DISTINCT CASE WHEN s.voting_for = '$partyName' THEN s.id END) as {$shortName}_count");
                $selects[] = DB::raw("ROUND((COUNT(DISTINCT CASE WHEN s.voting_for = '$partyName' THEN s.id END) * 100.0) / NULLIF(COUNT(DISTINCT s.id), 0), 2) as {$shortName}_percentage");
            }
        
            // Add gender statistics
            $selects = array_merge($selects, [
                DB::raw("COUNT(DISTINCT CASE WHEN s.sex = 'Male' THEN s.id END) as total_male_surveyed"),
                DB::raw("COUNT(DISTINCT CASE WHEN s.sex = 'Female' THEN s.id END) as total_female_surveyed"),
                DB::raw("COUNT(DISTINCT CASE WHEN s.sex IS NULL OR s.sex = '' THEN s.id END) as total_no_gender_surveyed"),
                DB::raw("ROUND((COUNT(DISTINCT CASE WHEN s.sex = 'Male' THEN s.id END) * 100.0) / NULLIF(COUNT(DISTINCT s.id), 0), 2) as male_percentage"),
                DB::raw("ROUND((COUNT(DISTINCT CASE WHEN s.sex = 'Female' THEN s.id END) * 100.0) / NULLIF(COUNT(DISTINCT s.id), 0), 2) as female_percentage"),
            ]);
        
            // Start building the query
            $query = DB::table('constituencies as c')
                ->leftJoin('voters as v', 'v.const', '=', 'c.id')
                // ->leftJoin('surveys as s', 's.voter_id', '=', 'v.id')
                ->leftJoin(DB::raw("(
                    SELECT DISTINCT ON (voter_id) * 
                    FROM surveys 
                    ORDER BY voter_id, created_at DESC
                ) as s"), 'v.id', '=', 's.voter_id')
                ->select($selects)
                ->whereIn('c.id', $constituencyIds)
                ->groupBy('c.id', 'c.name')
                ->orderBy('c.id', 'asc');
                if ($existsInDatabase === 'true') {
         
                    $query->where('v.exists_in_database', true);
            
            } elseif ($existsInDatabase === 'false') {
                $query->where('v.exists_in_database', false);
            }
            // Apply search filters
            if ($request->has('constituency_id')) {
                $query->where('c.id', $request->constituency_id);
            }
        
            if ($request->has('constituency_name')) {
                $query->whereRaw('LOWER(c.name) LIKE ?', ['%' . strtolower($request->constituency_name) . '%']);
            }
        
            // Execute the query
            $rawResults = $query->get();
        
            // Transform the results to include party and gender data
            $results = $rawResults->map(function ($row) use ($parties) {
                $transformedRow = [
                    'constituency_id' => $row->constituency_id,
                    'constituency_name' => $row->constituency_name,
                    'total_voters' => $row->total_voters,
                    'surveyed_voters' => $row->surveyed_voters,
                    'not_surveyed_voters' => $row->not_surveyed_voters,
                    'surveyed_percentage' => $row->surveyed_percentage,
                    'parties' => [],
                    'gender' => [
                        'male' => [
                            'count' => $row->total_male_surveyed,
                            'percentage' => $row->male_percentage
                        ],
                        'female' => [
                            'count' => $row->total_female_surveyed,
                            'percentage' => $row->female_percentage
                        ],
                        'unspecified' => [
                            'count' => $row->total_no_gender_surveyed,
                            'percentage' => 100 - ($row->male_percentage + $row->female_percentage)
                        ]
                    ]
                ];
        
                foreach ($parties as $party) {
                    $shortName = str_replace('-', '_', strtolower($party->short_name));
                    $transformedRow['parties'][$party->short_name] = [
                        'count' => $row->{$shortName . '_count'},
                        'percentage' => $row->{$shortName . '_percentage'}
                    ];
                }
        
                return $transformedRow;
            });
        
            // Get column names from query string
            $columns = array_map(function ($column) {
                return strtolower(urldecode(trim($column)));
            }, explode(',', $_GET['columns']));
        
            // Export to Excel
            $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
            return Excel::download(new Reports3Export($results, $request, $columns), 'Constituency Reports_' . $timestamp . '.xlsx');
        }

        /**
         * Report 5: Same columns as Report 2 but grouped by polling division (polling-based). Manager export.
         */
        public function getConstituencyReport5(Request $request)
        {
            $constituencyIds = explode(',', auth()->user()->constituency_id);
            $existsInDatabase = $request->input('exists_in_database');
            $parties = DB::table('parties')
                ->where('status', 'active')
                ->orderBy('position')
                ->get();

            $query = DB::table('voters as v')
                ->leftJoin('constituencies as c', 'v.const', '=', 'c.id')
                ->leftJoin(DB::raw("(
                    SELECT DISTINCT ON (voter_id) *
                    FROM surveys
                    ORDER BY voter_id, created_at DESC
                ) as s"), 'v.id', '=', 's.voter_id')
                ->whereIn('v.const', $constituencyIds);

            if ($existsInDatabase === 'true') {
                $query->where('v.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $query->where('v.exists_in_database', false);
            }
            if ($request->has('constituency_id')) {
                $query->where('v.const', $request->constituency_id);
            }
            if ($request->has('constituency_name')) {
                $query->whereRaw('LOWER(c.name) LIKE ?', ['%' . strtolower($request->constituency_name) . '%']);
            }

            $selects = [
                'v.polling as polling_division',
                'c.id as constituency_id',
                'c.name as constituency_name',
                DB::raw('COUNT(DISTINCT v.id) as total_voters'),
                DB::raw('COUNT(DISTINCT s.id) as surveyed_voters'),
                DB::raw('COUNT(DISTINCT v.id) - COUNT(DISTINCT s.id) as not_surveyed_voters'),
                DB::raw('ROUND((COUNT(DISTINCT s.id) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as surveyed_percentage'),
            ];
            foreach ($parties as $party) {
                $partyName = $party->name;
                $shortName = str_replace('-', '_', strtolower($party->short_name));
                $selects[] = DB::raw("COUNT(DISTINCT CASE WHEN s.voting_for = '$partyName' THEN s.id END) as {$shortName}_count");
                $selects[] = DB::raw("ROUND((COUNT(DISTINCT CASE WHEN s.voting_for = '$partyName' THEN s.id END) * 100.0) / NULLIF(COUNT(DISTINCT s.id), 0), 2) as {$shortName}_percentage");
            }
            $selects = array_merge($selects, [
                DB::raw("COUNT(DISTINCT CASE WHEN s.sex = 'Male' THEN s.id END) as total_male_surveyed"),
                DB::raw("COUNT(DISTINCT CASE WHEN s.sex = 'Female' THEN s.id END) as total_female_surveyed"),
                DB::raw("COUNT(DISTINCT CASE WHEN s.sex IS NULL OR s.sex = '' THEN s.id END) as total_no_gender_surveyed"),
                DB::raw("ROUND((COUNT(DISTINCT CASE WHEN s.sex = 'Male' THEN s.id END) * 100.0) / NULLIF(COUNT(DISTINCT s.id), 0), 2) as male_percentage"),
                DB::raw("ROUND((COUNT(DISTINCT CASE WHEN s.sex = 'Female' THEN s.id END) * 100.0) / NULLIF(COUNT(DISTINCT s.id), 0), 2) as female_percentage"),
            ]);

            $rawResults = $query->select($selects)
                ->groupBy('v.polling', 'c.id', 'c.name')
                ->orderBy('c.id', 'asc')
                ->orderBy('v.polling', 'asc')
                ->get();

            $results = $rawResults->map(function ($row) use ($parties) {
                $transformedRow = [
                    'polling_division' => $row->polling_division,
                    'constituency_id' => $row->constituency_id,
                    'constituency_name' => $row->constituency_name,
                    'total_voters' => $row->total_voters,
                    'surveyed_voters' => $row->surveyed_voters,
                    'not_surveyed_voters' => $row->not_surveyed_voters,
                    'surveyed_percentage' => $row->surveyed_percentage,
                    'parties' => [],
                    'gender' => [
                        'male' => ['count' => $row->total_male_surveyed, 'percentage' => $row->male_percentage],
                        'female' => ['count' => $row->total_female_surveyed, 'percentage' => $row->female_percentage],
                        'unspecified' => [
                            'count' => $row->total_no_gender_surveyed,
                            'percentage' => 100 - ($row->male_percentage + $row->female_percentage)
                        ]
                    ]
                ];
                foreach ($parties as $party) {
                    $shortName = str_replace('-', '_', strtolower($party->short_name));
                    $countKey = "{$shortName}_count";
                    $percentageKey = "{$shortName}_percentage";
                    $transformedRow['parties'][$party->short_name] = [
                        'count' => $row->$countKey,
                        'percentage' => $row->$percentageKey
                    ];
                }
                return $transformedRow;
            });

            $columns = array_map(function ($column) {
                return strtolower(urldecode(trim($column)));
            }, explode(',', $_GET['columns'] ?? ''));

            $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
            return Excel::download(new Reports5Export($results, $request, $columns, $parties), 'Polling Reports_' . $timestamp . '.xlsx');
        }

        public function getConstituencyReport4(Request $request)
        {   

            // Get party names using EXACT same method as getVotersInSurvey
            $fnmParty = DB::table('parties')->whereRaw('LOWER(name) = ?', ['free national movement'])->first();
            $plpParty = DB::table('parties')->whereRaw('LOWER(name) = ?', ['progressive liberal party'])->first();
            $coiParty = DB::table('parties')->whereRaw('LOWER(name) = ?', ['coalition of independents'])->first();
            
            $fnmName = $fnmParty ? $fnmParty->name : 'Free National Movement';
            $plpName = $plpParty ? $plpParty->name : 'Progressive Liberal Party';
            $coiName = $coiParty ? $coiParty->name : 'Coalition of Independents';
    
            // Build query EXACTLY like getVotersInSurvey - using INNER JOIN with raw subquery
            $query = DB::table('voters as v')
                ->leftJoin('constituencies as c', 'v.const', '=', 'c.id')
                ->join(DB::raw("(
                    SELECT DISTINCT ON (voter_id) 
                        voter_id,
                        id,
                        created_at,
                        user_id,
                        located,
                        voting_decision,
                        voting_for,
                        is_died,
                        died_date,
                        challenge
                    FROM surveys 
                    ORDER BY voter_id, id DESC
                ) as ls"), 'ls.voter_id', '=', 'v.id')
                 ->whereIn('v.const', explode(',', auth()->user()->constituency_id));
    
            // Get ALL filter parameters - SAME as getVotersInSurvey
            $existsInDatabase = $request->input('exists_in_database');
            $underAge25 = $request->input('under_age_25');
            $surname = $request->input('surname');
            $firstName = $request->input('first_name');
            $secondName = $request->input('second_name');
            $voterId = $request->input('voter');
            $houseNumber = $request->input('house_number'); 
            $address = $request->input('address');
            $pobse = $request->input('pobse');
            $pobis = $request->input('pobis');
            $pobcn = $request->input('pobcn');
            $constituencyId = $request->input('const') ?? $request->input('constituency_id');
            $constituencyName = $request->input('constituency_name');
            $polling = $request->input('polling');
            $located = $request->input('located');
            $voting_decision = $request->input('voting_decision');
            $voting_for = $request->input('voting_for');
            $is_died = $request->input('is_died');
            $died_date = $request->input('died_date');
            $challenge = $request->input('challenge');
            $user_id = $request->input('user_id');
            $start_date = $request->input('start_date');
            $end_date = $request->input('end_date');
    
            // Apply challenge filter
            if ($challenge === 'true') {
                $query->whereRaw('ls.challenge IS TRUE');
            } elseif ($challenge === 'false') {
                $query->whereRaw('ls.challenge IS FALSE');
            }
    
            // Apply exists_in_database filter
            if ($existsInDatabase === 'true') {
                $query->where('v.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $query->where('v.exists_in_database', false);
            }
    
            // Apply voting_for filter - SAME logic as getVotersInSurvey
            if ($voting_for !== null && $voting_for !== '') {
                if (is_numeric($voting_for)) {
                    $get_party = DB::table('parties')->where('id', $voting_for)->first();
                } else {
                    $get_party = DB::table('parties')->whereRaw('LOWER(name) = ?', [strtolower($voting_for)])->first();
                }
                if ($get_party) {
                    $query->where('ls.voting_for', $get_party->name);
                }
            }
    
            // Apply is_died filter
            if ($is_died !== null && $is_died !== '') {
                $query->where('ls.is_died', $is_died);
            }
    
            // Apply died_date filter
            if ($died_date !== null && $died_date !== '') {
                $query->where('ls.died_date', $died_date);
            }
    
            // Apply voting_decision filter
            if (!empty($voting_decision)) {
                $query->where('ls.voting_decision', $voting_decision);
            }
    
            // Apply located filter
            if (!empty($located)) {
                $query->whereRaw('LOWER(ls.located) = ?', [strtolower($located)]);
            }
    
            // Apply polling filter
            if (!empty($polling) && is_numeric($polling)) {
                $query->where('v.polling', $polling);
            }
    
            // Apply under_age_25 filter
            if ($underAge25 === 'yes') {
                $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, v.dob)) < 25');
            }
    
            // Apply user_id filter
            if (!empty($user_id)) {
                $query->where('ls.user_id', $user_id);
            }
    
            // Apply date range filters
            if (!empty($start_date)) {
                $query->where('ls.created_at', '>=', $start_date . ' 00:00:00');
            }
            if (!empty($end_date)) {
                $query->where('ls.created_at', '<=', $end_date . ' 23:59:59');
            }
    
            // Apply name filters
            if (!empty($surname)) {
                $query->whereRaw('LOWER(v.surname) LIKE ?', ['%' . strtolower($surname) . '%']);
            }
            if (!empty($firstName)) {
                $query->whereRaw('LOWER(v.first_name) LIKE ?', ['%' . strtolower($firstName) . '%']);
            }
            if (!empty($secondName)) {
                $query->whereRaw('LOWER(v.second_name) LIKE ?', ['%' . strtolower($secondName) . '%']);
            }
    
            // Apply address filters
            $query->where(function($q) use ($houseNumber, $address, $pobse, $pobis, $pobcn) {
                if ($houseNumber !== null && $houseNumber !== '') {
                    $q->whereRaw('LOWER(v.house_number) = ?', [strtolower($houseNumber)]);
                }
                if ($address !== null && $address !== '') {
                    $q->whereRaw('LOWER(v.address) = ?', [strtolower($address)]);
                }
                if ($pobse !== null && $pobse !== '') {
                    $q->whereRaw('LOWER(v.pobse) = ?', [strtolower($pobse)]);
                }
                if ($pobis !== null && $pobis !== '') {
                    $q->whereRaw('LOWER(v.pobis) = ?', [strtolower($pobis)]);
                }
                if ($pobcn !== null && $pobcn !== '') {
                    $q->whereRaw('LOWER(v.pobcn) = ?', [strtolower($pobcn)]);
                }
            });
    
            // Apply voter ID filter
            if (!empty($voterId) && is_numeric($voterId)) {
                $query->where('v.voter', $voterId);
            }
    
            // Apply constituency name filter
            if (!empty($constituencyName)) {
                $query->whereRaw('LOWER(c.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
            }
    
            // Apply constituency ID filter
            if (!empty($constituencyId) && is_numeric($constituencyId)) {
                $query->where('v.const', $constituencyId);
            }
    
            // Subquery: total voters per polling (all voters in that polling with same voter/constituency filters)
            $constituencyIds = explode(',', auth()->user()->constituency_id);
            $totalVotersSubquery = DB::table('voters as v2')
                ->leftJoin('constituencies as c2', 'v2.const', '=', 'c2.id')
                ->select('v2.polling', DB::raw('COUNT(DISTINCT v2.id) as total_voters'))
                ->whereIn('v2.const', $constituencyIds)
                ->groupBy('v2.polling');
            // Apply same voter-level and constituency-level filters (no survey filters)
            if ($existsInDatabase === 'true') {
                $totalVotersSubquery->where('v2.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $totalVotersSubquery->where('v2.exists_in_database', false);
            }
            if (!empty($constituencyId) && is_numeric($constituencyId)) {
                $totalVotersSubquery->where('v2.const', $constituencyId);
            }
            if (!empty($constituencyName)) {
                $totalVotersSubquery->whereRaw('LOWER(c2.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
            }
            if (!empty($polling) && is_numeric($polling)) {
                $totalVotersSubquery->where('v2.polling', $polling);
            }
            if ($underAge25 === 'yes') {
                $totalVotersSubquery->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, v2.dob)) < 25');
            }
            if (!empty($surname)) {
                $totalVotersSubquery->whereRaw('LOWER(v2.surname) LIKE ?', ['%' . strtolower($surname) . '%']);
            }
            if (!empty($firstName)) {
                $totalVotersSubquery->whereRaw('LOWER(v2.first_name) LIKE ?', ['%' . strtolower($firstName) . '%']);
            }
            if (!empty($secondName)) {
                $totalVotersSubquery->whereRaw('LOWER(v2.second_name) LIKE ?', ['%' . strtolower($secondName) . '%']);
            }
            if (!empty($voterId) && is_numeric($voterId)) {
                $totalVotersSubquery->where('v2.voter', $voterId);
            }
            $totalVotersSubquery->where(function ($q) use ($houseNumber, $address, $pobse, $pobis, $pobcn) {
                if ($houseNumber !== null && $houseNumber !== '') {
                    $q->whereRaw('LOWER(v2.house_number) = ?', [strtolower($houseNumber)]);
                }
                if ($address !== null && $address !== '') {
                    $q->whereRaw('LOWER(v2.address) = ?', [strtolower($address)]);
                }
                if ($pobse !== null && $pobse !== '') {
                    $q->whereRaw('LOWER(v2.pobse) = ?', [strtolower($pobse)]);
                }
                if ($pobis !== null && $pobis !== '') {
                    $q->whereRaw('LOWER(v2.pobis) = ?', [strtolower($pobis)]);
                }
                if ($pobcn !== null && $pobcn !== '') {
                    $q->whereRaw('LOWER(v2.pobcn) = ?', [strtolower($pobcn)]);
                }
            });

            $query->leftJoinSub($totalVotersSubquery, 'tv', 'v.polling', '=', 'tv.polling');
    
            // Select aggregated data by polling division (NO pagination for Excel - get ALL rows)
            $results = $query->select(
                'v.polling as polling_division',
                // Total voters in this polling (all voters matching filters)
                DB::raw('COALESCE(MAX(tv.total_voters), COUNT(DISTINCT v.id)) as total_voters'),
                DB::raw("COUNT(DISTINCT CASE WHEN ls.voting_for = '$fnmName' THEN v.id END) as fnm_count"),
                DB::raw("COUNT(DISTINCT CASE WHEN ls.voting_for = '$plpName' THEN v.id END) as plp_count"),
                DB::raw("COUNT(DISTINCT CASE WHEN ls.voting_for = '$coiName' THEN v.id END) as coi_count"),
                DB::raw("COUNT(DISTINCT CASE WHEN ls.voting_for IS NOT NULL AND ls.voting_for NOT IN ('$fnmName', '$plpName', '$coiName') THEN v.id END) as other_count"),
                DB::raw("COUNT(DISTINCT CASE WHEN ls.voting_for IS NULL THEN v.id END) as no_vote_count"),
                DB::raw("COUNT(DISTINCT v.id) as total_count"),
                DB::raw("ROUND((COUNT(DISTINCT CASE WHEN ls.voting_for = '$fnmName' THEN v.id END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as fnm_percentage"),
                DB::raw("ROUND((COUNT(DISTINCT CASE WHEN ls.voting_for = '$plpName' THEN v.id END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as plp_percentage"),
                DB::raw("ROUND((COUNT(DISTINCT CASE WHEN ls.voting_for = '$coiName' THEN v.id END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as coi_percentage"),
                DB::raw("ROUND((COUNT(DISTINCT CASE WHEN ls.voting_for IS NOT NULL AND ls.voting_for NOT IN ('$fnmName', '$plpName', '$coiName') THEN v.id END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as other_percentage"),
                DB::raw("ROUND((COUNT(DISTINCT CASE WHEN ls.voting_for IS NULL THEN v.id END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as no_vote_percentage")
            )
            ->groupBy('v.polling')
            ->orderBy('v.polling', 'asc')
            ->get();  // Get ALL rows for Excel (no pagination)
    
            // Transform: add total_party_count and ensure total_voters/total_count are integers
            $results->transform(function ($item) {
                $item->total_voters = (int) ($item->total_voters ?? $item->total_count ?? 0);
                $item->total_count = (int) $item->total_count;
                $item->total_party_count = (int) $item->fnm_count + (int) $item->plp_count + (int) $item->coi_count + (int) $item->other_count;
                return $item;
            });
    
            $columns = array_map(function($column) {
                return strtolower(urldecode(trim($column)));
            }, explode(',', $_GET['columns'] ?? ''));
       
            $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
            return Excel::download(new Reports4Export($results, $request, $columns), 'Election Projections Report_' . $timestamp . '.xlsx'); 
        }

        
    
}
