<?php

namespace App\Http\Controllers\Admin\Exports;

use App\Http\Controllers\Controller;
use App\Models\Voter;
use App\Models\Party;
use Illuminate\Http\Request;
use App\Models\UnregisteredVoter;
use App\Models\Survey;
use App\Exports\VotersDiffAddressExport;
use App\Exports\VotersExport;
use App\Exports\SurveyVotersExport; 
use App\Exports\SingleUser_SurveysExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
class VoterController extends Controller 
{
   
 
    
    public function getVotersInSurveyDetails(Request $request, $id)
    { 
        // Increase memory limit for large exports
        ini_set('memory_limit', '512M');
        
        // Check admin authorization
        if (!auth()->check() || auth()->user()->role->name !== 'Admin') { 
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }

        // Get search parameters
        $surname = $request->input('surname');
        $firstName = $request->input('first_name');
        $secondName = $request->input('second_name');
        $address = $request->input('address');
        $voterId = $request->input('voter_id');
        $constituencyName = $request->input('constituency_name');
        $underAge25 = $request->input('under_age_25');
        $pobse = $request->input('pobse');
        $pobis = $request->input('pobis');
        $pobcn = $request->input('pobcn'); 
        $houseNumber = $request->input('house_number');
        $polling = $request->input('polling');
        $voting_decision = $request->input('voting_decision');
        $located = $request->input('located');
        $query = Voter::with('user')
        ->select('voters.*', 'constituencies.name as constituency_name', 
        'surveys.id as survey_id', 'surveys.created_at as survey_date','surveys.user_id','surveys.located','surveys.voting_decision','surveys.voting_for','surveys.special_comments','surveys.other_comments')
        ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
        ->join('surveys', 'voters.id', '=', 'surveys.voter_id')
        ->whereExists(function ($query) {
            $query->select('id')
                ->from('surveys')
                ->whereColumn('surveys.voter_id', 'voters.id');
        })
        
        ->orderBy('surveys.id', 'desc');

        if (!empty($located)) {
            $query->whereRaw('LOWER(surveys.located) = ?', [strtolower($located)]);
        }

        if (!empty($voting_decision)) {
            $query->where('surveys.voting_decision', $voting_decision);
        }


        if ($underAge25 === 'yes') {
            $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
        }

        // Apply search filters
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

        if (!empty($voterId) && is_numeric($voterId)) {
            $query->where('voters.voter', $voterId);
        }

        if (!empty($constituencyName)) {
            $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
        }

        // Get paginated results
        $voters = $query->where('surveys.user_id',$id)->get();

        $columns = array_map(function($column) {
        return strtolower(urldecode(trim($column)));
    }, explode(',', $_GET['columns']));
       
    $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
    return Excel::download(new VotersExport($voters, $request, $columns), 'Canvasser Voters In Survey_' . $timestamp . '.xlsx');  

    }



