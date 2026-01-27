<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Voter;
use App\Models\Survey;
use App\Models\VoterHistory;
use App\Models\Constituency;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\DailySurveyTrack;
use App\Models\UnregisteredVoter;
use App\Models\VoterCardImage;
use App\Models\Party;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
class UserVoterController extends Controller
{
 // ... existing code ...

 


 public function nationalRegisteryList(Request $request) 
 {   
   
 
    $user = Auth::user();
    if(empty($user)){
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 403);
    }
     $query = Voter::with(['constituency','user','living_constituency','surveyer_constituency'])
         ->where('voters.is_national', 1)
         ->where('voters.user_id', $user->id); 
         

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
         $voters = $query->paginate($request->get('per_page', 20));
     }
    
     
     return response()->json([
         'success' => true,
         'data' => $voters,
         // 'searchable_fields' => $searchableFields,
         'sorting_options' => [
             'voter' => 'Voter ID',
             'const' => 'Constituency ID',
             'polling' => 'Polling Station'
         ],
         'current_sort' => [
             'sort_by' => $sortBy,
             'sort_order' => $sortOrder
         ]
     ]); 
 }

public function getVotersInSurvey(Request $request)
{
   
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
    $located = $request->input('located');
    $voting_decision = $request->input('voting_decision');
    $voting_for = $request->input('voting_for');
    $is_died = $request->input('is_died');
    $died_date = $request->input('died_date');
    $existsInDatabase = $request->input('exists_in_database');


    $query = Voter::with('user')
        ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date','surveys.user_id','surveys.voting_for','surveys.voting_decision','surveys.located','surveys.is_died','surveys.died_date')
        ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
        ->join('surveys', 'voters.id', '=', 'surveys.voter_id')
        ->whereExists(function ($query) {
            $query->select('id')
                ->from('surveys')
                ->whereColumn('surveys.voter_id', 'voters.id');
        })
        ->where('surveys.user_id', Auth::id())
        ->orderBy('surveys.id', 'desc'); 

    // Apply exists_in_database filter
    if ($existsInDatabase === 'true') {
        $query->where('voters.exists_in_database', true);
    } elseif ($existsInDatabase === 'false') {
        $query->where('voters.exists_in_database', false);
    } else {
        // Default behavior - keep existing logic
        $query->where('voters.exists_in_database', false);
    }

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
         'voting_decision' => 'Voting Decision',
         'voting_for' => 'Voting For'

     ];  

     if( $voting_for !== null && $voting_for !== ''){
        
        $get_party = Party::where('id', $voting_for)->first();
        $voting_for = $get_party->name;
        $query->where('surveys.voting_for', $voting_for);
   }

    if($is_died !== null && $is_died !== ''){
     
        $query->where('surveys.is_died', $is_died);
        
        
    }
    
    if($died_date !== null && $died_date !== ''){
        $query->where('surveys.died_date', $died_date);
    }

   

     if (!empty($located)) {
        $query->whereRaw('LOWER(surveys.located) = ?', [strtolower($located)]);
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
        $query->where('voters.id', $voterId); // Changed from voters.voter to voters.id since voter ID should match the primary key
    }

    if (!empty($constituencyName)) {
        $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
    }

    if (!empty($constituencyId) && is_numeric($constituencyId)) {
         $query->where('voters.const', $constituencyId);
    }

    // Get paginated results with all surveys
    $voters = $query->paginate($request->get('per_page', 20));

    
    return response()->json([
        'success' => true,
        'data' => $voters,
        'searchable_fields' => $searchableFields
    ]);
} 
public function getDiedVotersInSurvey(Request $request)
{
   
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
    $located = $request->input('located');
    $voting_decision = $request->input('voting_decision');
    $voting_for = $request->input('voting_for');
    $is_died = $request->input('is_died');
    $died_date = $request->input('died_date');
    $existsInDatabase = $request->input('exists_in_database');


    $query = Voter::with('user')
        ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date','surveys.user_id','surveys.voting_for','surveys.voting_decision','surveys.located','surveys.is_died','surveys.died_date')
        ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
        ->join('surveys', 'voters.id', '=', 'surveys.voter_id')
        ->whereExists(function ($query) {
            $query->select('id')
                ->from('surveys')
                ->whereColumn('surveys.voter_id', 'voters.id');
        })
        ->where('surveys.user_id', Auth::id())
        ->where('surveys.is_died', 1)
        ->orderBy('surveys.id', 'desc'); 

    // Apply exists_in_database filter
    if ($existsInDatabase === 'true') {
        $query->where('voters.exists_in_database', true);
    } elseif ($existsInDatabase === 'false') {
        $query->where('voters.exists_in_database', false);
    } else {
        // Default behavior - keep existing logic
        $query->where('voters.exists_in_database', false);
    }

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
         'voting_decision' => 'Voting Decision',
         'voting_for' => 'Voting For'

     ];  

     if( $voting_for !== null && $voting_for !== ''){
        
        $get_party = Party::where('id', $voting_for)->first();
        $voting_for = $get_party->name;
        $query->where('surveys.voting_for', $voting_for);
   }

    if($is_died !== null && $is_died !== ''){
     
        $query->where('surveys.is_died', $is_died);
        
        
    }
    
    

   

     if (!empty($located)) {
        $query->whereRaw('LOWER(surveys.located) = ?', [strtolower($located)]);
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
         $query->where('surveys.died_date', '>=', $request->start_date . ' 00:00:00');
     }

     if (isset($request->end_date) && !empty($request->end_date)) {
         $query->where('surveys.died_date', '<=', $request->end_date . ' 23:59:59');
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
        $query->where('voters.id', $voterId); // Changed from voters.voter to voters.id since voter ID should match the primary key
    }

    if (!empty($constituencyName)) {
        $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
    }

    if (!empty($constituencyId) && is_numeric($constituencyId)) {
         $query->where('voters.const', $constituencyId);
    }

    // Get paginated results with all surveys
    $voters = $query->paginate($request->get('per_page', 20));

    
    return response()->json([
        'success' => true,
        'data' => $voters,
        'searchable_fields' => $searchableFields
    ]);
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
        ->where('voters.diff_address', 'yes')
        ->whereIn('voters.const', $constituency_ids); 

      
        // Get pagination parameters
        $perPage = $request->input('per_page', 10);

        // Add search filters
        $firstName = $request->input('first_name');
        $secondName = $request->input('second_name');
        $surname = $request->input('surname');
        $address = $request->input('address');
        $houseNumber = $request->input('house_number');
        $pobis = $request->input('pobis');
        $pobcn = $request->input('pobcn');
        $pobse = $request->input('pobse');
        $voterId = $request->input('voter_id');
        $const = $request->input('const');
        $constituencyName = $request->input('constituency_name');
        $underAge25 = $request->input('under_age_25');
        $polling = $request->input('polling');
        $new_constituency = $request->input('new_constituency');
        $new_constituencyName = $request->input('new_constituencyName'); 
        $existsInDatabase = $request->input('exists_in_database');
        
        if (!empty($polling)) { 
            $query->where('voters.polling', $polling);
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

        if (!empty($const)) {
            $query->where('voters.const', $const);
        }
        if (!empty($new_constituency)) {
            $query->where('voters.living_constituency', $new_constituency);
        } 

        if (!empty($constituencyName)) {
            $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
        }
        if (!empty($new_constituencyName)) {
            $query->whereRaw('LOWER(new_constituencies.name) LIKE ?', ['%' . strtolower($new_constituencyName) . '%']);
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

        // Add sorting
        $sortBy = $request->input('sort_by', 'voters.surname');
        $sortOrder = $request->input('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Get paginated results
        $voters = $query->paginate($perPage);

        // Define searchable parameters
        $searchableParameters = [
            'first_name' => 'First Name',
            'second_name' => 'Second Name', 
            'surname' => 'Surname',
            'address' => 'Address',
            'house_number' => 'House Number',
            'pobis' => 'Island',
            'pobcn' => 'Country',
            'voter_id' => 'Voter ID',
            'const' => 'Constituency Id',
            'constituency_name' => 'Constituency Name',
            'under_age_25' => 'Under Age 25',
            'new_constituency' => 'New Constituency',
            'new_constituencyName' => 'New Constituency Name'
        ];

        return response()->json([
            'success' => true,
            'data' => $voters,
            'searchable_parameters' => $searchableParameters
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error retrieving voters list',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function getVotersNotInSurvey(Request $request)
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
        $underAge25 = $request->input('under_age_25');
        $polling = $request->input('polling');
        $houseNumber = $request->input('house_number');
        $pobse = $request->input('pobse');
        $pobis = $request->input('pobis');
        $pobcn = $request->input('pobcn');  
        $existsInDatabase = $request->input('exists_in_database');
        // Query voters who don't have a survey entry
        $query = Voter::select('voters.*', 'constituencies.name as constituency_name')
            ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
            ->whereIn('voters.const', $constituency_ids)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('surveys')
                    ->whereRaw('surveys.voter_id = voters.id');
                    //->where('surveys.user_id', Auth::id());
            }); 

            if (!empty($polling)) {
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

        if (!empty($constituencyId)) {
            $query->where('voters.const', $constituencyId);
        }

        // Add sorting
        $query->orderBy('voters.id', 'desc');

        // Get paginated results
        $voters = $query->paginate($perPage);

        $searchableParameters = [
            'first_name' => 'First Name',
            'second_name' => 'Second Name', 
            'surname' => 'Surname',
            'address' => 'Address',
            'voter_id' => 'Voter ID',
            'const' => 'Constituency Id',
            'constituency_name' => 'Constituency Name',
            'polling' => 'Polling'
        ];


        return response()->json([
            'success' => true,
            'data' => $voters,
            'searchable_parameters' => $searchableParameters
             
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error retrieving unsurveyed voters',
            'error' => $e->getMessage()
        ], 500);
    }
} 



public function getVotersList(Request $request)
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
            ->select('voters.*', 'constituencies.name as constituency_name')
            ->join('constituencies', 'voters.const', '=', 'constituencies.id')
            ->whereIn('voters.const', $constituency_ids);

        // Get pagination parameters
        $perPage = $request->input('per_page', 10);

        // Add search filters
        $firstName = $request->input('first_name');
        $secondName = $request->input('second_name');
        $surname = $request->input('surname');
        $address = $request->input('address');
        $houseNumber = $request->input('house_number');
        $pobis = $request->input('pobis');
        $pobcn = $request->input('pobcn');
        $pobse = $request->input('pobse');
        $voterId = $request->input('voter_id');
        $const = $request->input('const');
        $constituencyName = $request->input('constituency_name');
        $underAge25 = $request->input('under_age_25');
        $polling = $request->input('polling');
        $existsInDatabase = $request->input('exists_in_database');
        
        if ($existsInDatabase === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false);
        }
        if (!empty($polling)) { 
            $query->where('voters.polling', $polling);
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

        if (!empty($const)) {
            $query->where('voters.const', $const);
        }

        if (!empty($constituencyName)) {
            $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
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

        // Add sorting
        $sortBy = $request->input('sort_by', 'voters.surname');
        $sortOrder = $request->input('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Get paginated results
        $voters = $query->paginate($perPage);

        // Define searchable parameters
        $searchableParameters = [
            'first_name' => 'First Name',
            'second_name' => 'Second Name', 
            'surname' => 'Surname',
            'address' => 'Address',
            'house_number' => 'House Number',
            'pobis' => 'Island',
            'pobcn' => 'Country',
            'voter_id' => 'Voter ID',
            'const' => 'Constituency Id',
            'constituency_name' => 'Constituency Name',
            'under_age_25' => 'Under Age 25'
        ];

        return response()->json([
            'success' => true,
            'data' => $voters,
            'searchable_parameters' => $searchableParameters,
            'exists_in_database' => $existsInDatabase
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error retrieving voters list',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function newlyRegistered(Request $request)
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
            ->select('voters.*', 'constituencies.name as constituency_name')
            ->join('constituencies', 'voters.const', '=', 'constituencies.id')
            ->whereIn('voters.const', $constituency_ids)
            ->where('voters.newly_registered', 1);

        // Get pagination parameters
        $perPage = $request->input('per_page', 10);

        // Add search filters
        $firstName = $request->input('first_name');
        $secondName = $request->input('second_name');
        $surname = $request->input('surname');
        $address = $request->input('address');
        $voterId = $request->input('voter_id');
        $const = $request->input('const');
        $constituencyName = $request->input('constituency_name');
        $underAge25 = $request->input('under_age_25');
        $polling = $request->input('polling');
        $houseNumber = $request->input('house_number');
        $pobse = $request->input('pobse');
        $pobis = $request->input('pobis');
        $pobcn = $request->input('pobcn'); 
        $existsInDatabase = $request->input('exists_in_database');


        if ($underAge25 === 'yes') {
            $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
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

        if (!empty($const)) {
            $query->where('voters.const', $const);
        }

        
        if (!empty($constituencyName)) {
            $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
        }

        if (!empty($polling)) {
            $query->where('voters.polling', $polling);
        }

        // Apply exists_in_database filter
        if ($existsInDatabase === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false);
        }

        // Add sorting
        $sortBy = $request->input('sort_by', 'voters.surname');
        $sortOrder = $request->input('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Get paginated results
        $voters = $query->paginate($perPage);

        // Define searchable parameters
        $searchableParameters = [
            'first_name' => 'First Name',
            'second_name' => 'Second Name', 
            'surname' => 'Surname',
            'address' => 'Address',
            'voter_id' => 'Voter ID',
            'const' => 'Constituency Id',
            'constituency_name' => 'Constituency Name',
            'polling' => 'Polling'
        ];

        return response()->json([
            'success' => true,
            'data' => $voters,
            'searchable_parameters' => $searchableParameters
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error retrieving voters list',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function getVotersHistory($id)
{
    //dd($id);
    $voterHistory = VoterHistory::where('voter_id', $id)
        ->orderBy('history_id', 'desc')
        ->get();

    if($voterHistory->isEmpty()){
        return response()->json([
            'success' => true,
            'message' => 'No history found for this voter'
        ], 200);
    }
    return response()->json([
        'success' => true,
        'data' => $voterHistory
    ]);
}

public function duplicateVoters(Request $request)
{   
    
    $constituency_ids = explode(',', auth()->user()->constituency_id);
    
    if (empty($constituency_ids)) {
        return response()->json([
            'success' => false,
            'message' => 'User does not have an assigned constituency'
        ], 400);
    }

    $query = Voter::query()
    ->select('voters.*', 'constituencies.name as constituency_name')
    ->join('constituencies', 'voters.const', '=', 'constituencies.id')
    ->whereIn('voters.const', $constituency_ids)
    //->whereExists(function ($subquery) {
    ->whereExists(function ($subquery) use ($constituency_ids) {
        $subquery->select(\DB::raw(1))
            ->from('voters as v2')
            ->whereColumn([

                ['v2.surname', 'voters.surname'],
                ['v2.first_name', 'voters.first_name'],
               ['v2.second_name', 'voters.second_name'],
                ['v2.dob', 'voters.dob']
            ])

            ->whereColumn('v2.id', '!=', 'voters.id')
            ->where(function($q) use ($constituency_ids) {
                // Check if the duplicate is in any of the user's constituencies
                $q->whereIn('v2.const', $constituency_ids);
            });
    });

    // Search fields - including all fillable columns
    $searchableFields = [
        'first_name' => 'First Name',
        'second_name' => 'Second Name',
        'surname' => 'Surname', 
        'address' => 'Address',
        'voter' => 'Voter ID',
        'const' => 'Constituency ID',
        'constituency_name' => 'Constituency Name',
        'polling' => 'Polling'
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
    $polling = $request->input('polling');
    $houseNumber = $request->input('house_number');
    $pobse = $request->input('pobse');
    $pobis = $request->input('pobis');
    $pobcn = $request->input('pobcn');  
    $existsInDatabase = $request->input('exists_in_database');

    
    $underAge25 = $request->input('under_age_25');

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

    if (!empty($constituencyId)) {
        $query->where('voters.const', $constituencyId);
    }

    // Apply exists_in_database filter
    if ($existsInDatabase === 'true') {
        $query->where('voters.exists_in_database', true);
    } elseif ($existsInDatabase === 'false') {
        $query->where('voters.exists_in_database', false);
    }

    // Get paginated results
    $voters = $query->orderBy('first_name')
               ->orderBy('second_name')
               ->orderBy('dob')
               ->paginate($request->get('per_page', 20));
    
    return response()->json([
        'success' => true,
        'data' => $voters,
        'searchable_fields' => $searchableFields,
    ]); 

}


public function getUserSurveyCount(Request $request){
    $query = DailySurveyTrack::with('user')->where('user_id', auth()->user()->id);
    
    if ($request->filled('start_date')) {
        $query->whereDate('date', '>=', $request->start_date);
    }

    if ($request->filled('end_date')) {
        $query->whereDate('date', '<=', $request->end_date);
    }

    $dailySurveyCount = $query->orderBy('date', 'desc')
                              ->paginate($request->get('per_page', 20));

    return response()->json([
        'success' => true,
        'data' => $dailySurveyCount
    ]); 
}


    public function addressSearch(Request $request)
    {
        $address = $request->input('address');
        $constituency_ids = explode(',', auth()->user()->constituency_id);
        $addresses = Voter::select('house_number', 'address', 'pobse', 'pobis', 'pobcn')
            ->where(function($query) use ($address) {
                $query->whereRaw('LOWER(CONCAT(house_number, \' \', address, \' \', pobse, \' \', pobis, \' \', pobcn)) LIKE ?', 
                    ['%' . strtolower($address) . '%']);
            })
            ->distinct()
            ->whereIn('const', $constituency_ids)
            ->get()
            ->map(function($item) {
                return [
                    'house_number' => $item->house_number,
                    'address' => $item->address,
                    'pobse' => $item->pobse,
                    'pobis' => $item->pobis, 
                    'pobcn' => $item->pobcn,
                    'full_address' => trim($item->house_number . ' ' . 
                                         $item->address . ' ' . 
                                         $item->pobse . ' ' . 
                                         $item->pobis . ' ' . 
                                         $item->pobcn)
                ];
            });
        return response()->json($addresses);
    }
    public function unregisterAddressSearch(Request $request)
    {
        $address = $request->input('address');
        $addresses = UnregisteredVoter::select('new_address')
            ->whereRaw('LOWER(new_address) LIKE ?', ['%' . strtolower($address) . '%'])
            ->distinct()
            ->get()
            ->map(function($item) {
                return [
                    'value' => $item->new_address,
                    'key' => $item->new_address
                ];
            });
        return response()->json($addresses);
    }


    public function addVoterCardResult(Request $request){

        $validator = Validator::make($request->all(), [ 
          'voter_id' => 'required|exists:voters,voter',
          'party' => 'required',
          'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);
        if ($validator->fails()) {
          return response()->json(['status' => false, 'message' => 'Validation failed', 'data' => $validator->errors()], 422);
        }
        
        // Check if voter_id already exists for this user
        $existingRecord = VoterCardImage::where('reg_no', $request->voter_id)
                                        ->where('user_id', auth()->user()->id)
                                        ->first();
        
        if ($existingRecord) {
          return response()->json([
            'success' => false,
            'message' => 'Voter card result already exists for this voter ID. Use update API to modify.',
            'data' => ['voter_id' => $request->voter_id]
          ], 409);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imagePath = $image->store('voter_cards_images', 'public');
        }
    
        $voterCardImage = VoterCardImage::create([
          'user_id' => auth()->user()->id,
          'reg_no' => $request->voter_id,
          'exit_poll' => $request->party,
          'image' => $imagePath,
          'processed' => 1,
        ]);
        
        if($voterCardImage){
            //Cache::flush();
          return response()->json([
            'success' => true,
            'message' => 'Voter card result added successfully',
            'data' => $voterCardImage
          ], 200);
        }
        
        return response()->json([
          'success' => false,
          'message' => 'Failed to add voter card result',
          'data' => null
        ], 400);
      }

    public function getVoterCardResult(Request $request, $id){
      $voterCardImage = VoterCardImage::with('user', 'voter')
                                      ->where('user_id', auth()->user()->id)
                                      ->find($id);
      
      if (!$voterCardImage) {
        return response()->json([
          'success' => false,
          'message' => 'Voter card result not found',
          'data' => null
        ], 404);
      }

      return response()->json([
        'success' => true,
        'message' => 'Voter card result retrieved successfully',
        'data' => $voterCardImage
      ], 200);
    }

    public function updateVoterCardResult(Request $request, $id){
      $validator = Validator::make($request->all(), [ 
        'voter_id' => 'sometimes|required|exists:voters,voter',
        'party' => 'sometimes|required',
        'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
      ]);
      
      if ($validator->fails()) {
        return response()->json([
          'success' => false,
          'message' => 'Validation failed',
          'data' => $validator->errors()
        ], 422);
      }

      $voterCardImage = VoterCardImage::where('user_id', auth()->user()->id)->find($id);
 
       
      if (!$voterCardImage) {
        return response()->json([
          'success' => false,
          'message' => 'Voter card result not found',
          'data' => null
        ], 404);
      }
 
      // If voter_id is being updated, check if new voter_id already exists for this user
      if ($request->has('voter_id') && $request->voter_id != $voterCardImage->reg_no) {
        $existingRecord = VoterCardImage::where('reg_no', $request->voter_id)
                                       ->where('user_id', auth()->user()->id)
                                       ->where('id', '!=', $id)
                                       ->first();
        
        if ($existingRecord) {
          return response()->json([
            'success' => false,
            'message' => 'Voter card result already exists for this voter ID',
            'data' => ['voter_id' => $request->voter_id]
          ], 409);
        }
      }

      $updateData = [];
      
      if ($request->has('voter_id')) {
        $updateData['reg_no'] = $request->voter_id;
      }
      
      if ($request->has('party')) {
        $updateData['exit_poll'] = $request->party;
      }

      if ($request->hasFile('image')) {
        // Delete old image if exists
        if ($voterCardImage->image && Storage::disk('public')->exists($voterCardImage->image)) {
          Storage::disk('public')->delete($voterCardImage->image);
        }
        
        $image = $request->file('image');
        $updateData['image'] = $image->store('voter_cards_images', 'public');
      }

      // Set processed to 1 on update
      $updateData['processed'] = 1;

      $voterCardImage->update($updateData);
      $voterCardImage->refresh();
      Cache::flush();
      return response()->json([
        'success' => true,
        'message' => 'Voter card result updated successfully',
        'data' => $voterCardImage
      ], 200);
    }

    public function deleteVoterCardResult(Request $request, $id){
      $voterCardImage = VoterCardImage::where('user_id', auth()->user()->id)->find($id);
      
      if (!$voterCardImage) {
        
        return response()->json([
          'success' => false,
          'message' => 'Voter card result not found',
          'data' => null
        ], 404);
      }

      // Delete image file if exists
      if ($voterCardImage->image && Storage::disk('public')->exists($voterCardImage->image)) {
        Storage::disk('public')->delete($voterCardImage->image);
      }

      $voterCardImage->delete();
      Cache::flush();
      return response()->json([
        'success' => true,
        'message' => 'Voter card result deleted successfully',
        'data' => null
      ], 200);
    }
    
    
    public function listVoterCardResult(Request $request){
      $query = VoterCardImage::with('user', 'voter')
                             ->where('user_id', auth()->user()->id)
                             ->orderBy('id', 'desc');
  
      // Filter by voter_id if provided
      if ($request->has('voter') && !empty($request->get('voter'))) {
        $query->where('reg_no', 'like', '%' . $request->get('voter') . '%');
      }
      // Filter by party (exit_poll) if provided
      if ($request->has('party') && !empty($request->get('party'))) {
        $query->whereRaw('LOWER(exit_poll) = ?', [strtolower($request->get('party'))]);
      }
  
      $voterCardImages = $query->paginate($request->get('per_page', 20));
  
      return response()->json([
        'success' => true,
        'message' => 'Voter card result list retrieved successfully',
        'data' => $voterCardImages
      ], 200);
    }


} 