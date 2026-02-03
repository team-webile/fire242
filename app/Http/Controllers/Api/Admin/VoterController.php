<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voter;
use App\Models\Party;
use App\Models\VoterHistory;
use Illuminate\Http\Request;
use App\Models\UnregisteredVoter;
use App\Models\Survey;
 
use DB;
use Illuminate\Support\Facades\Cache;
class VoterController extends Controller  
{
      
   
    public function nationalRegisteryList(Request $request) 
    {   
        // Check if user is authenticated and has admin role
        if (!auth()->check() || auth()->user()->role->name !== 'Admin') { 
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }
    
       
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

    public function index(Request $request)  
    {   
        // Check if user is authenticated and has admin role
        if (!auth()->check() || auth()->user()->role->name !== 'Admin') { 
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }
    
        // Optimized query: Use PostgreSQL DISTINCT ON for latest survey join
        // DISTINCT ON is highly optimized in PostgreSQL and works perfectly with indexes
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
            ->leftJoin('voter_cards_images as vci', 'vci.reg_no', '=', 'voters.voter');

            
 
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
        $haveCorrectPhone = $request->input('have_correct_phone');
        // if ($partyId) {
        //     $partyId = Party::where('name', $partyId)->first();
        //     $partyShortName = strtolower($partyId->short_name);
        //     $query->whereRaw('LOWER(vci.exit_poll) = ?', [$partyShortName]);
        // }

        if ($partyId) {
            if (is_numeric($partyId)) {
                $party = Party::where('id', $partyId)->first();
            } else {
                $party = Party::whereRaw('LOWER(name) = ?', [strtolower($partyId)])->first();
            }

            if ($party && isset($party->short_name)) {
                $partyShortName = strtolower($party->short_name);
                $query->whereRaw('LOWER(ls.voting_for) = ?', [$partyShortName]);
            }
        }



            if ($haveCorrectPhone == 'yes') {
                $query->where('ls.cell_phone', '!=', '9999999'); 
                $query->where('ls.work_phone', '!=',  null); 
                
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

        if (!empty($constituencyName)) {
            $query->whereRaw('constituencies.name ILIKE ?', ['%' . $constituencyName . '%']);
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
                    // Alphabetical sorting: First Name (main), Second Name (tie-breaker), Surname (tie-breaker)
                    $query->orderByRaw('LOWER(voters.first_name) ' . strtoupper($sortOrder));
                    $query->orderByRaw('LOWER(voters.second_name) ' . strtoupper($sortOrder));
                    $query->orderByRaw('LOWER(voters.surname) ' . strtoupper($sortOrder));
                    break;
                case 'second_name':
                    // Alphabetical sorting: Second Name (main), First Name (tie-breaker), Surname (tie-breaker)
                    $query->orderByRaw('LOWER(voters.second_name) ' . strtoupper($sortOrder));
                    $query->orderByRaw('LOWER(voters.first_name) ' . strtoupper($sortOrder));
                    $query->orderByRaw('LOWER(voters.surname) ' . strtoupper($sortOrder));
                    break;
                case 'surname':
                case 'last_name':
                    // Alphabetical sorting: Surname (main), First Name (tie-breaker), Second Name (tie-breaker)
                    $query->orderByRaw('LOWER(voters.surname) ' . strtoupper($sortOrder));
                    $query->orderByRaw('LOWER(voters.first_name) ' . strtoupper($sortOrder));
                    $query->orderByRaw('LOWER(voters.second_name) ' . strtoupper($sortOrder));
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
                'polling' => 'Polling Station',
                'first_name' => 'First Name',
                'second_name' => 'Second Name', 
                'surname' => 'Surname',
                'last_name' => 'Surname' // Alias for surname
            ],
            'current_sort' => [
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder
            ]
        ]); 
    }

    // public function index(Request $request) 
    // {   
    //     // Check if user is authenticated and has admin role
    //     if (!auth()->check() || auth()->user()->role->name !== 'Admin') { 
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Unauthorized - Admin access required'
    //         ], 403);
    //     }
    
       
    //     $query = Voter::query() 
    //         ->select(
    //             'voters.*',
    //             'constituencies.name as constituency_name',
    //             'ls.home_phone_code',
    //             'ls.home_phone',
    //             'ls.work_phone_code',
    //             'ls.work_phone',
    //             'ls.cell_phone_code',
    //             'ls.cell_phone',
    //             'ls.voting_for'
    //         )
    //         ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
    //         ->leftJoinSub(
    //             DB::table('surveys as s1')
    //                 ->select(
    //                     's1.voter_id',
    //                     's1.home_phone_code',
    //                     's1.home_phone',
    //                     's1.work_phone_code',
    //                     's1.work_phone',
    //                     's1.cell_phone_code',
    //                     's1.cell_phone',
    //                     's1.voting_for'
    //                 )
    //                 ->join(
    //                     DB::raw('(SELECT voter_id, MAX(id) AS max_id FROM surveys GROUP BY voter_id) as s2'),
    //                     function ($join) {
    //                         $join->on('s1.voter_id', '=', 's2.voter_id')
    //                             ->on('s1.id', '=', 's2.max_id');
    //                     }
    //                 ), 
    //             'ls', 
    //             'ls.voter_id', 
    //             '=', 
    //             'voters.id'
    //         )
    //         ->leftJoin('voter_cards_images as vci', 'vci.reg_no', '=', 'voters.voter');

            
 
