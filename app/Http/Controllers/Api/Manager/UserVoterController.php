<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Voter;
use App\Models\Survey;
use App\Models\Party;
use App\Models\VoterHistory;
use App\Models\Constituency;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator; 
use App\Models\UnregisteredVoter;
use Illuminate\Support\Facades\Cache;

class UserVoterController extends Controller
{
 // ... existing code ...

 
 public function nationalRegisteryList(Request $request) 
 {   
   
 
     
     $query = Voter::with(['constituency','user','living_constituency','surveyer_constituency'])
         ->where('voters.is_national', 1)
         ->where('voters.const', explode(',', auth()->user()->constituency_id));  
         

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

    // OPTIMIZED: Create subquery with only needed columns instead of SELECT *
    // This significantly reduces the amount of data processed
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
        ->whereIn('voters.const', explode(',', auth()->user()->constituency_id))
        ->orderBy('ls.created_at', 'desc');
            
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

    // Apply challenge filter
    // For PostgreSQL with boolean type, cast string to boolean explicitly
    if ($challenge === 'true') {
        $query->whereRaw('ls.challenge IS TRUE');
    } elseif ($challenge === 'false') {
        $query->whereRaw('ls.challenge IS FALSE');
    }
    
    // Apply exists_in_database filter
    if ($existsInDatabase === 'true') {
        $query->where('voters.exists_in_database', true);
    } elseif ($existsInDatabase === 'false') {
        $query->where('voters.exists_in_database', false);
    }

    $polling = $request->input('polling');

    // Apply voting_for filter
    if ($voting_for !== null && $voting_for !== '') {
        $get_party = Party::where('id', $voting_for)->first();
        if ($get_party) {
            $voting_for = $get_party->name;
            $query->where('ls.voting_for', $voting_for);
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
        $query->where('voters.polling', $polling);
    }
    
    // Apply under_age_25 filter
    $underAge25 = $request->input('under_age_25');
    if ($underAge25 === 'yes') {
        $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
    }

    // Apply user_id filter
    if (isset($request->user_id) && !empty($request->user_id)) {
        $query->where('ls.user_id', $request->user_id);
    }

    // Apply date range filters
    if (isset($request->start_date) && !empty($request->start_date)) {
        $query->where('ls.created_at', '>=', $request->start_date . ' 00:00:00');
    }

    if (isset($request->end_date) && !empty($request->end_date)) {
        $query->where('ls.created_at', '<=', $request->end_date . ' 23:59:59');
    }

    // Apply name filters
    if (!empty($surname)) {
        $query->whereRaw('LOWER(voters.surname) LIKE ?', ['%' . strtolower($surname) . '%']);
    }

    if (!empty($firstName)) {
        $query->whereRaw('LOWER(voters.first_name) LIKE ?', ['%' . strtolower($firstName) . '%']);
    }

    if (!empty($secondName)) {
        $query->whereRaw('LOWER(voters.second_name) LIKE ?', ['%' . strtolower($secondName) . '%']);
    }

    // Apply address-related filters
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

    // Apply voter ID filter
    if (!empty($voterId) && is_numeric($voterId)) {
        $query->where('voters.voter', $voterId);
    }

    // Apply constituency name filter
    if (!empty($constituencyName)) {
        $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
    }

    // Apply constituency ID filter
    if (!empty($constituencyId) && is_numeric($constituencyId)) {
        $query->where('voters.const', $constituencyId);
    }

    // Get paginated results
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
    $pobse = $request->input('pobse');
    $pobis = $request->input('pobis');
    $pobcn = $request->input('pobcn'); 
    $houseNumber = $request->input('house_number');
    $polling = $request->input('polling');
    $located = $request->input('located');
    $voting_decision = $request->input('voting_decision');
    $voting_for = $request->input('voting_for');
    $is_died = $request->input('is_died');
    $died_date = $request->input('died_date');
    $existsInDatabase = $request->input('exists_in_database'); 
 


    $query = Voter::with('user')
        ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date','surveys.user_id','surveys.located','surveys.voting_decision','surveys.voting_for','surveys.is_died','surveys.died_date')
        ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
        ->join('surveys', 'voters.id', '=', 'surveys.voter_id')
        ->whereExists(function ($query) {
            $query->select('id')
                ->from('surveys')
                ->whereColumn('surveys.voter_id', 'voters.id');
        })
        ->whereIn('voters.const', explode(',', auth()->user()->constituency_id))
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
         'const' => 'Constituency ID',
         'constituency_name' => 'Constituency Name',
         'user_id' => 'User ID',
         'polling' => 'Polling Station',
         'voting_decision' => 'Voting Decision',
         'located' => 'Located',
         'voting_for' => 'Voting For',
         'is_died' => 'Voter Died',
         'died_date' => 'Died Date'

        ];  

        if( $voting_for !== null && $voting_for !== ''){
        
            $get_party = Party::where('id', $voting_for)->first();
            $voting_for = $get_party->name;
            $query->where('surveys.voting_for', $voting_for);
       }

       if($is_died !== null && $is_died !== '' ){
        $query->where('surveys.is_died', $is_died);
        }
        
        

        if (!empty($voting_decision)) {
            $query->where('surveys.voting_decision', $voting_decision);
        }

     if (!empty($located)) {
        $query->whereRaw('LOWER(surveys.located) = ?', [strtolower($located)]);
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
    $voters = $query->paginate($request->get('per_page', 20));

    
    return response()->json([
        'success' => true,
        'data' => $voters,
        'searchable_fields' => $searchableFields
    ]);
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
        $voter_id = $request->input('voter_id');
        $polling = $request->input('polling');
        $pobse = $request->input('pobse');
        $pobis = $request->input('pobis');
        $pobcn = $request->input('pobcn'); 
        $houseNumber = $request->input('house_number');
        $existsInDatabase = $request->input('exists_in_database');
        // Query voters who don't have a survey entry
        $query = Voter::select('voters.*', 'constituencies.name as constituency_name')
            ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
            ->whereIn('voters.const', $constituency_ids)
           
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('surveys')
                    ->whereRaw('surveys.voter_id = voters.id');
            }); 