   public function getVotersInSurvey(Request $request)
   {
       // Increase memory limit for large exports
       ini_set('memory_limit', '1024M');
       
       // Check admin authorization
        if (!auth()->check() || auth()->user()->role->name !== 'Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }

        $const = $request->input('const');
        $surname = $request->input('surname');
        $firstName = $request->input('first_name');
        $secondName = $request->input('second_name');
        $address = $request->input('address');
        $voterId = $request->input('voter');
        $constituencyName = $request->input('constituency_name');
        $constituencyId = $request->input('const');
        $underAge25 = $request->input('under_age_25');
        $houseNumber = $request->input('house_number');
        $pobse = $request->input('pobse');
        $pobis = $request->input('pobis');
        $pobcn = $request->input('pobcn');
        $located = $request->input('located');
        $voting_decision = $request->input('voting_decision');
        $voting_for = $request->input('voting_for');
        $is_died = $request->input('is_died');
        $died_date = $request->input('died_date');
        $existsInDatabase = $request->input('exists_in_database');
        $challenge = $request->input('challenge');
        

        $latestSurveySubquery = DB::table('surveys')
        ->selectRaw('DISTINCT ON (voter_id) 
            voter_id,
            id,
            created_at,
            user_id,
            located,
            voting_decision,
            voting_for,
            is_died,
            died_date,
            work_phone_code,
            work_phone,
            cell_phone_code,
            cell_phone,
            email,
            challenge')
        ->orderBy('voter_id')
        ->orderBy('id', 'desc');
       
    // OPTIMIZED: Use leftJoinSub instead of raw join
    // This is more efficient and allows better query optimization
      $query = Voter::query()
        ->select(
            'voters.*',
            'constituencies.name as constituency_name',
            'ls.id as survey_id',
            'ls.created_at as survey_date',
            'ls.user_id',
            'ls.located',
            'ls.voting_decision',
            'ls.voting_for',
            'ls.is_died',
            'ls.died_date',
            'ls.work_phone_code',
            'ls.work_phone',
            'ls.cell_phone_code',
            'ls.cell_phone',
            'ls.email',
            'ls.challenge'
        )
        ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
        ->joinSub($latestSurveySubquery, 'ls', 'ls.voter_id', '=', 'voters.id')
        ->orderBy('ls.created_at', 'desc');
        


     


            // Apply search filters

            $searchableFields = [
                'first_name' => 'First Name',
                'second_name' => 'Second Name',
                'surname' => 'Surname', 
                'address' => 'Address',
                'voter' => 'Voter ID',
                'const' => 'Constituency ID',
                'constituency_name' => 'Constituency Name',
                'user_id' => 'User ID',
                'under_age_25' => 'Under 25',
                'polling' => 'Polling Station',
                'voting_decision' => 'Voting Decision',
                'located' => 'Located',
                'voting_for' => 'Voting For'
            ];  



            if ($challenge === 'true') {
                $query->whereRaw('ls.challenge IS TRUE');
            } elseif ($challenge === 'false') {
                $query->whereRaw('ls.challenge IS FALSE');
            }
            


            
            if ($existsInDatabase === 'true') {
                $query->where('voters.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $query->where('voters.exists_in_database', false);
            }

            $polling = $request->input('polling');

            if ($voting_for !== null && $voting_for !== '') {
                // Check if voting_for is numeric (ID) or a string (name)
                if (is_numeric($voting_for)) {
                    $get_party = Party::where('id', $voting_for)->first();
                } else {
                    // Search by name (case-insensitive)
                    $get_party = Party::whereRaw('LOWER(name) = ?', [strtolower($voting_for)])->first();
                }
                
                if ($get_party) {
                    $voting_for = $get_party->name;
                    $query->where('ls.voting_for', $voting_for);
                }
            }
            if($is_died !== null && $is_died !== ''){
                $query->where('surveys.is_died', $is_died);
            }
            if($died_date !== null && $died_date !== ''){
                $query->where('surveys.died_date', $died_date);
            }
            // Apply filters
            if (!empty($voting_decision)) {
                $query->where('surveys.voting_decision', $voting_decision);
            }

            if (!empty($located)) {
                $query->whereRaw('LOWER(surveys.located) = ?', [strtolower($located)]);
            }
            if (!empty($polling) && is_numeric($polling)) {
                $query->where('voters.polling', $polling);
            }
            $underAge25 = $request->input('under_age_25');
            if ($underAge25 === 'yes') {
                $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
            }
            
            if (isset($request->user_id) && !empty($request->user_id)) {
            
                $query->where('surveys.user_id',$request->user_id);
            }

            // Get search parameters
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

        if (!empty($constituencyId) && is_numeric($constituencyId)) {
            $query->where('voters.const', $constituencyId);
        }

        // Get paginated results with all surveys
        $voters = $query->get();

        $columns = array_map(function($column) {
            return strtolower(urldecode(trim($column)));
        }, explode(',', $_GET['columns']));
        $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
    
      
        
        return Excel::download(new SurveyVotersExport($voters, $request, $columns), 'Voters In Survey_' . $timestamp . '.xlsx');  


        return response()->json([
            'success' => true,
            'data' => $voters 
        ]);
   }
   public function getDiedVotersInSurvey(Request $request)
   {
       // Increase memory limit for large exports
       ini_set('memory_limit', '512M');
       
       // Check admin authorization
       if (!auth()->check() || auth()->user()->role->name !== 'Admin') {
           return response()->json([
               'success' => false,
               'message' => 'Unauthorized - Admin access required'
           ], 403);
       }

       $const = $request->input('const');
       $surname = $request->input('surname');
       $firstName = $request->input('first_name');
       $secondName = $request->input('second_name');
       $address = $request->input('address');
       $voterId = $request->input('voter');
       $constituencyName = $request->input('constituency_name');
       $constituencyId = $request->input('const');
       $underAge25 = $request->input('under_age_25');
       $houseNumber = $request->input('house_number');
       $pobse = $request->input('pobse');
       $pobis = $request->input('pobis');
       $pobcn = $request->input('pobcn');
       $located = $request->input('located');
       $voting_decision = $request->input('voting_decision');
       $voting_for = $request->input('voting_for');
       $is_died = $request->input('is_died');
       $died_date = $request->input('died_date');
       $existsInDatabase = $request->input('exists_in_database');

       // OPTIMIZED: Use DISTINCT ON subquery to get only the latest survey per voter
       $latestSurveySubquery = DB::table('surveys')
           ->selectRaw('DISTINCT ON (voter_id) 
               voter_id,
               id,
               created_at,
               user_id,
               located,
               voting_decision,
               voting_for,
               is_died,
               died_date')
           ->orderBy('voter_id')
           ->orderBy('id', 'desc');

       $query = Voter::query()
           ->select(
               'voters.*',
               'constituencies.name as constituency_name',
               'ls.id as survey_id',
               'ls.created_at as survey_date',
               'ls.user_id',
               'ls.located',
               'ls.voting_decision',
               'ls.voting_for',
               'ls.is_died',
               'ls.died_date'
           )
           ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
           ->joinSub($latestSurveySubquery, 'ls', 'ls.voter_id', '=', 'voters.id')
           ->where('ls.is_died', 1)
           ->orderBy('ls.id', 'desc');

        $searchableFields = [
            'first_name' => 'First Name',
            'second_name' => 'Second Name',
            'surname' => 'Surname', 
            'address' => 'Address',
            'voter' => 'Voter ID',
            'const' => 'Constituency ID',
            'constituency_name' => 'Constituency Name',
            'user_id' => 'User ID',
            'under_age_25' => 'Under 25',
            'polling' => 'Polling Station',
            'voting_decision' => 'Voting Decision',
            'located' => 'Located',
            'voting_for' => 'Voting For'
        ];  

        $polling = $request->input('polling');

        // Apply voting_for filter
        if ($voting_for !== null && $voting_for !== '') {
            if (is_numeric($voting_for)) {
                $get_party = Party::where('id', $voting_for)->first();
            } else {
                $get_party = Party::whereRaw('LOWER(name) = ?', [strtolower($voting_for)])->first();
            }
            if ($get_party) {
                $query->where('ls.voting_for', $get_party->name);
            }
        }
        
        if ($is_died !== null && $is_died !== '') {
            $query->where('ls.is_died', $is_died);
        }
        if ($died_date !== null && $died_date !== '') {
            $query->where('ls.died_date', $died_date);
        }
        if ($existsInDatabase === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false);
        }

        if (!empty($voting_decision)) {
            $query->where('ls.voting_decision', $voting_decision);
        }

        if (!empty($located)) {
            $query->whereRaw('LOWER(ls.located) = ?', [strtolower($located)]);
        }
        if (!empty($polling) && is_numeric($polling)) {
            $query->where('voters.polling', $polling);
        }
        if ($underAge25 === 'yes') {
            $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
        }
       
        if (isset($request->user_id) && !empty($request->user_id)) {
            $query->where('ls.user_id', $request->user_id);
        }

        if (isset($request->start_date) && !empty($request->start_date)) {
            $query->where('ls.died_date', '>=', $request->start_date . ' 00:00:00');
        }

        if (isset($request->end_date) && !empty($request->end_date)) {
            $query->where('ls.died_date', '<=', $request->end_date . ' 23:59:59');
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

       if (!empty($constituencyId) && is_numeric($constituencyId)) {
            $query->where('voters.const', $constituencyId);
       }

       // Get results - one voter per row (latest survey only)
       $voters = $query->get();

       $columns = array_map(function($column) {
        return strtolower(urldecode(trim($column)));
    }, explode(',', $_GET['columns']));
         
    $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
    return Excel::download(new SurveyVotersExport($voters, $request, $columns), 'Voters In Survey_' . $timestamp . '.xlsx');  


       return response()->json([
           'success' => true,
           'data' => $voters 
       ]);
   }

   public function getVotersNotInSurveyExport(Request $request)
   {
       // Increase memory limit for large exports
       ini_set('memory_limit', '512M');
       
       // Check admin authorization
       if (!auth()->check() || auth()->user()->role->name !== 'Admin') {
           return response()->json([
               'success' => false,
               'message' => 'Unauthorized - Admin access required'
           ], 403);
       }

       // Get search parameters
       $surname = $request->input('surname');
       $firstName = $request->input('first_name');
       $secondName = $request->input('second_name');
       $address = $request->input('address');
       $voterId = $request->input('voter');
       $constituencyName = $request->input('constituency_name'); 
       $underAge25 = $request->input('under_age_25');
       $pobse = $request->input('pobse');
       $pobis = $request->input('pobis');
       $pobcn = $request->input('pobcn'); 
       $houseNumber = $request->input('house_number');
       $polling = $request->input('polling');
       $constituencyId = $request->input('const');
       $existsInDatabase = $request->input('exists_in_database');
      

       if((!isset($constituencyId) || $constituencyId == '') && (!isset($constituencyName) || $constituencyName == '')){
        return response()->json([
            'success' => false,
            'message' => 'Please select either constituency or constituency name first'
        ], 400); 
       }else{


                $query = Voter::query()
                ->select('voters.*', 'constituencies.name as constituency_name')
                ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
                ->whereNotExists(function ($query) {
                    $query->select('id')
                        ->from('surveys')
                        ->whereColumn('surveys.voter_id', 'voters.id');
                });
                
                if ($existsInDatabase === 'true') {
                    $query->where('voters.exists_in_database', true);
                } elseif ($existsInDatabase === 'false') {
                    $query->where('voters.exists_in_database', false);
                }

                if (!empty($constituencyId) && is_numeric($constituencyId)) {
                    $query->where('voters.const', $constituencyId);
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

            // Apply search filters
            if ($underAge25 === 'yes') {
                $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
            }
                // Apply search filters
                if (isset($request->start_date) && !empty($request->start_date)) {
                    $query->where('voters.created_at', '>=', $request->start_date . ' 00:00:00');
                    }
            
                    if (isset($request->end_date) && !empty($request->end_date)) {
                        $query->where('voters.created_at', '<=', $request->end_date . ' 23:59:59');
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

            // Get paginated results
            $voters = $query->orderBy('voters.id', 'desc')->get();

            $columns = array_map(function($column) {
                return strtolower(urldecode(trim($column)));
            }, explode(',', $_GET['columns']));
            
            $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
            return Excel::download(new VotersExport($voters, $request, $columns), 'Voters Not In Survey_' . $timestamp . '.xlsx');  



       }
  
        
   }
 
   
   
 


   public function getUserSurveys(Request $request, $id)
   {
        // Increase memory limit for large exports
        ini_set('memory_limit', '512M');
        
        if (!auth()->check() || auth()->user()->role->name !== 'Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }
        $query = Survey::with(['voter', 'voter.constituency']);

        // Search fields based on Survey model's fillable columns
       
        $polling = $request->input('polling');
        $houseNumber = $request->input('house_number');
        $address = $request->input('address');
        $pobse = $request->input('pobse');
        $pobis = $request->input('pobis');
        $pobcn = $request->input('pobcn');
        $voting_decision = $request->input('voting_decision');
        $located = $request->input('located');
        $voting_for = $request->input('voting_for');
        $is_died = $request->input('is_died');
        $died_date = $request->input('died_date');

        if( $voting_for !== null && $voting_for !== ''){
        
            $get_party = Party::where('id', $voting_for)->first();
            $voting_for = $get_party->name;
            $query->where('surveys.voting_for', $voting_for);
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

        if($is_died !== null && $is_died !== ''){
            $query->where('surveys.is_died', $is_died);
        }
        if($died_date !== null && $died_date !== ''){
            $query->where('surveys.died_date', $died_date);
        }
        

        if (!empty($voting_decision)) {
            $query->where('voting_decision', $voting_decision);
        }

        if (!empty($located)) {
            $query->whereRaw('LOWER(located) = ?', [strtolower($located)]);
        }

        $underAge25 = $request->input('under_age_25');
        if ($underAge25 === 'yes') {
            $query->whereHas('voter', function($q) {
                $q->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, dob)) < 25');
            });
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        if ($request->filled('surname')) {
            $query->whereHas('voter', function($q) use ($request) {
                $q->whereRaw('LOWER(surname) LIKE ?', ['%' . strtolower($request->surname) . '%']);
            });
        }

        if ($request->filled('first_name')) {
            $query->whereHas('voter', function($q) use ($request) {
                $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->first_name) . '%']);
            });
        }

        if ($request->filled('second_name')) {
            $query->whereHas('voter', function($q) use ($request) {
                $q->whereRaw('LOWER(second_name) LIKE ?', ['%' . strtolower($request->second_name) . '%']);
            });
        }

        if ($request->filled('voter_id')) {
            $query->whereHas('voter', function($q) use ($request) {
                $q->where('voter', $request->voter_id);
            });
        }

        if ($request->filled('sex')) {
            $query->where('sex', $request->sex);
        }

        $surveys = $query->where('user_id', $id)
                        ->orderBy('id', 'desc')
                        ->get();

        if (!isset($_GET['columns'])) {
            return response()->json([
                'success' => false,
                'message' => 'Columns parameter is required'
            ], 400);
        }

        $columns = array_map(function($column) {
            return strtolower(urldecode(trim($column)));
        }, explode(',', $_GET['columns']));
     
        $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
        return Excel::download(
            new SingleUser_SurveysExport($surveys, $request, $columns), 
            'Voters In Survey_' . $timestamp . '.xlsx'
        );
   } 



   public function getVotersDiffAddress(Request $request)
   {
       // Increase memory limit for large exports
       ini_set('memory_limit', '512M');
       
    if (!auth()->check() || auth()->user()->role->name !== 'Admin') {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized - Admin access required'
        ], 403);
    }
  
    $query = Voter::query()
    ->select(
        'voters.*',
        'constituencies.name as constituency_name',
        'new_constituencies.name as new_constituency_name' // Alias for the second join
    )
    ->join('constituencies', 'voters.const', '=', 'constituencies.id')
    ->leftJoin('constituencies as new_constituencies', 'voters.living_constituency', '=', 'new_constituencies.id') // Second join
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
    $voters = $query->orderBy('id', 'desc')->get(); 
          
        $columns = array_map(function($column) {
            return strtolower(urldecode(trim($column)));
        }, explode(',', $_GET['columns']));
        
        $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
        return Excel::download(new VotersDiffAddressExport($voters, $request, $columns), 'Voters Diff Address_' . $timestamp . '.xlsx');  
   } 



   public function nationalRegisteryList(Request $request)  
   {   
        
    
       $query = Voter::with(['constituency','user','living_constituency','surveyer_constituency'])
           ->where('voters.is_national', 1); 
            

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
       $existsInDatabase = $request->input('exists_in_database'); 
       $isVoted = $request->input('is_voted');
       $advance_poll = $request->input('advance_poll');
       $export = $request->input('export');


       $living_constituency_name = $request->input('living_constituency_name');
       
       // Filter by living constituency name (explicit join so filter works reliably)
       if ($living_constituency_name !== null && $living_constituency_name !== '' && trim($living_constituency_name) !== '') {
           $query->join('constituencies as living_const', 'voters.living_constituency', '=', 'living_const.id')
               ->whereRaw('LOWER(living_const.name) LIKE ?', ['%' . strtolower(trim($living_constituency_name)) . '%'])
               ->select('voters.*'); // avoid duplicate columns from join
       }
       



       $partyId = $request->input('voting_for');
       // if ($partyId) {
       //     $partyId = Party::where('name', $partyId)->first();
       //     $partyShortName = strtolower($partyId->short_name);
       //     $query->whereRaw('LOWER(vci.exit_poll) = ?', [$partyShortName]);
       // }

       if ($partyId) {
           $partyId = Party::where('name', $partyId)->first();
           $partyShortName = strtolower($partyId->name);
           $query->whereRaw('LOWER(ls.voting_for) = ?', [$partyShortName]);
       }

       // $partyId = $request->input('voting_for');
       // if ($partyId) {
       //     $query->where('ls.voting_for', $partyId);
       // }


       if ($advance_poll == 'yes') {
           $query->where('voters.flagged', 1);
       }

       if ($isVoted === 'yes') {
           $query->whereExists(function ($q) {
               $q->select(DB::raw(1))
                 ->from('voter_cards_images')
                 ->whereColumn('voter_cards_images.reg_no', 'voters.voter'); 
           });
       }  


       if ($isVoted === 'no') {
           $query->whereNotExists(function ($q) {
               $q->select(DB::raw(1))
                 ->from('voter_cards_images')
                 ->whereColumn('voter_cards_images.reg_no', 'voters.voter'); 
           });

       }
       // Get sorting parameters
       $sortBy = $request->input('sort_by'); // voter, const, or polling
       $sortOrder = $request->input('sort_order', 'asc'); // asc or desc

       // Validate sort order
       $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc']) ? strtolower($sortOrder) : 'asc'; 


       // Apply filters

       if (!empty($polling) && is_numeric($polling)) {
           $query->where('voters.polling', $polling);
       }

       if ($underAge25 === 'yes') {
           $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
       }

       // Apply exists_in_database filter
       if ($existsInDatabase === 'true') { 
           $query->where('voters.exists_in_database', true);
       } elseif ($existsInDatabase === 'false') {
           $query->where('voters.exists_in_database', false);
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
           $query->whereHas('constituency', function ($q) use ($constituencyName) {
               $q->whereRaw(
                   'LOWER(name) LIKE ?',
                   ['%' . strtolower($constituencyName) . '%']
               );
           });
       }

       if (!empty($constituencyId)) {
           $query->where('voters.const', $constituencyId);
       }


       if (!empty($sortBy)) {
           switch ($sortBy) {
               case 'voter':
                   $query->orderBy('voters.voter', $sortOrder);
                   break;
               case 'const':
                   $query->orderBy('voters.const', $sortOrder);
                   break;
               case 'polling':
                   $query->orderBy('voters.polling', $sortOrder);
                   break;
               case 'first_name':
                   $query->orderByRaw('LOWER(voters.first_name) ' . strtoupper($sortOrder));
                   break;
               case 'last_name':
                   $query->orderByRaw('LOWER(TRIM(voters.surname)) ' . strtoupper($sortOrder));
                   break;
               default:
                   $query->orderBy('voters.id', 'desc'); 
                   break;
           }
       } else {
           // Default sorting
           $query->orderBy('voters.id', 'desc');
       }

       // Get paginated results
       
       if($export == 'true'){
           $voters = $query->get();
       }else{
           $voters = $query->get();
       }
      
       $columns = array_map(function($column) {
        return strtolower(urldecode(trim($column)));
    }, explode(',', $_GET['columns']));
 
    $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
    return Excel::download(new VotersExport($voters, $request, $columns), 'Voters Diff Address_' . $timestamp . '.xlsx');  
    
   }

} 