    //     $searchableFields = [
    //         'first_name' => 'First Name',
    //         'second_name' => 'Second Name',
    //         'surname' => 'Surname', 
    //         'address' => 'Address',
    //         'voter' => 'Voter ID',
    //         'const' => 'Constituency ID',
    //         'constituency_name' => 'Constituency Name',
    //         'polling' => 'Polling Station'
    //     ];  

    //     // Get search parameters
    //     $const = $request->input('const');
    //     $surname = $request->input('surname');
    //     $firstName = $request->input('first_name');
    //     $secondName = $request->input('second_name');
    //     $address = $request->input('address');
    //     $voterId = $request->input('voter');
    //     $constituencyName = $request->input('constituency_name');
    //     $constituencyId = $request->input('const');
    //     $underAge25 = $request->input('under_age_25');
    //     $polling = $request->input('polling');
    //     $houseNumber = $request->input('house_number');
    //     $pobse = $request->input('pobse');
    //     $pobis = $request->input('pobis');
    //     $pobcn = $request->input('pobcn');
    //     $existsInDatabase = $request->input('exists_in_database'); 
    //     $isVoted = $request->input('is_voted');
    //     $advance_poll = $request->input('advance_poll');
    //     $export = $request->input('export');

    //     $partyId = $request->input('voting_for');
    //     // if ($partyId) {
    //     //     $partyId = Party::where('name', $partyId)->first();
    //     //     $partyShortName = strtolower($partyId->short_name);
    //     //     $query->whereRaw('LOWER(vci.exit_poll) = ?', [$partyShortName]);
    //     // }

    //     if ($partyId) {
    //         $partyId = Party::where('name', $partyId)->first();
    //         $partyShortName = strtolower($partyId->name);
    //         $query->whereRaw('LOWER(ls.voting_for) = ?', [$partyShortName]);
    //     }

    //     // $partyId = $request->input('voting_for');
    //     // if ($partyId) {
    //     //     $query->where('ls.voting_for', $partyId);
    //     // }


    //     if ($advance_poll == 'yes') {
    //         $query->where('voters.flagged', 1);
    //     }

    //     if ($isVoted === 'yes') {
    //         $query->whereExists(function ($q) {
    //             $q->select(DB::raw(1))
    //               ->from('voter_cards_images')
    //               ->whereColumn('voter_cards_images.reg_no', 'voters.voter'); 
    //         });
    //     }  


    //     if ($isVoted === 'no') {
    //         $query->whereNotExists(function ($q) {
    //             $q->select(DB::raw(1))
    //               ->from('voter_cards_images')
    //               ->whereColumn('voter_cards_images.reg_no', 'voters.voter'); 
    //         });

    //     }
    //     // Get sorting parameters
    //     $sortBy = $request->input('sort_by'); // voter, const, or polling
    //     $sortOrder = $request->input('sort_order', 'asc'); // asc or desc

    //     // Validate sort order
    //     $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc']) ? strtolower($sortOrder) : 'asc'; 

 
    //     // Apply filters

    //     if (!empty($polling) && is_numeric($polling)) {
    //         $query->where('voters.polling', $polling);
    //     }

    //     if ($underAge25 === 'yes') {
    //         $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
    //     }

    //     // Apply exists_in_database filter
    //     if ($existsInDatabase === 'true') { 
    //         $query->where('voters.exists_in_database', true);
    //     } elseif ($existsInDatabase === 'false') {
    //         $query->where('voters.exists_in_database', false);
    //     }
        
    //     if (!empty($const)) {
    //         $query->where('voters.const', $const);
    //     }
        
    //     if (!empty($surname)) {
    //         $query->whereRaw('LOWER(voters.surname) LIKE ?', ['%' . strtolower($surname) . '%']);
    //     }

    //     if (!empty($firstName)) {
    //         $query->whereRaw('LOWER(voters.first_name) LIKE ?', ['%' . strtolower($firstName) . '%']);
    //     }

    //     if (!empty($secondName)) {
    //         $query->whereRaw('LOWER(voters.second_name) LIKE ?', ['%' . strtolower($secondName) . '%']);
    //     }

    
    //     $query->where(function($q) use ($houseNumber, $address, $pobse, $pobis, $pobcn) {
    //         if ($houseNumber !== null && $houseNumber !== '') {
    //             $q->whereRaw('LOWER(voters.house_number) = ?', [strtolower($houseNumber)]);
    //         }
    //         if ($address !== null && $address !== '') {
    //             $q->whereRaw('LOWER(voters.address) = ?', [strtolower($address)]);
    //         }
    //         if ($pobse !== null && $pobse !== '') {
    //             $q->whereRaw('LOWER(voters.pobse) = ?', [strtolower($pobse)]);
    //         }
    //         if ($pobis !== null && $pobis !== '') {
    //             $q->whereRaw('LOWER(voters.pobis) = ?', [strtolower($pobis)]);
    //         }
    //         if ($pobcn !== null && $pobcn !== '') {
    //             $q->whereRaw('LOWER(voters.pobcn) = ?', [strtolower($pobcn)]);
    //         }
    //     }); 