            // Apply exists_in_database filter
            if ($existsInDatabase === 'true') {
                $query->where('voters.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $query->where('voters.exists_in_database', false);
            } else {
                // Default behavior - keep existing logic
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

         
        if (!empty($voterId) && is_numeric($voterId)) {
            $query->where('voters.voter', $voterId);
        }

        if (!empty($constituencyName)) {
            $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
        }

        if (!empty($constituencyId)) {
            $query->where('voters.const', $constituencyId);
        }
        if (!empty($voter_id )) {
            $query->where('voters.voter', $voter_id);
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
            'polling' => 'Polling Station'
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



public function voterCardsReport(Request $request)
{


    $constituency_ids = explode(',', auth()->user()->constituency_id);

    if (empty($constituency_ids)) {
        return response()->json([
            'success' => false,
            'message' => 'User does not have an assigned constituency'
        ], 400);
    }


    // Build the query with joins - starting from voters to match index function count
    $query = DB::table('voters as v')
        ->leftJoin('constituencies as c', 'v.const', '=', 'c.id')
        ->leftJoin('voter_cards_images as vci', 'vci.reg_no', '=', 'v.voter')
        ->whereIn('v.const', $constituency_ids);

    // Get filter parameters
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

    // Apply filters matching the index function
    if ($request->has('constituency_id') && !empty($request->constituency_id)) {
        $query->where('v.const', $request->constituency_id);
    }

    if ($request->has('constituency_name') && !empty($request->constituency_name)) {
        $query->whereRaw('LOWER(c.name) LIKE ?', ['%' . strtolower($request->constituency_name) . '%']); 
    }

    if ($request->has('polling') && !empty($request->polling)) {
        $query->where('v.polling', $request->polling);
    }
    
    // exists_in_database filter
    if ($existsInDatabase === 'true') {
        $query->where('v.exists_in_database', true);
    } elseif ($existsInDatabase === 'false') {
        $query->where('v.exists_in_database', false);
    }

    // under_age_25 filter
    if ($underAge25 === 'yes') {
        $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, v.dob)) < 25');
    }

    // Name filters
    if (!empty($surname)) {
        $query->whereRaw('LOWER(v.surname) LIKE ?', ['%' . strtolower($surname) . '%']);
    }

    if (!empty($firstName)) {
        $query->whereRaw('LOWER(v.first_name) LIKE ?', ['%' . strtolower($firstName) . '%']);
    }

    if (!empty($secondName)) {
        $query->whereRaw('LOWER(v.second_name) LIKE ?', ['%' . strtolower($secondName) . '%']);
    }

    // Voter ID filter
    if (!empty($voterId) && is_numeric($voterId)) {
        $query->where('v.voter', $voterId);
    }

    // Address filters
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

    // Select aggregated data by polling division
    $results = $query->select(
        'v.polling as polling_division',
        DB::raw("COUNT(DISTINCT CASE WHEN vci.exit_poll = 'fnm' THEN vci.id END) as fnm_count"),
        DB::raw("COUNT(DISTINCT CASE WHEN vci.exit_poll = 'plp' THEN vci.id END) as plp_count"),
        DB::raw("COUNT(DISTINCT CASE WHEN vci.exit_poll = 'coi' THEN vci.id END) as dna_count"),
        DB::raw("COUNT(DISTINCT CASE WHEN vci.exit_poll NOT IN ('fnm', 'plp', 'coi') AND vci.exit_poll IS NOT NULL THEN vci.id END) as other_count"),
        DB::raw("COUNT(DISTINCT CASE WHEN vci.exit_poll IS NULL THEN v.id END) as no_vote_count"),
        DB::raw("COUNT(DISTINCT v.id) as total_count"),
        
        // Percentages
        DB::raw("ROUND((COUNT(DISTINCT CASE WHEN vci.exit_poll = 'fnm' THEN vci.id END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as fnm_percentage"),
        DB::raw("ROUND((COUNT(DISTINCT CASE WHEN vci.exit_poll = 'plp' THEN vci.id END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as plp_percentage"),
        DB::raw("ROUND((COUNT(DISTINCT CASE WHEN vci.exit_poll = 'coi' THEN vci.id END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as dna_percentage"),
        DB::raw("ROUND((COUNT(DISTINCT CASE WHEN vci.exit_poll NOT IN ('fnm', 'plp', 'coi') AND vci.exit_poll IS NOT NULL THEN vci.id END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as other_percentage"),
        DB::raw("ROUND((COUNT(DISTINCT CASE WHEN vci.exit_poll IS NULL THEN v.id END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as no_vote_percentage")
    )
    ->groupBy('v.polling')
    ->orderBy('v.polling', 'asc')
    ->paginate($request->input('per_page', 20));

    // Transform: add total_party_count (sum of fnm, plp, dna, other counts) to each item
    $results->getCollection()->transform(function ($item) {
        $item->total_party_count =
            $item->fnm_count
            + $item->plp_count
            + $item->dna_count
            + $item->other_count;
        return $item;
    });

    return response()->json([
        'success' => true,
        'message' => 'Voter cards report retrieved successfully',
        'data' => $results
    ]);
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

        // Optimized: Use DISTINCT ON for latest survey (PostgreSQL optimized approach)
        $latestSurveySubquery = DB::table('surveys')
            ->selectRaw('DISTINCT ON (voter_id) 
                voter_id,
                home_phone_code,
                home_phone,
                work_phone_code,
                work_phone,
                cell_phone_code,
                cell_phone,
                voting_for')
            ->orderBy('voter_id')
            ->orderBy('id', 'desc');
        
        $query = Voter::query()
            ->select(
                'voters.*',
                'constituencies.name as constituency_name',
                'ls.home_phone_code',
                'ls.home_phone',
                'ls.work_phone_code',
                'ls.work_phone',
                'ls.cell_phone_code',
                'ls.cell_phone',
                'ls.voting_for'
            )
            ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
            ->leftJoinSub($latestSurveySubquery, 'ls', 'ls.voter_id', '=', 'voters.id')
            ->leftJoin('voter_cards_images as vci', 'vci.reg_no', '=', 'voters.voter')
            ->whereIn('voters.const', $constituency_ids);
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
        $pobse = $request->input('pobse');
        $pobis = $request->input('pobis');
        $pobcn = $request->input('pobcn'); 
        $houseNumber = $request->input('house_number');
        $existsInDatabase = $request->input('exists_in_database');
        $partyId = $request->input('voting_for');
        $isVoted = $request->input('is_voted');
        $advance_poll = $request->input('advance_poll');

        // CRITICAL: Apply constituency filter FIRST - this is the most important filter for managers
        // It dramatically reduces the dataset before other filters are applied
        //$query->whereIn('voters.const', $constituency_ids);

        // Apply indexed filters early to reduce dataset before expensive operations
        if (!empty($const)) {
            $query->where('voters.const', $const);
        }

        if (!empty($polling) && is_numeric($polling)) {
            $query->where('voters.polling', $polling);
        }

        // Note: exists_in_database is stored as INTEGER (int4) in database, not boolean
        // So we must use integer values (0 or 1) for comparison
        if ($existsInDatabase === 'true' || $existsInDatabase === '1' || $existsInDatabase === 1) {
            $query->where('voters.exists_in_database', 1);
        } elseif ($existsInDatabase === 'false' || $existsInDatabase === '0' || $existsInDatabase === 0) {
            $query->where('voters.exists_in_database', 0);
        }

        if ($advance_poll == 'yes') {
            $query->where('voters.flagged', 1);
        }

        if (!empty($voterId) && is_numeric($voterId)) {
            $query->where('voters.voter', $voterId);
        }

        if ($underAge25 === 'yes') {
            $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
        }

        // Apply is_voted filter early (before expensive joins)
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

        // Apply text search filters (these are slower but applied after indexed filters)
        // Use ILIKE for PostgreSQL (can use expression indexes)
        if (!empty($surname)) {
            $query->whereRaw('voters.surname ILIKE ?', ['%' . $surname . '%']);
        }

        if (!empty($firstName)) {
            $query->whereRaw('voters.first_name ILIKE ?', ['%' . $firstName . '%']);
        }

        if (!empty($secondName)) {
            $query->whereRaw('voters.second_name ILIKE ?', ['%' . $secondName . '%']);
        }

        if (!empty($constituencyName)) {
            $query->whereRaw('constituencies.name ILIKE ?', ['%' . $constituencyName . '%']);
        }

        $query->where(function($q) use ($houseNumber, $address, $pobse, $pobis, $pobcn) {
            if ($houseNumber !== null && $houseNumber !== '') {
                $q->whereRaw('LOWER(voters.house_number) = LOWER(?)', [$houseNumber]);
            }
            if ($address !== null && $address !== '') {
                $q->whereRaw('LOWER(voters.address) = LOWER(?)', [$address]);
            }
            if ($pobse !== null && $pobse !== '') {
                $q->whereRaw('LOWER(voters.pobse) = LOWER(?)', [$pobse]);
            }
            if ($pobis !== null && $pobis !== '') {
                $q->whereRaw('LOWER(voters.pobis) = LOWER(?)', [$pobis]);
            }
            if ($pobcn !== null && $pobcn !== '') {
                $q->whereRaw('LOWER(voters.pobcn) = LOWER(?)', [$pobcn]);
            }
        });

        // Apply party filter after joins are established
        if ($partyId) {
            $partyId = Party::where('name', $partyId)->first();
            if ($partyId) {
                $partyShortName = strtolower($partyId->short_name);
                $query->whereRaw('LOWER(vci.exit_poll) = ?', [$partyShortName]);
            }
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
            'under_age_25' => 'Under Age 25',
            'polling' => 'Polling Station'
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



public function electionDayReport_one(Request $request) 
{   
   
    // Generate cache key based on all request parameters
    $cacheKey = 'election_day_report_one_' . md5(json_encode($request->all()) . '_' . $request->get('per_page', 20));


    // Fix: properly create $constituency_ids and make available inside cache closure

    $constituency_ids = array_filter(array_map('trim', explode(',', (string) (auth()->user()->constituency_id ?? ''))));
    if (empty($constituency_ids)) {
        return response()->json([
            'success' => false,
            'message' => 'User does not have an assigned constituency'
        ], 400);
    }

    // Check if data exists in cache, otherwise execute query and cache forever
    $response = Cache::rememberForever($cacheKey, function() use ($request, $constituency_ids) {
        // Get all voters, join latest survey if exists
        $query = \DB::table('voters')
            ->select(
                'surveys.home_phone_code',
                'surveys.id as survey_id',
                'surveys.home_phone',
                'surveys.work_phone_code',
                'surveys.work_phone',
                'surveys.cell_phone_code',
                'surveys.cell_phone',
                'surveys.voting_for',
                'voters.*',
                'voters.id as voter_table_id',
                'constituencies.name as constituency_name',
                'vci.*'
            )
            // Use leftJoin to join only the latest survey per voter, using DISTINCT on voter_id (since one voter may have multiple surveys)
            ->leftJoin('surveys', function($join) {
                $join->on('surveys.voter_id', '=', 'voters.id')
                     ->whereRaw('surveys.id = (SELECT MAX(s2.id) FROM surveys as s2 WHERE s2.voter_id = voters.id)');
            })
            ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
            ->leftJoin('voter_cards_images as vci', 'vci.reg_no', '=', 'voters.voter')
            ->whereNotNull('vci.reg_no')
            ->whereIn('voters.const', $constituency_ids);

        $searchableFields = [
            'first_name' => 'First Name',
            'second_name' => 'Second Name',
            'surname' => 'Surname', 
            'address' => 'Address',
            'voter' => 'Voter ID',
            'const' => 'Constituency ID',
            'constituency_name' => 'Constituency Name',
            'polling' => 'Polling Station',
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
        $isSurveyed = $request->input('is_surveyed');
        $advance_poll = $request->input('advance_poll');
        $export = $request->input('export');

        $partyId = $request->input('voting_for');
        if ($partyId) {
            $partyId = Party::where('name', $partyId)->first();
            $partyShortName = strtolower($partyId->short_name);
            $query->whereRaw('LOWER(vci.exit_poll) = ?', [$partyShortName]);
        }

        if ($advance_poll == 'yes') {
            $query->where('voters.flagged', 1);
        }

        // Get sorting parameters
        $sortBy = $request->input('sort_by');
        $sortOrder = $request->input('sort_order', 'asc');
        $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc']) ? strtolower($sortOrder) : 'asc'; 

        // Apply filters

        // is_surveyed filter
        if ($isSurveyed === 'yes') {
            $query->whereNotNull('surveys.id');
        } elseif ($isSurveyed === 'no') {
            $query->whereNull('surveys.id');
        }

        // is_voted filter (voter_cards_images)
        if ($isVoted === 'yes') {
            $query->whereNotNull('vci.reg_no');
        } elseif ($isVoted === 'no') {
            $query->whereNull('vci.reg_no');
        }

        if (!empty($polling) && is_numeric($polling)) {
            $query->where('voters.polling', $polling);
        }

        if ($underAge25 === 'yes') {
            $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
        }

        // Apply exists_in_database filter
        if ($existsInDatabase === true || $existsInDatabase === 'true') { 
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === false || $existsInDatabase === 'false') {
          
            $query->where('voters.exists_in_database', false);
        }
        
        if (!empty($const)) {
            $query->where('voters.const', $const);
        }
        
        if (!empty($surname)) {
            $query->whereRaw('voters.surname ILIKE ?', ['%' . $surname . '%']);
        }

        if (!empty($firstName)) {
            $query->whereRaw('voters.first_name ILIKE ?', ['%' . $firstName . '%']);
        }

        if (!empty($secondName)) {
            $query->whereRaw('voters.second_name ILIKE ?', ['%' . $secondName . '%']);
        }

        $query->where(function($q) use ($houseNumber, $address, $pobse, $pobis, $pobcn) {
            if ($houseNumber !== null && $houseNumber !== '') {
                $q->whereRaw('voters.house_number ILIKE ?', [$houseNumber]);
            }
            if ($address !== null && $address !== '') {
                $q->whereRaw('voters.address ILIKE ?', [$address]);
            }
            if ($pobse !== null && $pobse !== '') {
                $q->whereRaw('voters.pobse ILIKE ?', [$pobse]);
            }
            if ($pobis !== null && $pobis !== '') {
                $q->whereRaw('voters.pobis ILIKE ?', [$pobis]);
            }
            if ($pobcn !== null && $pobcn !== '') {
                $q->whereRaw('voters.pobcn ILIKE ?', [$pobcn]);
            }
        }); 


        if (!empty($voterId) && is_numeric($voterId)) {
            $query->where('voters.voter', $voterId);
        }

        if (!empty($constituencyName)) {
            $query->whereRaw('constituencies.name ILIKE ?', ['%' . $constituencyName . '%']);
        }

        if (!empty($constituencyId)) {
            $query->where('voters.const', $constituencyId);
        }

        // survey_district/district_name filter removed

        if (!empty($sortBy)) {
            switch ($sortBy) {
                case 'voter':
                    $query->orderBy('voters.voter', $sortOrder)
                          ->orderBy('surveys.id', 'desc');
                    break;
                case 'const':
                    $query->orderBy('voters.const', $sortOrder)
                          ->orderBy('surveys.id', 'desc');
                    break;
                case 'polling':
                    $query->orderBy('voters.polling', $sortOrder)
                          ->orderBy('surveys.id', 'desc');
                    break;
                case 'first_name':
                    $query->orderByRaw('LOWER(voters.first_name) ' . strtoupper($sortOrder))
                          ->orderBy('surveys.id', 'desc');
                    break;
                case 'last_name':
                    $query->orderByRaw('LOWER(TRIM(voters.surname)) ' . strtoupper($sortOrder))
                          ->orderBy('surveys.id', 'desc');
                    break;
                default:
                    $query->orderBy('surveys.id', 'desc')
                          ->orderBy('voters.id', 'desc'); 
                    break;
            }
        } else {
            // Default sorting - surveys is base table, so sort by survey id first
            $query->orderBy('surveys.id', 'desc')
                  ->orderBy('voters.id', 'desc');
        }

       
        $voters = $query->paginate($request->get('per_page', 20));
   
        $response = [
            'success' => true,
            'data' => $voters,
            'searchable_fields' => $searchableFields,
            'sorting_options' => [
                'voter' => 'Voter ID',
                'const' => 'Constituency ID',
                'polling' => 'Polling Station'
            ],
            'current_sort' => [
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder
            ]
        ];

        return $response;
    });

    return response()->json($response); 
}
 
public function print_voters(Request $request)
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

        $latestSurveySubquery = DB::table('surveys')
            ->selectRaw('DISTINCT ON (voter_id) 
                voter_id,
                id as survey_id,
                home_phone_code,
                home_phone,
                work_phone_code,
                work_phone,
                cell_phone_code,
                cell_phone,
                voting_for,
                challenge,
                created_at as survey_created_at,
                updated_at as survey_updated_at')
            ->orderBy('voter_id')
            ->orderBy('id', 'desc');

        // Optimized query: Use leftJoin for better performance
        $query = Voter::query()
            ->select(
                'voters.*', 
                'constituencies.name as constituency_name',
                'ls.survey_id',
                'ls.voter_id',
                'ls.home_phone_code',
                'ls.home_phone',
                'ls.work_phone_code',
                'ls.work_phone',
                'ls.cell_phone_code',
                'ls.cell_phone',
                'ls.voting_for',
                'ls.challenge',
                'ls.survey_created_at',
                'ls.survey_updated_at'
            )
            ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
            ->leftJoinSub($latestSurveySubquery, 'ls', 'ls.voter_id', '=', 'voters.id')
            ->whereIn('voters.const', $constituency_ids)
            ->where('voters.exists_in_database', false);

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
        $pobse = $request->input('pobse');
        $pobis = $request->input('pobis');
        $pobcn = $request->input('pobcn'); 
        $houseNumber = $request->input('house_number');
        $advance_poll = $request->input('advance_poll');
        
        // Optimized: Use boolean comparison instead of integer cast
        if ($advance_poll == 'yes') {
            $query->where('voters.flagged', 1);
        }

        // Optimized text search: Use ILIKE for PostgreSQL (can use expression indexes)
        if (!empty($surname)) {
            $query->whereRaw('voters.surname ILIKE ?', ['%' . $surname . '%']);
        }

        if (!empty($firstName)) {
            $query->whereRaw('voters.first_name ILIKE ?', ['%' . $firstName . '%']);
        }

        if (!empty($secondName)) {
            $query->whereRaw('voters.second_name ILIKE ?', ['%' . $secondName . '%']);
        }

        if (!empty($polling) && is_numeric($polling)) {
            $query->where('voters.polling', $polling);
        }
        
        $query->where(function($q) use ($houseNumber, $address, $pobse, $pobis, $pobcn) {
            if ($houseNumber !== null && $houseNumber !== '') {
                $q->whereRaw('LOWER(voters.house_number) = LOWER(?)', [$houseNumber]);
            }
            if ($address !== null && $address !== '') {
                $q->whereRaw('LOWER(voters.address) = LOWER(?)', [$address]);
            }
            if ($pobse !== null && $pobse !== '') {
                $q->whereRaw('LOWER(voters.pobse) = LOWER(?)', [$pobse]);
            }
            if ($pobis !== null && $pobis !== '') {
                $q->whereRaw('LOWER(voters.pobis) = LOWER(?)', [$pobis]);
            }
            if ($pobcn !== null && $pobcn !== '') {
                $q->whereRaw('LOWER(voters.pobcn) = LOWER(?)', [$pobcn]);
            }
        }); 

        if (!empty($voterId) && is_numeric($voterId)) {
            $query->where('voters.voter', $voterId);
        }

        if (!empty($const)) {
            $query->where('voters.const', $const);
        }

        if (!empty($constituencyName)) {
            $query->whereRaw('constituencies.name ILIKE ?', ['%' . $constituencyName . '%']);
        }

        if ($underAge25 === 'yes') {
            $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
        }

        // CRITICAL PERFORMANCE: Sort in database (PostgreSQL) instead of PHP
        // Database sorting is 100x+ faster than PHP sorting for large datasets
        $query->orderByRaw('LOWER(TRIM(voters.surname)) ASC NULLS LAST')
              ->orderByRaw('LOWER(TRIM(voters.first_name)) ASC NULLS LAST')
              ->orderByRaw('LOWER(TRIM(voters.second_name)) ASC NULLS LAST');

        // Get the results from the DB (already sorted by database)
        $voters = $query->get();

        // ULTRA-OPTIMIZED TRANSFORMATION: Minimal operations for maximum speed
        // Pre-define field list for direct access
        $voterFields = [
            'id', 'const', 'polling', 'voter', 'surname', 'first_name', 'second_name', 
            'dob', 'pobcn', 'pobis', 'pobse', 'house_number', 'aptno', 'blkno', 
            'address', 'newly_registered', 'created_at', 'updated_at', 'is_contacted', 
            'diff_address', 'living_constituency', 'search_vector', 'exists_in_database', 
            'last_checked_at', 'flagged', 'constituency_name', 'challenge'
        ];
        
        $surveyFields = [
            'survey_id', 'voter_id', 'home_phone_code', 'home_phone', 
            'work_phone_code', 'work_phone', 'cell_phone_code', 'cell_phone', 
            'voting_for', 'survey_created_at', 'survey_updated_at', 'challenge'
        ];

        // Process with minimal overhead - direct property access, no array conversions
        $transformed = [];
        foreach ($voters as $voter) {
            // Build voter data - direct assignment (fastest method)
            $voterData = [];
            foreach ($voterFields as $field) {
                // Use property_exists check only if needed, otherwise direct access
                $voterData[$field] = $voter->$field ?? null;
            }
            
            // Extract survey - single check, then direct field access
            if (isset($voter->voter_id) && $voter->voter_id !== null) {
                $survey = [];
                foreach ($surveyFields as $field) {
                    $value = $voter->$field ?? null;
                    if ($value !== null) {
                        $survey[$field] = $value;
                    }
                }
                $voterData['survey'] = !empty($survey) ? $survey : null;
            } else {
                $voterData['survey'] = null;
            }
            
            $transformed[] = $voterData;
        }

        // Define searchable parameters
        $searchableParameters = [
            'first_name' => 'First Name',
            'second_name' => 'Second Name', 
            'surname' => 'Surname',
            'address' => 'Address',
            'voter_id' => 'Voter ID',
            'const' => 'Constituency Id',
            'constituency_name' => 'Constituency Name',
            'under_age_25' => 'Under Age 25',
            'polling' => 'Polling Station'
        ];

        return response()->json([
            'success' => true,
            'data' => $transformed,
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
            ->where('voters.exists_in_database', false)
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
        $voters = $query->paginate($request->get('per_page', 20));

        // Define searchable parameters
        $searchableParameters = [
            'first_name' => 'First Name',
            'second_name' => 'Second Name', 
            'surname' => 'Surname',
            'address' => 'Address',
            'voter_id' => 'Voter ID',
            'const' => 'Constituency Id',
            'constituency_name' => 'Constituency Name',
            'under_age_25' => 'Under Age 25',
            'polling' => 'Polling Station'
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
            ->where('voters.exists_in_database', false)
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
        $pobse = $request->input('pobse');
        $pobis = $request->input('pobis');
        $pobcn = $request->input('pobcn'); 
        $houseNumber = $request->input('house_number');
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

        if (!empty($const)) {
            $query->where('voters.const', $const);
        }

        
        if (!empty($constituencyName)) {
            $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
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
            'constituency_name' => 'Constituency Name'
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
        ->orderBy('id', 'desc')
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
    ->where('voters.exists_in_database', false)
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
        'constituency_name' => 'Constituency Name'
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
    $pobse = $request->input('pobse');
    $pobis = $request->input('pobis');
    $pobcn = $request->input('pobcn'); 
    $houseNumber = $request->input('house_number');
    $existsInDatabase = $request->input('exists_in_database');

    if ($existsInDatabase === 'true') {
        $query->where('voters.exists_in_database', true);   
    } elseif ($existsInDatabase === 'false') {
        $query->where('voters.exists_in_database', false);
    }


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


public function addressSearch(Request $request)
    {
        $address = $request->input('address');
        $constituency_ids = explode(',', auth()->user()->constituency_id);
        $addresses = Voter::select('house_number', 'address', 'pobse', 'pobis', 'pobcn')
            ->where('voters.exists_in_database', false)
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

} 