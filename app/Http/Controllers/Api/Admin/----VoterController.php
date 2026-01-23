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
      
    public function index(Request $request) 
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
                'ls.home_phone_code',
                'ls.home_phone',
                'ls.work_phone_code',
                'ls.work_phone',
                'ls.cell_phone_code',
                'ls.cell_phone',
                'ls.voting_for'
            )
            ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
            ->leftJoinSub(
                DB::table('surveys as s1')
                    ->select(
                        's1.voter_id',
                        's1.home_phone_code',
                        's1.home_phone',
                        's1.work_phone_code',
                        's1.work_phone',
                        's1.cell_phone_code',
                        's1.cell_phone',
                        's1.voting_for'
                    )
                    ->join(
                        DB::raw('(SELECT voter_id, MAX(id) AS max_id FROM surveys GROUP BY voter_id) as s2'),
                        function ($join) {
                            $join->on('s1.voter_id', '=', 's2.voter_id')
                                ->on('s1.id', '=', 's2.max_id');
                        }
                    ), 
                'ls', 
                'ls.voter_id', 
                '=', 
                'voters.id'
            );

            
 
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
        if ($partyId) {
            $query->where('ls.voting_for', $partyId);
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
            $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
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
        ]); 
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
            $query->where('surveys.voting_for', $partyId);
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

        return response()->json($response); 
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
      
        $query = Voter::query()
            ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.*')
            ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
            ->leftJoin(DB::raw("(
                SELECT DISTINCT ON (voter_id) * 
                FROM surveys 
                ORDER BY voter_id, created_at DESC
            ) as surveys"), 'voters.id', '=', 'surveys.voter_id');
   
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


        $partyId = $request->input('voting_for');
        if ($partyId) {
            $query->where('surveys.voting_for', $partyId);
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

        if ($advance_poll == 1) {
            $query->where('voters.flagged', 1);
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

        // Get the results from the DB
        $voters = $query->get();

        // Deep sort by full surname, then full first_name, then full second_name (all letters, case-insensitive)
        // Sorting pattern: Full alphabetical comparison of all letters in each name field
        // Example: AAA AAA AAA, AAA AAA AAB, AAA AAB AAA, etc.
        $sorted = collect($voters)->sort(function($a, $b) {
            // Sort by full surname (case-insensitive, all letters)
            $surnameA = strtolower(trim($a->surname ?? ''));
            $surnameB = strtolower(trim($b->surname ?? ''));
            $surnameCompare = strcmp($surnameA, $surnameB);
            if ($surnameCompare !== 0) {
                return $surnameCompare;
            }
            
            // If surnames are equal, sort by full first_name (case-insensitive, all letters)
            $firstNameA = strtolower(trim($a->first_name ?? ''));
            $firstNameB = strtolower(trim($b->first_name ?? ''));
            $firstNameCompare = strcmp($firstNameA, $firstNameB);
            if ($firstNameCompare !== 0) {
                return $firstNameCompare;
            }
            
            // If first names are also equal, sort by full second_name (case-insensitive, all letters)
            $secondNameA = strtolower(trim($a->second_name ?? ''));
            $secondNameB = strtolower(trim($b->second_name ?? ''));
            return strcmp($secondNameA, $secondNameB);
        })->values();

        // Transform results to nest survey data under each voter
        $transformed = $sorted->map(function($voter) {
            $voterArray = $voter->toArray();
            
            // List of voter fields (excluding survey fields)
            $voterFields = [
                'id', 'const', 'polling', 'voter', 'surname', 'first_name', 'second_name', 
                'dob', 'pobcn', 'pobis', 'pobse', 'house_number', 'aptno', 'blkno', 
                'address', 'newly_registered', 'created_at', 'updated_at', 'is_contacted', 
                'diff_address', 'living_constituency', 'search_vector', 'exists_in_database', 
                'last_checked_at', 'flagged', 'constituency_name'
            ];
            
            // Extract survey data - check if survey exists by looking for voter_id (unique to surveys)
            $survey = null;
            $hasSurvey = isset($voterArray['voter_id']) && $voterArray['voter_id'] !== null;
            
            if ($hasSurvey) {
                $surveyFields = [];
                
                // Extract all fields that are not voter fields - these are survey fields
                foreach ($voterArray as $key => $value) {
                    // Include all fields that are not in voterFields (these are from surveys table)
                    if (!in_array($key, $voterFields) && $key !== 'survey') {
                        $surveyFields[$key] = $value;
                    }
                }
                
                // Set survey object if we have data
                if (!empty($surveyFields)) {
                    $survey = $surveyFields;
                }
            }
            
            // Build voter data with only voter fields
            $voterData = [];
            foreach ($voterFields as $field) {
                if (isset($voterArray[$field])) {
                    $voterData[$field] = $voterArray[$field];
                }
            }
            
            // Add survey to voter data (null if no survey)
            $voterData['survey'] = $survey;
            
            return $voterData;
        });

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

        $query = Voter::query()
        ->select('voters.*', 'constituencies.name as constituency_name')
        ->join('constituencies', 'voters.const', '=', 'constituencies.id')
        ->whereExists(function ($subquery) {
            $subquery->select(\DB::raw(1))
                ->from('voters as v2')
                ->whereColumn([
                    ['v2.surname', 'voters.surname'],
                    ['v2.first_name', 'voters.first_name'],
                    //['v2.second_name', 'voters.second_name'],
                    ['v2.dob', 'voters.dob']
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
        // Apply filters

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
        if ($existsInDatabase === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false);
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
        $voters = $query->orderBy('id', 'desc')->where('newly_registered', 1)->paginate($request->get('per_page', 20)); // Default to 20 items per page if not specified
        
        return response()->json([
            'success' => true,
            'data' => $voters,
            'searchable_fields' => $searchableFields,
        ]); 
    }


    // ... existing code ...

   // ... existing code ...

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
 

 
 

        // $query = Voter::with('user') 
        //     ->select(
        //         'voters.*',
        //         'constituencies.name as constituency_name',
        //         'surveys.id as survey_id',
        //         'surveys.created_at as survey_date',
        //         'surveys.user_id',
        //         'surveys.located',
        //         'surveys.voting_decision',
        //         'surveys.voting_for',
        //         'surveys.is_died',
        //         'surveys.died_date',
        //         'surveys.work_phone_code',
        //         'surveys.work_phone',
        //         'surveys.cell_phone_code',
        //         'surveys.cell_phone',
        //         'surveys.email',
        //         'surveys.home_phone_code',
        //         'surveys.home_phone',
        //         'surveys.special_comments',
        //         'surveys.other_comments',

        //     )
        //     ->join('constituencies', 'voters.const', '=', 'constituencies.id')
        //     ->join(DB::raw("(
        //         SELECT DISTINCT ON (voter_id) * 
        //         FROM surveys 
        //         ORDER BY voter_id, created_at DESC
        //     ) as surveys"), 'voters.id', '=', 'surveys.voter_id')
        //     ->orderBy('surveys.created_at', 'desc');

       

            // ->select(
            //     'voters.*',
            //     'constituencies.name as constituency_name',
            //     'surveys.id as survey_id',
            //     'surveys.created_at as survey_date',
            //     'surveys.user_id',
                
            // )

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

// $query = Voter::with('user')
//         ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date','surveys.user_id','surveys.located','surveys.voting_decision','surveys.voting_for','surveys.is_died',
//         'surveys.died_date','surveys.work_phone_code','surveys.work_phone','surveys.cell_phone_code','surveys.cell_phone','surveys.email') 
//         ->join('constituencies', 'voters.const', '=', 'constituencies.id')
//         ->join('surveys', 'voters.id', '=', 'surveys.voter_id')
//         ->orderBy('surveys.id', 'desc');


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

        if ($existsInDatabase === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false);
        }

        $polling = $request->input('polling');

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

      


       $query = Voter::with('user')
           ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date','surveys.user_id','surveys.located','surveys.voting_decision','surveys.voting_for','surveys.is_died','surveys.died_date')
           ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
           ->join('surveys', 'voters.id', '=', 'surveys.voter_id')
           ->whereExists(function ($query) {
               $query->select('id')
                   ->from('surveys')
                   ->whereColumn('surveys.voter_id', 'voters.id');
           })
           ->where('surveys.is_died', 1)
           ->orderBy('surveys.id', 'desc');

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

        $polling = $request->input('polling');

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
        if ($existsInDatabase === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false);
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