    //     if (!empty($voterId) && is_numeric($voterId)) {
    //         $query->where('voters.voter', $voterId);
    //     }

    //     if (!empty($constituencyName)) {
    //         $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
    //     }

    //     if (!empty($constituencyId)) {
    //         $query->where('voters.const', $constituencyId); 
    //     }

 
    //     if (!empty($sortBy)) {
    //         switch ($sortBy) {
    //             case 'voter':
    //                 $query->orderBy('voters.voter', $sortOrder);
    //                 break;
    //             case 'const':
    //                 $query->orderBy('voters.const', $sortOrder);
    //                 break;
    //             case 'polling':
    //                 $query->orderBy('voters.polling', $sortOrder);
    //                 break;
    //             case 'first_name':
    //                 $query->orderByRaw('LOWER(voters.first_name) ' . strtoupper($sortOrder));
    //                 break;
    //             case 'last_name':
    //                 $query->orderByRaw('LOWER(TRIM(voters.surname)) ' . strtoupper($sortOrder));
    //                 break;
    //             default:
    //                 $query->orderBy('voters.id', 'desc'); 
    //                 break;
    //         }
    //     } else {
    //         // Default sorting
    //         $query->orderBy('voters.id', 'desc');
    //     }

    //     // Get paginated results

    //     if($export == 'true'){
    //         $voters = $query->get();
    //     }else{
    //         $voters = $query->paginate($request->get('per_page', 20));
    //     }
       
        
    //     return response()->json([
    //         'success' => true,
    //         'data' => $voters,
    //         // 'searchable_fields' => $searchableFields,
    //         'sorting_options' => [
    //             'voter' => 'Voter ID',
    //             'const' => 'Constituency ID',
    //             'polling' => 'Polling Station'
    //         ],
    //         'current_sort' => [
    //             'sort_by' => $sortBy,
    //             'sort_order' => $sortOrder
    //         ]
    //     ]); 
    // }
    


     
    
