<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Constituency;
use App\Models\Voter;
use App\Models\User;
use App\Models\Survey;
use App\Models\VoterCardImage;
use App\Models\Island;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
class ConstituencyController extends Controller
{


    public function updatePositions(Request $request)
    {


        $validator = Validator::make($request->all(), [
            'items' => 'required|array',
            'items.*.id' => 'required|integer',
            'items.*.position' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // First verify all IDs exist before updating
        $ids = collect($request->items)->pluck('id');
        $existingIds = Constituency::whereIn('id', $ids)->pluck('id');
        
        // Find which IDs don't exist
        $invalidIds = $ids->diff($existingIds);
        
        if ($invalidIds->isNotEmpty()) {
            return response()->json([
                'message' => 'One or more constituency IDs do not exist',
                'invalid_ids' => $invalidIds->values()
            ], 422);
        }

        foreach ($request->items as $item) {
            Constituency::where('id', $item['id'])
                ->update(['position' => $item['position']]);
        }

        return response()->json(['message' => 'Positions updated successfully']);
    } 


    public function index(Request $request)
    {
        $query = Constituency::query();
        
     
        
        if ($request->has('constituency_name') && !empty($request->constituency_name)) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->constituency_name) . '%']);
            $searchParams['constituency_name'] = $request->constituency_name;
        }

        if ($request->has('island_name') && !empty($request->island_name)) {
            $query->whereHas('island', function($q) use ($request) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->island_name) . '%']);
            });
            $searchParams['island_name'] = $request->island_name;
        }

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->boolean('status'));
            $searchParams['status'] = $request->boolean('status');
        }

        $constituencies = $query->orderBy('position','asc')->paginate($request->input('per_page', 20));

        $searchParams = [
            'constituency_name' => $request->constituency_name,
            'island_name' => $request->island_name,
            'status' => $request->boolean('status')
        ];

        return response()->json([
            'success' => true,
            'message' => 'Constituencies retrieved successfully',
            'data' => $constituencies,
            'search_params' => $searchParams
        ]); 
    }
    public function getConstituencies(Request $request)
    {
        $query = Constituency::query();
        
     
        
        if ($request->has('constituency_name') && !empty($request->constituency_name)) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->constituency_name) . '%']);
            $searchParams['constituency_name'] = $request->constituency_name;
        }

        if ($request->has('island_name') && !empty($request->island_name)) {
            $query->whereHas('island', function($q) use ($request) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->island_name) . '%']);
            });
            $searchParams['island_name'] = $request->island_name;
        }

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->boolean('status'));
            $searchParams['status'] = $request->boolean('status');
        }

        $constituencies = $query->orderBy('position','asc')->get();

        $searchParams = [
            'constituency_name' => $request->constituency_name,
            'island_name' => $request->island_name,
            'status' => $request->boolean('status')
        ];

        return response()->json([
            'success' => true,
            'message' => 'Constituencies retrieved successfully',
            'data' => $constituencies,
            'search_params' => $searchParams
        ]); 
    }

    public function store(Request $request)
    {
     
        $validator = Validator::make($request->all(), [
            //'name' => 'required|string|unique:constituencies,name|max:255',
            'island_id' => 'required|exists:islands,id',  // Add this line
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $constituency = Constituency::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Constituency created successfully',
            'data' => $constituency
        ], 201);
    }

    public function show(Constituency $constituency)
    {
         
        return response()->json([
            'success' => true,
            'message' => 'Constituency retrieved successfully',
            'data' => $constituency
        ]);
    }

    public function update(Request $request, Constituency $constituency)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                //Rule::unique('constituencies')->ignore($constituency->id)
            ],
            'island_id' => 'required|exists:islands,id',  // Add this line
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $constituency->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Constituency updated successfully',
            'data' => $constituency
        ]);
    }

    public function destroy(Constituency $constituency)
    {
        $constituency->delete();

        return response()->json([
            'success' => true,
            'message' => 'Constituency deleted successfully'
        ]);
    }


    public function getIslands()
    {
        $query = Island::query(); 
  
        if (request()->has('search')) {
            $query->where('name', 'like', '%' . request()->search . '%');
        }

        $islands = $query->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Islands retrieved successfully',
            'data' => $islands
        ]);
    }


    // public function getConstituencyReports(Request $request)  
    // { 
    //     // First get all active parties
    //     $parties = DB::table('parties')
    //         ->where('status', 'active')
    //         ->orderBy('position')
    //         ->get();

    //     $query = DB::table('constituencies as c')
    //         ->leftJoin('voters as v', 'v.const', '=', 'c.id')
    //         ->leftJoin('surveys as s', 's.voter_id', '=', 'v.id');

    //     if ($request->has('constituency_id')) {
    //         $query->where('c.id', $request->constituency_id);
    //     }

    //     if ($request->has('constituency_name')) {
    //         $query->whereRaw('LOWER(c.name) LIKE ?', ['%' . strtolower($request->constituency_name) . '%']);
    //     }

    //     // Build the select statement dynamically
    //     $selects = [
    //         'c.id as constituency_id',
    //         'c.name as constituency_name',
    //         DB::raw('COUNT(DISTINCT v.id) as total_voters'),
    //         DB::raw('COUNT(DISTINCT s.id) as surveyed_voters'),
    //         DB::raw('COUNT(DISTINCT v.id) - COUNT(DISTINCT s.id) as not_surveyed_voters'),
    //         DB::raw('ROUND((COUNT(DISTINCT s.id) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as surveyed_percentage'),
    //     ];

    //     // Add party-specific counts and percentages
    //     foreach ($parties as $party) {
    //         $partyName = $party->name;
    //         // Replace hyphens with underscores and make lowercase for column names
    //         $shortName = str_replace('-', '_', strtolower($party->short_name));
            
    //         // Add count for this party
    //         $selects[] = DB::raw("COUNT(DISTINCT CASE WHEN s.voting_for = '$partyName' THEN s.id END) as {$shortName}_count");
            
    //         // Add percentage for this party
    //         $selects[] = DB::raw("ROUND((COUNT(DISTINCT CASE WHEN s.voting_for = '$partyName' THEN s.id END) * 100.0) / NULLIF(COUNT(DISTINCT s.id), 0), 2) as {$shortName}_percentage");
    //     }

    //     // Add gender statistics
    //     $selects = array_merge($selects, [
    //         DB::raw("COUNT(DISTINCT CASE WHEN s.sex = 'Male' THEN s.id END) as total_male_surveyed"),
    //         DB::raw("COUNT(DISTINCT CASE WHEN s.sex = 'Female' THEN s.id END) as total_female_surveyed"), 
    //         DB::raw("COUNT(DISTINCT CASE WHEN s.sex IS NULL OR s.sex = '' THEN s.id END) as total_no_gender_surveyed"),
    //         DB::raw("ROUND((COUNT(DISTINCT CASE WHEN s.sex = 'Male' THEN s.id END) * 100.0) / NULLIF(COUNT(DISTINCT s.id), 0), 2) as male_percentage"),
    //         DB::raw("ROUND((COUNT(DISTINCT CASE WHEN s.sex = 'Female' THEN s.id END) * 100.0) / NULLIF(COUNT(DISTINCT s.id), 0), 2) as female_percentage")
    //     ]);

    //     $rawResults = $query->select($selects)
    //         ->groupBy('c.id', 'c.name')
    //         ->orderBy('c.id')
    //         ->paginate($request->input('per_page', 20));

    //     // Transform the results to include party data as a map
    //     $results = $rawResults->map(function($row) use ($parties) {
    //         $transformedRow = [
    //             'constituency_id' => $row->constituency_id,
    //             'constituency_name' => $row->constituency_name,
    //             'total_voters' => $row->total_voters,
    //             'surveyed_voters' => $row->surveyed_voters,
    //             'not_surveyed_voters' => $row->not_surveyed_voters,
    //             'surveyed_percentage' => $row->surveyed_percentage,
    //             'parties' => [],
    //             'gender' => [
    //                 'male' => [
    //                     'count' => $row->total_male_surveyed,
    //                     'percentage' => $row->male_percentage
    //                 ],
    //                 'female' => [
    //                     'count' => $row->total_female_surveyed,
    //                     'percentage' => $row->female_percentage
    //                 ],
    //                 'unspecified' => [
    //                     'count' => $row->total_no_gender_surveyed,
    //                     'percentage' => 100 - ($row->male_percentage + $row->female_percentage)
    //                 ]
    //             ]
    //         ];

    //         // Add party data as a map
    //         foreach ($parties as $party) {
    //             $shortName = str_replace('-', '_', strtolower($party->short_name));
    //             $countKey = "{$shortName}_count";
    //             $percentageKey = "{$shortName}_percentage";
                
    //             $transformedRow['parties'][$party->short_name] = [
    //                 'count' => $row->$countKey,
    //                 'percentage' => $row->$percentageKey
    //             ];
    //         }

    //         return $transformedRow;
    //     });

        

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Constituencies retrieved successfully',
    //         'data' => $rawResults,
             
    //     ]);
    // }



     public function getConstituencyReport4(Request $request)
    {
        // Build the query with joins - starting from voters to match index function count
        $query = DB::table('voters as v')
            ->leftJoin('constituencies as c', 'v.const', '=', 'c.id')
            ->leftJoin('surveys', function($join) {
                $join->on('surveys.voter_id', '=', 'v.id')
                     ->whereRaw('surveys.id = (SELECT MAX(s2.id) FROM surveys as s2 WHERE s2.voter_id = v.id)');
            })
            ->leftJoin('voter_cards_images as vci', 'vci.reg_no', '=', 'v.voter')
            ->whereNotNull('vci.reg_no');
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
        if ($existsInDatabase === 'true' || $existsInDatabase === true) {
            $query->where('v.exists_in_database', true);
        } elseif ($existsInDatabase === 'false' || $existsInDatabase === false) {
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
            // Use DISTINCT vci.reg_no for UNIQUE voter id count in images (not vci.id)
            DB::raw("COUNT(DISTINCT CASE WHEN vci.exit_poll = 'fnm' THEN vci.reg_no END) as fnm_count"),
            DB::raw("COUNT(DISTINCT CASE WHEN vci.exit_poll = 'plp' THEN vci.reg_no END) as plp_count"),
            DB::raw("COUNT(DISTINCT CASE WHEN vci.exit_poll = 'dna' THEN vci.reg_no END) as dna_count"),
            DB::raw("COUNT(DISTINCT CASE WHEN vci.exit_poll NOT IN ('fnm', 'plp', 'dna') AND vci.exit_poll IS NOT NULL THEN vci.reg_no END) as other_count"),
            // For no_vote_count, those voters with NO voter card image at all
            DB::raw("COUNT(DISTINCT CASE WHEN vci.reg_no IS NULL THEN v.id END) as no_vote_count"),
            // All voters in polling division
            DB::raw("COUNT(DISTINCT v.id) as total_count"),

            // Percentages (use reg_no counts in numerator)
            DB::raw("ROUND((COUNT(DISTINCT CASE WHEN vci.exit_poll = 'fnm' THEN vci.reg_no END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as fnm_percentage"),
            DB::raw("ROUND((COUNT(DISTINCT CASE WHEN vci.exit_poll = 'plp' THEN vci.reg_no END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as plp_percentage"),
            DB::raw("ROUND((COUNT(DISTINCT CASE WHEN vci.exit_poll = 'dna' THEN vci.reg_no END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as dna_percentage"),
            DB::raw("ROUND((COUNT(DISTINCT CASE WHEN vci.exit_poll NOT IN ('fnm', 'plp', 'dna') AND vci.exit_poll IS NOT NULL THEN vci.reg_no END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as other_percentage"),
            DB::raw("ROUND((COUNT(DISTINCT CASE WHEN vci.reg_no IS NULL THEN v.id END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as no_vote_percentage")
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
    
    public function getConstituencyReports(Request $request)   
    { 
         
        $existsInDatabase = $request->input('exists_in_database');
        // First get all active parties
        $parties = DB::table('parties')
            ->where('status', 'active')
            ->orderBy('position') 
            ->get();

        $query = DB::table('constituencies as c')
            ->leftJoin('voters as v', 'v.const', '=', 'c.id')
            ->leftJoin(DB::raw("(
                SELECT DISTINCT ON (voter_id) * 
                FROM surveys 
                ORDER BY voter_id, created_at DESC
            ) as s"), 'v.id', '=', 's.voter_id');
       

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

        $rawResults = $query->select($selects)
            ->groupBy('c.id', 'c.name')
            ->orderBy('c.id', 'asc')
            ->paginate($request->input('per_page', 20));

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

        return response()->json([
            'success' => true,
            'message' => 'Constituencies retrieved successfully',
            'data' => $rawResults
        ]);
    }


    public function getConstituencyReport1(Request $request)
    {   
        $existsInDatabase = $request->input('exists_in_database');
        $query = DB::table('constituencies as c')
            ->leftJoin('voters as v', 'v.const', '=', 'c.id')
            ->leftJoin(DB::raw("(
                SELECT DISTINCT ON (voter_id) * 
                FROM surveys 
                ORDER BY voter_id, created_at DESC
            ) as s"), 'v.id', '=', 's.voter_id');

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
            ->orderBy('c.id', 'asc')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Constituencies retrieved successfully',
            'data' => $results,
        ]);
    }

    // public function getConstituencyReport1(Request $request)
    // {
    //     $query = DB::table('constituencies as c')
    //         ->leftJoin('voters as v', 'v.const', '=', 'c.id')
    //         ->leftJoin('surveys as s', 's.voter_id', '=', 'v.id');

    //     if ($request->has('constituency_id')) {
    //         $query->where('c.id', $request->constituency_id);
    //     }

    //     if ($request->has('constituency_name')) {
    //         $query->whereRaw('LOWER(c.name) LIKE ?', ['%' . strtolower($request->constituency_name) . '%']);
    //     }

    //     $results = $query->select(
    //             'c.id as constituency_id',
    //             'c.name as constituency_name',
    //             DB::raw('COUNT(DISTINCT s.id) as total_surveyed'),
    //             DB::raw('COUNT(DISTINCT v.id) as total_voters'),
    //             DB::raw('ROUND((COUNT(DISTINCT s.id) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as percentage')
    //         )
    //         ->groupBy('c.id', 'c.name')
    //         ->orderBy('c.name')
    //         ->paginate($request->input('per_page', 20));
 
    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Constituencies retrieved successfully',
    //         'data' => $results,
    //     ]);  
    // }


 

    public function getConstituencyReport2(Request $request)
    {
        $existsInDatabase = $request->input('exists_in_database');
        $parties = DB::table('parties')
            ->where('status', 'active')
            ->orderBy('position')
            ->get();

        $query = DB::table('constituencies as c')
            ->leftJoin('voters as v', 'v.const', '=', 'c.id')
            // ->leftJoin('surveys as s', 's.voter_id', '=', 'v.id');

            ->leftJoin(DB::raw("(
                SELECT DISTINCT ON (voter_id) * 
                FROM surveys 
                ORDER BY voter_id, created_at DESC
            ) as s"), 'v.id', '=', 's.voter_id');


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

        $rawResults = $query->select($selects)
            ->groupBy('c.id', 'c.name')
            ->orderBy('c.id', 'asc')
            ->paginate($request->input('per_page', 20));

        // Transform the results to include party data as a map
        $results = $rawResults->getCollection()->map(function($row) use ($parties) {
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

        // Create a new paginator instance with the transformed data
        $paginatedResults = new \Illuminate\Pagination\LengthAwarePaginator(
            $results,
            $rawResults->total(),
            $rawResults->perPage(),
            $rawResults->currentPage(),
            ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]
        );

        return response()->json([ 
            'success' => true,
            'message' => 'Constituencies retrieved successfully',
            'data' => $paginatedResults
        ]);
    } 


    /**
     * Get voter cards report grouped by polling division
     * Shows counts and percentages for each party by polling station
     * 
     * Filters: constituency_id, constituency_name, polling, exists_in_database, under_age_25,
     *          surname, first_name, second_name, voter, house_number, address, pobse, pobis, pobcn
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    // public function voterCardsReport(Request $request)
    // {
    //     // Generate cache key based on all request parameters
    //     $cacheKey = 'voter_cards_report_' . md5(json_encode($request->all()) . '_' . $request->get('per_page', 20));
        
    //     // Check if data exists in cache, otherwise execute query and cache forever
    //     $response = Cache::rememberForever($cacheKey, function() use ($request) {
    //         // Build the query with joins - starting from voters to match index function count
    //         $query = DB::table('voters as v')
    //             ->leftJoin('constituencies as c', 'v.const', '=', 'c.id')
    //             ->leftJoin('surveys', function($join) {
    //                 $join->on('surveys.voter_id', '=', 'v.id')
    //                      ->whereRaw('surveys.id = (SELECT MAX(s2.id) FROM surveys as s2 WHERE s2.voter_id = v.id)');
    //             })
    //             ->leftJoin('voter_cards_images as vci', 'vci.reg_no', '=', 'v.voter')
    //             ->whereNotNull('vci.reg_no');
    //         // Get filter parameters
    //         $existsInDatabase = $request->input('exists_in_database');
    //         $underAge25 = $request->input('under_age_25');
    //         $surname = $request->input('surname');
    //         $firstName = $request->input('first_name');
    //         $secondName = $request->input('second_name');
    //         $voterId = $request->input('voter');
    //         $houseNumber = $request->input('house_number'); 
    //         $address = $request->input('address');
    //         $pobse = $request->input('pobse');
    //         $pobis = $request->input('pobis');
    //         $pobcn = $request->input('pobcn');
            
    //         // Apply filters matching the index function
    //         if ($request->has('constituency_id') && !empty($request->constituency_id)) {
    //             $query->where('v.const', $request->constituency_id);
    //         }
    //         if ($request->has('constituency_name') && !empty($request->constituency_name)) {
    //             $query->whereRaw('LOWER(c.name) LIKE ?', ['%' . strtolower($request->constituency_name) . '%']); 
    //         }
    //         if ($request->has('polling') && !empty($request->polling)) {
    //             $query->where('v.polling', $request->polling);
    //         }
            
    //         // exists_in_database filter
    //         if ($existsInDatabase === 'true' || $existsInDatabase === true) {
    //             $query->where('v.exists_in_database', true);
    //         } elseif ($existsInDatabase === 'false' || $existsInDatabase === false) {
    //             $query->where('v.exists_in_database', false);
    //         }
    //         // under_age_25 filter
    //         if ($underAge25 === 'yes') {
    //             $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, v.dob)) < 25');
    //         }
    //         // Name filters
    //         if (!empty($surname)) {
    //             $query->whereRaw('LOWER(v.surname) LIKE ?', ['%' . strtolower($surname) . '%']);
    //         }
    //         if (!empty($firstName)) {
    //             $query->whereRaw('LOWER(v.first_name) LIKE ?', ['%' . strtolower($firstName) . '%']);
    //         }
    //         if (!empty($secondName)) {
    //             $query->whereRaw('LOWER(v.second_name) LIKE ?', ['%' . strtolower($secondName) . '%']);
    //         }
    //         // Voter ID filter
    //         if (!empty($voterId) && is_numeric($voterId)) {
    //             $query->where('v.voter', $voterId);
    //         }
    //         // Address filters
    //         $query->where(function($q) use ($houseNumber, $address, $pobse, $pobis, $pobcn) {
    //             if ($houseNumber !== null && $houseNumber !== '') {
    //                 $q->whereRaw('LOWER(v.house_number) = ?', [strtolower($houseNumber)]);
    //             }
    //             if ($address !== null && $address !== '') {
    //                 $q->whereRaw('LOWER(v.address) = ?', [strtolower($address)]);
    //             }
    //             if ($pobse !== null && $pobse !== '') {
    //                 $q->whereRaw('LOWER(v.pobse) = ?', [strtolower($pobse)]);
    //             }
    //             if ($pobis !== null && $pobis !== '') {
    //                 $q->whereRaw('LOWER(v.pobis) = ?', [strtolower($pobis)]);
    //             }
    //             if ($pobcn !== null && $pobcn !== '') {
    //                 $q->whereRaw('LOWER(v.pobcn) = ?', [strtolower($pobcn)]);
    //             }
    //         });

    //         // Select aggregated data by polling division
    //         $results = $query->select(
    //             'v.polling as polling_division',
    //             // Use DISTINCT vci.reg_no for UNIQUE voter id count in images (not vci.id)
    //             DB::raw("COUNT(DISTINCT CASE WHEN vci.exit_poll = 'fnm' THEN vci.reg_no END) as fnm_count"),
    //             DB::raw("COUNT(DISTINCT CASE WHEN vci.exit_poll = 'plp' THEN vci.reg_no END) as plp_count"),
    //             DB::raw("COUNT(DISTINCT CASE WHEN vci.exit_poll = 'dna' THEN vci.reg_no END) as dna_count"),
    //             DB::raw("COUNT(DISTINCT CASE WHEN vci.exit_poll NOT IN ('fnm', 'plp', 'dna') AND vci.exit_poll IS NOT NULL THEN vci.reg_no END) as other_count"),
    //             // For no_vote_count, those voters with NO voter card image at all
    //             DB::raw("COUNT(DISTINCT CASE WHEN vci.reg_no IS NULL THEN v.id END) as no_vote_count"), 
    //             // All voters in polling division
    //             DB::raw("COUNT(DISTINCT v.id) as total_count"),

    //             // Percentages (use reg_no counts in numerator)
    //             DB::raw("ROUND((COUNT(DISTINCT CASE WHEN vci.exit_poll = 'fnm' THEN vci.reg_no END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as fnm_percentage"),
    //             DB::raw("ROUND((COUNT(DISTINCT CASE WHEN vci.exit_poll = 'plp' THEN vci.reg_no END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as plp_percentage"),
    //             DB::raw("ROUND((COUNT(DISTINCT CASE WHEN vci.exit_poll = 'dna' THEN vci.reg_no END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as dna_percentage"),
    //             DB::raw("ROUND((COUNT(DISTINCT CASE WHEN vci.exit_poll NOT IN ('fnm', 'plp', 'dna') AND vci.exit_poll IS NOT NULL THEN vci.reg_no END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as other_percentage"),
    //             DB::raw("ROUND((COUNT(DISTINCT CASE WHEN vci.reg_no IS NULL THEN v.id END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as no_vote_percentage")
    //         )->groupBy('v.polling')
    //         ->orderBy('v.polling', 'asc')
    //         ->paginate($request->input('per_page', 20));

    //         // Transform: add total_party_count (sum of fnm, plp, dna, other counts) to each item
    //         $results->getCollection()->transform(function ($item) {
    //             $item->total_party_count =
    //                 $item->fnm_count
    //                 + $item->plp_count
    //                 + $item->dna_count
    //                 + $item->other_count;
    //             return $item;
    //         });

    //         return [
    //             'success' => true,
    //             'message' => 'Voter cards report retrieved successfully',
    //             'data' => $results
    //         ];
    //     });

    //     return response()->json($response);
    // }

    
    
    
    public function voterCardsReport(Request $request)
    {
        // Build the query with joins - starting from voters to match index function count
        $query = DB::table('voters as v')
            ->leftJoin('constituencies as c', 'v.const', '=', 'c.id')
            ->leftJoin('voter_cards_images as vci', 'vci.reg_no', '=', 'v.voter'); 

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



    public function getAllConstituencies(Request $request)
    {
        $query = Constituency::query();

        // Add search by constituency name if provided
        if ($request->has('constituency_name') && !empty($request->input('constituency_name'))) {
            $query->where('name', 'like', '%' . $request->input('constituency_name') . '%');
        }

        $constituencies = $query->get();

        return response()->json([
            'success' => true,
            'message' => 'All constituencies retrieved successfully',
            'data' => $constituencies
        ]);
    }

  


    

// ... existing code ...

}