    public function electionDayGraph(Request $request)
    { 
        // Only Admin
        if (!auth()->check() || auth()->user()->role->name !== 'Admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($request->input('clear_all') === 'true') {
            Cache::flush();
            return response()->json([
                'success' => true,
                'message' => 'All cache cleared successfully'
            ]);
        }

        // Cache graph per filter set to reduce DB load; 5-minute TTL
        $cacheKey = 'election_day_graph_' . md5(json_encode($request->all()));
        $payload = Cache::rememberForever($cacheKey, function () use ($request) {
            // Define timeline mapping for slot display/labels (all times are EST)
            $slotLabels = [
                "8am", "9am", "10am", "11am", "12pm",
                "1pm", "2pm", "3pm", "4pm", "5pm", "530pm"
            ];

            // Base query: Apply all filters up until the DB time grouping
            $query = DB::table('voters')
                ->leftJoin('voter_cards_images as vci', 'voters.voter', '=', 'vci.reg_no')
                ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
                ->leftJoin('surveys', function($join) {
                    $join->on('surveys.voter_id', '=', 'voters.id')
                         ->whereRaw('surveys.id = (SELECT MAX(s2.id) FROM surveys as s2 WHERE s2.voter_id = voters.id)');
                })
                // Only voter_cards with a created_at timestamp for bucketing
                ->whereNotNull('vci.created_at');

            // Pull out filters (same as before)
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
            $partyId = $request->input('voting_for');

            if ($partyId) {

                if (is_numeric($partyId)) {
                    $party = Party::where('id', $partyId)->first();
                } else {
                    $party = Party::whereRaw('LOWER(name) = ?', [strtolower($partyId)])->first();
                }

          
               
              if ($party && isset($party->short_name)) {
                  $partyShortName = strtolower($party->short_name);
                  $query->whereRaw('LOWER(vci.exit_poll) = ?', [$partyShortName]);
              } else {
                  $query->whereRaw('1=0');
              }
          }

            if ($advance_poll == 'yes') {
                $query->where('voters.flagged', 1);
            }

            // is_surveyed filter
            if ($isSurveyed === 'yes') {
                $query->whereNotNull('surveys.id');
            } elseif ($isSurveyed === 'no') {
                $query->whereNull('surveys.id');
            }

            // is_voted filter (optimized: skip results if not voted)
            if ($isVoted === 'no') {
                $query->whereRaw('1=0');
            }

            if (!empty($polling) && is_numeric($polling)) {
                $query->where('voters.polling', $polling);
            }

            if ($underAge25 === 'yes') {
                $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
            }

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

            // Total voters (y axis should be all eligible voters matching the filter)
            $totalVoters = (clone $query)->distinct('voters.voter')->count('voters.voter');

            // NOTE: The following assumes 'vci.created_at' is in UTC. To convert to EST (UTC-5),
            // we use AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York'.
            // This ensures bucketing/grouping is done in EST regardless of server timezone.

            $rawCase = "CASE
                WHEN EXTRACT(HOUR FROM (vci.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York')) = 8 THEN '8am'
                WHEN EXTRACT(HOUR FROM (vci.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York')) = 9 THEN '9am'
                WHEN EXTRACT(HOUR FROM (vci.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York')) = 10 THEN '10am'
                WHEN EXTRACT(HOUR FROM (vci.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York')) = 11 THEN '11am'
                WHEN EXTRACT(HOUR FROM (vci.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York')) = 12 THEN '12pm'
                WHEN EXTRACT(HOUR FROM (vci.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York')) = 13 THEN '1pm'
                WHEN EXTRACT(HOUR FROM (vci.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York')) = 14 THEN '2pm'
                WHEN EXTRACT(HOUR FROM (vci.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York')) = 15 THEN '3pm'
                WHEN EXTRACT(HOUR FROM (vci.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York')) = 16 THEN '4pm'
                WHEN EXTRACT(HOUR FROM (vci.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York')) = 17 
                    AND EXTRACT(MINUTE FROM (vci.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York')) < 30 THEN '5pm'
                WHEN EXTRACT(HOUR FROM (vci.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York')) = 17 
                    AND EXTRACT(MINUTE FROM (vci.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York')) >= 30 THEN '530pm'
                ELSE NULL END as slot_label";

            $bucketQuery = (clone $query)
                ->selectRaw("$rawCase, COUNT(*) as count")
                ->whereRaw("(EXTRACT(HOUR FROM (vci.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York')) BETWEEN 8 AND 17)")
                ->groupByRaw('slot_label');

            $results = $bucketQuery->get();

            // Prepare slot counts with all slots filled
            $counts = array_fill_keys($slotLabels, 0);
            foreach($results as $row) {
                if ($row->slot_label && isset($counts[$row->slot_label])) {
                    $counts[$row->slot_label] = intval($row->count);
                }
            }

            // Build graph with cumulative totals, without EST in time
            $graph = [];
            $running = 0;
            foreach ($counts as $time => $inc) {
                $running += $inc;
                $graph[] = [
                    'time' => $time,
                    'increment' => $inc,
                    'value' => $running
                ];
            }

            $total = $running;

            // Y-axis: From 0 up to $totalVoters, 12 ticks evenly spaced
            $tickCount = 12;
            $step = $tickCount > 1 ? ($totalVoters / ($tickCount - 1)) : $totalVoters;
            $yAxis = [];
            for ($i = 0; $i < $tickCount; $i++) {
                $yAxis[] = (int)round($i * $step);
            }

            return [
                'success' => true,
                'total_voted' => $total,
                'total_voters' => $totalVoters,
                'slots' => array_keys($counts),
                'graph' => $graph,
                'y_axis' => $yAxis,
                'time_zone' => 'EST'
            ];
        });

        return response()->json($payload);
    }



    public function electionDayReport_one(Request $request) 
    {   
        // Check if user is authenticated and has admin role
        if (!auth()->check() || auth()->user()->role->name !== 'Admin') { 
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }

        // Generate cache key based on all request parameters
        $cacheKey = 'election_day_report_one_' . md5(json_encode($request->all()) . '_' . $request->get('per_page', 20));
        
        // Check if data exists in cache, otherwise execute query and cache forever
        $response = Cache::rememberForever($cacheKey, function() use ($request) {
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
                ->whereNotNull('vci.reg_no');

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

    public function PollingelectionDayGraph(Request $request)
    { 
        
        // Only Admin
        if (!auth()->check() || auth()->user()->role->name !== 'Admin') { 
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized - Admin access required',
                'data' => null
            ], 403);
        }

        // Cache graph per filter set to reduce DB load; forever TTL
        // $cacheKey = 'polling_election_day_graph_' . md5(json_encode($request->all()));
        // $payload = Cache::rememberForever($cacheKey, function () use ($request) {
            // Define timeline mapping for slot display/labels (all times are EST)
            $slotLabels = [
                "8am", "9am", "10am", "11am", "12pm",
                "1pm", "2pm", "3pm", "4pm", "5pm", "530pm"   
            ];

            // Base query: match base dataset of listVoterCardResult, but using DB::table for aggregation
            // Base table is voter_cards_images - include ALL records to match list count
            $query = DB::table('voter_cards_images as vci')
                ->leftJoin('voters', 'vci.reg_no', '=', 'voters.voter')
                ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id');

            // Filters aligned with VoterCardController::listVoterCardResult
            $voter      = $request->input('voter');         // reg_no
            $votingFor  = $request->input('voting_for');   // exit_poll
            $voterNull  = $request->input('voter_null');   // yes / no
            $polling    = $request->input('polling');      // voters.polling
            
            // Filter by voter (reg_no like)
            if ($voter !== null && $voter !== '') {
                $query->where('vci.reg_no', 'like', '%' . $voter . '%');
            }

            // Filter by party (exit_poll) - same semantics as listVoterCardResult
            if ($votingFor !== null && $votingFor !== '') {
                $get_party  = Party::where('name', $votingFor)->first();
                $votingFor = strtolower($get_party->short_name);
                $query->whereRaw('LOWER(vci.exit_poll) = ?', [strtolower($votingFor)]); 
            }

            // Filter by voter_null (reg_no null / not null)
            if ($voterNull === 'yes') {
                $query->whereNull('vci.reg_no');
            } elseif ($voterNull === 'no') {
                $query->whereNotNull('vci.reg_no');
            }

            // Filter by polling (via joined voters table)
            if (!empty($polling)) {
                $query->where('voters.polling', $polling); 
            }

            // NOTE: The following assumes 'vci.created_at' is in UTC. To convert to EST (UTC-5),
            // we use AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York'.
            $rawCase = "CASE
                WHEN EXTRACT(HOUR FROM (vci.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York')) = 8 THEN '8am'
                WHEN EXTRACT(HOUR FROM (vci.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York')) = 9 THEN '9am'
                WHEN EXTRACT(HOUR FROM (vci.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York')) = 10 THEN '10am'
                WHEN EXTRACT(HOUR FROM (vci.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York')) = 11 THEN '11am'
                WHEN EXTRACT(HOUR FROM (vci.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York')) = 12 THEN '12pm'
                WHEN EXTRACT(HOUR FROM (vci.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York')) = 13 THEN '1pm'
                WHEN EXTRACT(HOUR FROM (vci.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York')) = 14 THEN '2pm'
                WHEN EXTRACT(HOUR FROM (vci.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York')) = 15 THEN '3pm'
                WHEN EXTRACT(HOUR FROM (vci.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York')) = 16 THEN '4pm'
                WHEN EXTRACT(HOUR FROM (vci.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York')) = 17 
                    AND EXTRACT(MINUTE FROM (vci.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York')) < 30 THEN '5pm'
                WHEN EXTRACT(HOUR FROM (vci.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York')) = 17 
                    AND EXTRACT(MINUTE FROM (vci.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York')) >= 30 THEN '530pm'
                ELSE NULL END as slot_label";

            // Count records without created_at (these can't be time-bucketed but should be included in grand total)
            $queryWithoutTimestamp = (clone $query)->whereNull('vci.created_at');
            $countWithoutTimestamp = $queryWithoutTimestamp->count();

            // Count records with created_at but outside the 8am-5:30pm time range
            $queryOutsideTimeRange = (clone $query)
                ->whereNotNull('vci.created_at')
                ->whereRaw("NOT (EXTRACT(HOUR FROM (vci.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York')) BETWEEN 8 AND 17)");
            $countOutsideTimeRange = $queryOutsideTimeRange->count();

            // Bucket query: only records with created_at in the time range (8am-5:30pm EST)
            $bucketQuery = (clone $query)
                ->selectRaw('voters.polling as polling_station, ' . $rawCase . ', COUNT(*) as count')
                ->whereNotNull('vci.created_at')
                ->whereRaw("(EXTRACT(HOUR FROM (vci.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York')) BETWEEN 8 AND 17)")
                ->groupBy('voters.polling')
                ->groupByRaw('slot_label');

            $results = $bucketQuery->get();

            // Unbucketed per-poll counts: records without timestamp OR outside the 8am-5:30pm window
            $unbucketedQuery = (clone $query)
                ->selectRaw('voters.polling as polling_station, COUNT(*) as count')
                ->where(function($q) {
                    $q->whereNull('vci.created_at')
                      ->orWhereRaw("NOT (EXTRACT(HOUR FROM (vci.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York')) BETWEEN 8 AND 17)");
                })
                ->groupBy('voters.polling');

            $unbucketedResults = $unbucketedQuery->get(); 

            // Prepare structure keyed by polling station with all slots filled
            // Votes cast during a time slot (e.g., 8am-9am) are stored with that slot's label
            $polls = [];
            foreach ($results as $row) {
                if (!$row->slot_label) {
                    continue;
                }

                $pollKey = $row->polling_station ?? 'unknown';
                if (!isset($polls[$pollKey])) {
                    $polls[$pollKey] = [
                        'polling' => $row->polling_station,
                        'label' => $row->polling_station ? 'Poll ' . $row->polling_station : 'Poll',
                        'increments' => array_fill_keys($slotLabels, 0),
                        'cumulative' => array_fill_keys($slotLabels, 0),
                        'decrement' => array_fill_keys($slotLabels, 0),
                        'total' => 0
                    ];
                }

                // Store votes cast during this hour slot (temporary storage)
                $polls[$pollKey]['increments'][$row->slot_label] = intval($row->count); 
            }

            // Ensure polls are initialized for unbucketed results and add their counts to totals
            foreach ($unbucketedResults as $row) {
                $pollKey = $row->polling_station ?? 'unknown';
                if (!isset($polls[$pollKey])) {
                    $polls[$pollKey] = [
                        'polling' => $row->polling_station,
                        'label' => $row->polling_station ? 'Poll ' . $row->polling_station : 'Poll',
                        'increments' => array_fill_keys($slotLabels, 0),
                        'cumulative' => array_fill_keys($slotLabels, 0),
                        'decrement' => array_fill_keys($slotLabels, 0),
                        'total' => 0
                    ];
                }
                // Add unbucketed count to poll total (no slot increments)
                $polls[$pollKey]['total'] += intval($row->count);
            }

            // Calculate totals per poll and per slot
            $totalsBySlot = array_fill_keys($slotLabels, 0);
            $cumulativeBySlot = array_fill_keys($slotLabels, 0);
            foreach ($polls as &$poll) {
                // Store original vote counts by hour (votes cast during that hour)
                $votesByHour = $poll['increments'];
                
                // Calculate total votes for this poll (bucketed slots + any unbucketed added earlier)
                $poll['total'] = array_sum($votesByHour) + ($poll['total'] ?? 0);
                
                // Calculate total voters per slot across all polls (using original vote counts)
                foreach ($slotLabels as $slotLabel) {
                    $totalsBySlot[$slotLabel] += $votesByHour[$slotLabel] ?? 0;
                }
                
                // Calculate cumulative, increment, and decrement by comparing with previous slot
                $runningCumulative = 0;
                $previousCumulative = 0;
                
                foreach ($slotLabels as $index => $slotLabel) {
                    if ($index === 0) {
                        // First slot (8am): voting starts, no previous data
                        $poll['increments'][$slotLabel] = 0;
                        $poll['cumulative'][$slotLabel] = 0;
                        $poll['decrement'][$slotLabel] = 0;
                        $previousCumulative = 0;
                    } else {
                        // Get votes cast in the previous hour slot (new votes added)
                        $previousSlotLabel = $slotLabels[$index - 1];
                        $votesFromPreviousHour = $votesByHour[$previousSlotLabel] ?? 0;
                        
                        // Add votes from previous hour to running cumulative
                        // Cumulative = total votes cast up to this time point
                        $runningCumulative += $votesFromPreviousHour;
                        $currentCumulative = $runningCumulative;
                        
                        // Net change = current cumulative - previous cumulative
                        $netChange = $currentCumulative - $previousCumulative;
                        
                        // Increment = new votes added in the previous hour slot
                        $poll['increments'][$slotLabel] = $votesFromPreviousHour;
                        
                        // Cumulative = running total up to this time (based on last slot)
                        $poll['cumulative'][$slotLabel] = $currentCumulative;
                        
                        // Decrement = votes removed/invalidated during the previous hour slot
                        $poll['decrement'][$slotLabel] = max(0, $votesFromPreviousHour - $netChange);
                        
                        // Update previous cumulative for next iteration
                        $previousCumulative = $currentCumulative;
                    }
                }
            }
            unset($poll); // break reference

            // Totals for top-of-chart labels per poll (includes unbucketed counts)
            $topTotals = [];
            foreach ($polls as $poll) {
                $topTotals[] = [
                    'polling' => $poll['polling'],
                    'label' => $poll['label'],
                    'total' => $poll['total'],
                ];
            }

            // Cumulative totals per slot across all polls (stacked cumulative)
            $runningSlots = 0;
            foreach ($slotLabels as $slotLabel) {
                $runningSlots += $totalsBySlot[$slotLabel];
                $cumulativeBySlot[$slotLabel] = $runningSlots;
            }
            
            // Calculate grand total from all slot totals (sum of all votes cast across all polls and all time slots)
            // Include: records with timestamps in time range (bucketed) + records without timestamps + records outside time range
            $grandTotal = array_sum($totalsBySlot) + $countWithoutTimestamp + $countOutsideTimeRange;
             
            $payload = [
                'status' => 'success',
                'message' => 'Polling votes by hour', 
                'data' => [
                    'time_zone' => 'EST',
                    'slots' => $slotLabels,
                    'polls' => array_values($polls), // ensure numeric indexing for JSON
                    'top_totals' => $topTotals, // for labels above each poll
                    'totals_by_slot' => $totalsBySlot,
                    'cumulative_by_slot' => $cumulativeBySlot,
                    'grand_total' => $grandTotal,
                    'records_without_timestamp' => $countWithoutTimestamp, // for debugging/transparency
                    'records_outside_time_range' => $countOutsideTimeRange // for debugging/transparency 
                ]
            ];
           
            return $payload;
        //});

        return response()->json($payload);
    }

 
    
    public function print_voters(Request $request)
    {   
        
        // Check if user is authenticated and has admin role
        if (!auth()->check() || auth()->user()->role->name !== 'Admin') { 
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }
      
        // Optimized query: Use PostgreSQL DISTINCT ON for latest survey join
        // DISTINCT ON is highly optimized in PostgreSQL and works perfectly with indexes
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
            ->leftJoinSub($latestSurveySubquery, 'ls', 'ls.voter_id', '=', 'voters.id');
   
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
        $advance_poll = $request->input('advance_poll');
        $isVoted = $request->input('is_voted');
        $haveCorrectPhone = $request->input('have_correct_phone');

      
        if ($haveCorrectPhone == 'yes') {
            $query->where('ls.cell_phone', '!=', '9999999'); 
            $query->where('ls.work_phone', '!=',  null); 
            
        }

        $partyId = $request->input('voting_for');
        if ($partyId) {
            $party = Party::where('name', $partyId)->first();
            if ($party) {
                $partyName = strtolower($party->name);
                $query->whereRaw('LOWER(ls.voting_for) = ?', [$partyName]);
            }
        }

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

        if (!empty($constituencyName)) {
            $query->whereRaw('constituencies.name ILIKE ?', ['%' . $constituencyName . '%']);
        }

        if (!empty($constituencyId)) {
            $query->where('voters.const', $constituencyId);
        }

        // CRITICAL PERFORMANCE FIX: Sort in database (PostgreSQL) instead of PHP
        // Database sorting is 100x+ faster than PHP sorting for large datasets
        // This handles 50,000+ records efficiently using database indexes
        $query->orderByRaw('LOWER(TRIM(voters.surname)) ASC NULLS LAST')
              ->orderByRaw('LOWER(TRIM(voters.first_name)) ASC NULLS LAST')
              ->orderByRaw('LOWER(TRIM(voters.second_name)) ASC NULLS LAST');

        // Get the results from the DB (already sorted by database)
        $voters = $query->get();

        // ULTRA-OPTIMIZED TRANSFORMATION: Minimal operations for maximum speed
        // Pre-define field lists as simple arrays (foreach is faster than for loops)
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

        return response()->json([ 
            'success' => true,
            'data' => $transformed,
            'searchable_fields' => $searchableFields, 
        ]);  
    } 

    public function duplicateVoters(Request $request) 
    {   
        // Check if user is authenticated and has admin role
        if (!auth()->check() || auth()->user()->role->name !== 'Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }
     
        $perPage = $request->get('per_page', 20);

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

        $query->where(function ($q) use ($houseNumber, $address, $pobse, $pobis, $pobcn) {
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
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $voters,
            'searchable_fields' => $searchableFields, 
        ]); 

    }


   

    
    public function newlyRegistered(Request $request)   
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
            ->paginate($request->get('per_page', 20)); // Default to 20 items per page if not specified
        
        return response()->json([
            'success' => true,
            'data' => $voters,
            'searchable_fields' => $searchableFields,
        ]); 
    } 


    // ... existing code ...

   // ... existing code ...

   public function getVotersInSurveyBackup(Request $request)
   {
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
 

 
 

        

       
 

            $query = Voter::with('user')
            ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date','surveys.user_id','surveys.located','surveys.voting_decision','surveys.voting_for','surveys.is_died',
             'surveys.died_date','surveys.work_phone_code','surveys.work_phone','surveys.cell_phone_code','surveys.cell_phone','surveys.email') 
            ->join('constituencies', 'voters.const', '=', 'constituencies.id') 
            ->join(DB::raw("(
                SELECT DISTINCT ON (voter_id) * 
                FROM surveys 
                ORDER BY voter_id, created_at DESC
            ) as surveys"), 'voters.id', '=', 'surveys.voter_id')
            ->orderBy('surveys.created_at', 'desc');

 

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
            $query->where('surveys.challenge', true);
        }
        else if ($challenge === 'false') {
            $query->where('surveys.challenge', false); 
        }
        
        if ($existsInDatabase === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false);
        }

        $polling = $request->input('polling');

        if( $voting_for !== null && $voting_for !== ''){
            // Check if voting_for is numeric (ID) or a string (name)
            if (is_numeric($voting_for)) {
                $get_party = Party::where('id', $voting_for)->first();  
            } else {
                // Search by name (case-insensitive)
                $get_party = Party::whereRaw('LOWER(name) = ?', [strtolower($voting_for)])->first();
            }
            
            if ($get_party) {
                $voting_for = $get_party->name;
                $query->where('surveys.voting_for', $voting_for);
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
       $voters = $query->paginate($request->get('per_page', 20));

       
       return response()->json([
           'success' => true,
           'data' => $voters,
           'searchable_fields' => $searchableFields
       ]);
   }

   public function getVotersInSurvey(Request $request)
    {
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

       // Get paginated results - one voter per row (latest survey only)
       $voters = $query->paginate($request->get('per_page', 20));

       return response()->json([
           'success' => true,
           'data' => $voters,
           'searchable_fields' => $searchableFields
       ]);
   }


   public function getVotersDiffAddress(Request $request)
   {   
       // Check if user is authenticated and has admin role
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
       $voters = $query->orderBy('id', 'desc')->paginate($request->get('per_page', 20)); // Default to 20 items per page if not specified
       
       
       return response()->json([
           'success' => true,
           'data' => $voters,
           'searchable_fields' => $searchableFields,
            
       ]); 
   }
   

   public function getVotersNotInSurvey(Request $request)
   {
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
       $constituencyId = $request->input('const');
       $underAge25 = $request->input('under_age_25');
       $polling = $request->input('polling');
       $houseNumber = $request->input('house_number');
       $pobse = $request->input('pobse');
       $pobis = $request->input('pobis');
       $pobcn = $request->input('pobcn');
       $existsInDatabase = $request->input('exists_in_database');


       $query = Voter::query()
           ->select('voters.*', 'constituencies.name as constituency_name')
           ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
           ->whereNotExists(function ($query) {
               $query->select('id')
                   ->from('surveys')
                   ->whereColumn('surveys.voter_id', 'voters.id');
           });

           

           // Apply filters

           if (!empty($constituencyName)) {
            $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
        }
   
           if (!empty($constituencyId) && is_numeric($constituencyId)) {
               $query->where('voters.const', $constituencyId);
           }
           if (!empty($polling) && is_numeric($polling)) {
               $query->where('voters.polling', $polling);
           }
           if (!empty($voterId) && is_numeric($voterId)) {
            $query->where('voters.voter', $voterId);
        }

        $underAge25 = $request->input('under_age_25');
        if ($underAge25 === 'yes') {
            $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
        }

        // Apply exists_in_database filter
        if ($existsInDatabase === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false);
        }
       // Apply search filters
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

    
       // Get paginated results
       $voters = $query->orderBy('id', 'desc')->paginate($request->get('per_page', 20));


       $searchableFields = [
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
           'searchable_parameters' => $searchableFields
       ]);
   }
 
   public function getUnregisteredVoters(Request $request)
   { 
       if (!auth()->check() || auth()->user()->role->name !== 'Admin') {
           return response()->json([
               'success' => false,
               'message' => 'Unauthorized - User access required'
           ], 403); 
       }

       $query = UnregisteredVoter::with(['voter' => function($query) {
           $query->select('id', 'voter', 'first_name','second_name', 'address', 'pobse', 'const');
       }]); 

       // Add search functionality
       if ($request->has('search')) {
           $search = $request->search;
           $query->where(function($q) use ($search) {
               $q->where('name', 'LIKE', "%{$search}%")
               ->orWhere('phone_number', 'LIKE', "%{$search}%")
               ->orWhere('new_email', 'LIKE', "%{$search}%")
               ->orWhere('new_address', 'LIKE', "%{$search}%")
               ->orWhereHas('voter', function($q) use ($search) {
                   $q->where('first_name', 'LIKE', "%{$search}%")
                       ->orWhere('address', 'LIKE', "%{$search}%");
               });
           });
       }

       $underAge25 = $request->input('under_age_25');
       if ($underAge25 === 'yes') {
           $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, date_of_birth)) < 25');
       }
       // Add filters
       if ($request->has('gender')) {
           $query->where('gender', $request->gender);
       }

       if ($request->has('date_from') && $request->has('date_to')) {
           $query->whereBetween('date_of_birth', [$request->date_from, $request->date_to]);
       }

       // Add sorting
       $sortField = $request->get('sort_by', 'created_at');
       $sortDirection = $request->get('sort_direction', 'desc');
       $allowedSortFields = ['name', 'date_of_birth', 'gender', 'created_at'];
       $query->orderBy('id', 'desc');

       // Paginate results
       $per_page = $request->get('per_page', 20);
       $unregisteredVoters = $query->orderBy('id', 'desc')->paginate($per_page);

       // Build search parameters object
       $searchParams = [
           'search' => $request->search ?? null,
           'gender' => $request->gender ?? null,
           'date_from' => $request->date_from ?? null,
           'date_to' => $request->date_to ?? null,
           'sort_by' => $sortField,
           'sort_direction' => $sortDirection,
           'per_page' => $per_page,
           'name' => $request->name ?? null,
           'phone_number' => $request->phone_number ?? null,
           'new_email' => $request->new_email ?? null,
           'new_address' => $request->new_address ?? null,
           'first_name' => $request->first_name ?? null,
           'address' => $request->address ?? null,
           'under_age_25' => $request->under_age_25 ?? null
       ];

       return response()->json([
           'success' => true,
           'data' => $unregisteredVoters,
           'search_params' => $searchParams
       ]);
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

    public function addressSearch(Request $request)
    {
        $address = $request->input('address');
        $addresses = Voter::select('house_number', 'address', 'pobse', 'pobis', 'pobcn')
            ->where(function($query) use ($address) {
                $query->whereRaw('LOWER(CONCAT(house_number, \' \', address, \' \', pobse, \' \', pobis, \' \', pobcn)) LIKE ?', 
                    ['%' . strtolower($address) . '%']);
            })
            ->distinct()
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