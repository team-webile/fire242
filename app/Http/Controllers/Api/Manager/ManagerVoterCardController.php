<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\Voter;
use App\Models\Party;
use App\Models\VoterCard; 
use DB;
use App\Models\VoterCardImage;
use Illuminate\Support\Facades\Cache;
class ManagerVoterCardController extends Controller
{
//   public function getVoterCard_FNM(Request $request)
//   {

//     $constituencyIds = explode(',', auth()->user()->constituency_id);
//     $existsInDatabase = $request->input('exists_in_database');
//     $query = VoterCard::with('voter.constituency');
//     $query->where('circled_exit_poll', 'FNM');
//     $query->whereHas('voter', function ($q) use ($constituencyIds) {
//         $q->whereIn('const', $constituencyIds);
//     });
//     if ($existsInDatabase === 'true') { 
//         $query->whereHas('voter', function($q) use ($existsInDatabase) {
//             $q->where('voters.exists_in_database', true);
//         });
//     } elseif ($existsInDatabase === 'false') {
//         $query->whereHas('voter', function($q) use ($existsInDatabase) {
//             $q->where('voters.exists_in_database', false);
//         });
//     }

//      // Apply filters
//      if ($request->has('voter_id') && is_numeric($request->input('voter_id'))) {
//          $query->whereHas('voter', function($q) use ($request) {
//              $q->where('voter', $request->input('voter_id'));
//          });
//      }

//      if ($request->has('surname')) {
//          $query->whereHas('voter', function($q) use ($request) {
//              $q->whereRaw('LOWER(surname) LIKE ?', ['%' . strtolower($request->input('surname')) . '%']);
//          });
//      }

//      if ($request->has('first_name')) {
//          $query->whereHas('voter', function($q) use ($request) {
//              $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->input('first_name')) . '%']);
//          });
//      }

//      if ($request->has('second_name')) {
//          $query->whereHas('voter', function($q) use ($request) {
//              $q->whereRaw('LOWER(second_name) LIKE ?', ['%' . strtolower($request->input('second_name')) . '%']);
//          });
//      }

//      if ($request->has('address')) {
//          $query->whereHas('voter', function($q) use ($request) {
//              $q->whereRaw('LOWER(address) LIKE ?', ['%' . strtolower($request->input('address')) . '%']);
//          });
//      }

//      if ($request->has('constituency_id') && is_numeric($request->input('constituency_id'))) {
//          $query->whereHas('voter.constituency', function($q) use ($request) {
//              $q->where('id', $request->input('constituency_id'));
//          });
//      }

//      if ($request->has('polling') && is_numeric($request->input('polling'))) {
//          $query->whereHas('voter', function($q) use ($request) {
//              $q->where('polling', $request->input('polling'));
//          });
//      }

//      $getVoterCard = $query->orderBy('id', 'desc')->paginate($request->get('per_page', 20));

//      $searchParameters = $request->only([
//          'voter_id', 'surname', 'first_name', 'second_name', 
//          'address', 'constituency_id', 'polling'
//      ]); 

//      return response()->json([
//       'success' => true,
//       'message' => 'Voter Card retrieved successfully', 
//       'data' => $getVoterCard,
//       'search_parameters' => $searchParameters
//      ]);
//   }
    private function transformPaginatedResponse($paginatedData)
    {
        $transformedData = $paginatedData->getCollection()->map(function ($item) {
            return $this->transformVoterCardResponse($item);
        });
        
        $paginatedData->setCollection($transformedData);
        return $paginatedData;
    }

    private function transformVoterCardResponse($voterCardImage)
    {
        $data = $voterCardImage->toArray();
        
        // Map exit_poll to circled_exit_poll (keep both for compatibility)
        $data['circled_exit_poll'] = $data['exit_poll'] ?? null;
        $data['voter_name'] = $data['voter_name'] ?? null;
        // Map processed to status (keep both for compatibility)
        $data['status'] = $data['processed'] ?? 0;
        
        // Ensure voter relationship is properly structured
        if (isset($data['voter']) && is_array($data['voter'])) {
            // Voter relationship already loaded, keep as is
        } elseif ($voterCardImage->reg_no) {
            // Load voter relationship if not already loaded
            $voter = Voter::where('voter', $voterCardImage->reg_no)->with('constituency')->first();
            if ($voter) {
                $data['voter'] = $voter->toArray();
            }
        }
        
        return $data;
    }


    private function applyVoterCardFilters($query, $request)
    {
        $tableName = (new VoterCardImage())->getTable();
        
        if ($request->has('voter_id') && is_numeric($request->input('voter_id'))) {
            $query->where($tableName . '.reg_no', $request->input('voter_id'));
        }

        if ($request->has('surname')) {
            $query->whereRaw('LOWER(voters.surname) LIKE ?', ['%' . strtolower($request->input('surname')) . '%']);
        }

        if ($request->has('first_name')) {
            $query->whereRaw('LOWER(voters.first_name) LIKE ?', ['%' . strtolower($request->input('first_name')) . '%']);
        }

        if ($request->has('second_name')) {
            $query->whereRaw('LOWER(voters.second_name) LIKE ?', ['%' . strtolower($request->input('second_name')) . '%']);
        }

        if ($request->has('address')) {
            $query->whereRaw('LOWER(voters.address) LIKE ?', ['%' . strtolower($request->input('address')) . '%']);
        }

        if ($request->has('constituency_id') && is_numeric($request->input('constituency_id'))) {
          $query->where('voters.const', $request->input('const'));
        }


        if ($request->has('const') && is_numeric($request->input('const'))) {
            $query->where('voters.const', $request->input('const'));
        }

        if ($request->has('polling') && is_numeric($request->input('polling'))) {
            $query->where('voters.polling', $request->input('polling'));
        }

        if ($request->has('constituency_name') && !empty($request->constituency_name)) {
          $constituencyName = Constituency::where('name', $request->constituency_name)->first();
         
          $query->where('voters.const', $constituencyName->id);
        }
        
        $existsInDatabase = $request->input('exists_in_database');
        if ($existsInDatabase === 'true' || $existsInDatabase === true) {
          $query->where('voters.exists_in_database', true);
      } elseif ($existsInDatabase === 'false' || $existsInDatabase === false) {
        
          $query->where('voters.exists_in_database', false);
      }

 
    }

    public function getVoterCard_FNM(Request $request)
    {
        return $this->getVoterCardByParty($request, 'FNM');
    }


    private function getVoterCardByParty(Request $request, $party)
    {
      // Generate cache key based on all request parameters and party
      $cacheKey = 'voter_card_by_party_' . strtoupper($party) . '_' . md5(json_encode($request->all()) . '_' . $request->get('per_page', 20)); 
      
      // Check if data exists in cache, otherwise execute query and cache forever
      $constituencyIds = explode(',', auth()->user()->constituency_id);
      $response = Cache::rememberForever($cacheKey, function() use ($request, $party, $constituencyIds) {
          $tableName = (new VoterCardImage())->getTable();
          
          $query = VoterCardImage::query()
              ->leftJoin('voters', $tableName . '.reg_no', '=', 'voters.voter')
              ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
              ->leftJoin('surveys', function($join) {
                 $join->on('surveys.voter_id', '=', 'voters.id')
                      ->whereRaw('surveys.id = (SELECT MAX(s2.id) FROM surveys as s2 WHERE s2.voter_id = voters.id)');
             })
              ->select($tableName . '.*')
             //  ->whereRaw('UPPER(' . $tableName . '.exit_poll) = ?', [strtoupper($party)])
              ->whereRaw('UPPER(' . $tableName . '.exit_poll) = ?', [strtoupper($party)])
              ->whereNotNull($tableName . '.reg_no')
              ->whereIn('voters.const', $constituencyIds);
  
          $this->applyVoterCardFilters($query, $request);
  
          $perPage = min($request->get('per_page', 20), 100);
          $getVoterCard = $query->orderBy($tableName . '.id', 'desc')->paginate($perPage);
          
          // Load relationships after pagination to avoid conflicts
          $getVoterCard->load(['voter.constituency']);
  
          $transformedData = $this->transformPaginatedResponse($getVoterCard);
  
          $searchParameters = $request->only([
              'voter_id', 'surname', 'first_name', 'second_name', 
              'address', 'constituency_id', 'polling'
          ]); 
  
          return [
              'success' => true,
              'message' => 'Voter Card retrieved successfully', 
              'data' => $transformedData,
              'search_parameters' => $searchParameters
          ];
      });
  
      return response()->json($response);
    }


  public function getVoterCard_PLP(Request $request){
    return $this->getVoterCardByParty($request, 'PLP');
  }
  public function getVoterCard_DNA(Request $request){
    return $this->getVoterCardByParty($request, 'DNA');
  }
  public function getVoterCard_UNK(Request $request){
   
    return $this->getVoterCardByParty($request, 'UNK');
  }



  public function getConstituencyReports(Request $request)   
  { 
      $constituencyIds = explode(',', auth()->user()->constituency_id);
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
          ->orderBy('c.id', 'asc')
          ->paginate($request->input('per_page', 20));

      return response()->json([
          'success' => true,
          'message' => 'Constituencies retrieved successfully',
          'data' => $results,
      ]);
  }

  public function getConstituencyReport2(Request $request)
    {

    
        $constituencyIds = explode(',', auth()->user()->constituency_id);
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
    
    

    public function addVoterCardResult(Request $request){

        $validator = Validator::make($request->all(), [ 
          'voter_id' => 'required|exists:voters,voter',
          'voter_name' => 'nullable|string',
          'party' => 'required',
          'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);
        if ($validator->fails()) {
          return response()->json(['success' => false, 'message' => 'Validation failed', 'data' => $validator->errors()], 422);
        }
        
        // Check if voter_id already exists
        $existingRecord = VoterCardImage::where('reg_no', $request->voter_id)->first();
        
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
          'voter_name' => isset($request->voter_name) && !empty($request->voter_name) ? $request->voter_name : null,
          'exit_poll' => $request->party,
          'image' => $imagePath,
          'processed' => 1,
        ]);
        
        if($voterCardImage){
          // Clear cached reports and party-based voter card lists
          Cache::flush();
    
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
    
      public function updateVoterCardResult(Request $request, $id){
        $validator = Validator::make($request->all(), [ 
          'voter_id' => 'sometimes|required|exists:voters,voter',
          'party' => 'sometimes|required',
          'voter_name' => 'nullable|string',  
          'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]); 
        
        if ($validator->fails()) {
          return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'data' => $validator->errors()
          ], 422);
        }
    
        $voterCardImage = VoterCardImage::find($id);
        
        if (!$voterCardImage) {
          return response()->json([
            'success' => false,
            'message' => 'Voter card result not found',
            'data' => null
          ], 404);
        }
    
        // If voter_id is being updated, check if new voter_id already exists
        if ($request->has('voter_id') && $request->voter_id != $voterCardImage->reg_no) {
          $existingRecord = VoterCardImage::where('reg_no', $request->voter_id)
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
    
        if ($request->has('voter_name')) {
          $updateData['voter_name'] = $request->voter_name ?? null; 
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
    
        // Clear cached reports and party-based voter card lists
        Cache::flush();
    
        return response()->json([
          'success' => true,
          'message' => 'Voter card result updated successfully',
          'data' => $voterCardImage
        ], 200);
      }
    
      public function deleteVoterCardResult(Request $request, $id){ 
        $voterCardImage = VoterCardImage::find($id);
        
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
    
    
      public function getVoterCardResult(Request $request, $id){
        $voterCardImage = VoterCardImage::with('user', 'voter.constituency')->find($id);
        
        if (!$voterCardImage) {
          return response()->json([
            'success' => false,
            'message' => 'Voter card result not found',
            'data' => null
          ], 404);
        }
    
        $transformedData = $this->transformVoterCardResponse($voterCardImage);
    
        return response()->json([
          'success' => true,
          'message' => 'Voter card result retrieved successfully',
          'data' => $transformedData
        ], 200);
      }
    
      public function listVoterCardResult(Request $request){
    
        $query = VoterCardImage::with(['user', 'voter.constituency'])->where('user_id', auth()->user()->id)->orderBy('id', 'desc');
    
        // Filter by voter_id if provided
        if ($request->has('voter') && !empty($request->get('voter'))) {
          $query->where('reg_no', 'like', '%' . $request->get('voter') . '%');
        }
        // Filter by party (exit_poll) if provided
        $votingFor = $request->get('voting_for');
        if ($votingFor !== null && $votingFor !== '') {
            if (is_numeric($votingFor)) {
                $party = Party::where('id', $votingFor)->first();
            } else {
                $party = Party::whereRaw('LOWER(name) = ?', [strtolower($votingFor)])->first();
            }
            if ($party) {
                $partyShortName = strtolower($party->short_name);
                $query->whereRaw('LOWER(exit_poll) = ?', [$partyShortName]);
            }
        }
    
    
        if ($request->has('voter_null') && !empty($request->get('voter_null')) && $request->get('voter_null') == 'yes') {
          $query->whereNull('reg_no');
        }else if ($request->has('voter_null') && !empty($request->get('voter_null')) && $request->get('voter_null') == 'no') {
          $query->whereNotNull('reg_no');
        }
    
    
        if ($request->has('polling') && !empty($request->get('polling'))) {
          
          $query->whereHas('voter', function($q) use ($request) {
            $q->where('polling', $request->get('polling'));
          });
        }
    
     
        $perPage = min($request->get('per_page', 20), 100);
        $voterCardImages = $query->paginate($perPage);
    
        // Transform the response to match expected format
        $transformedData = $this->transformPaginatedResponse($voterCardImages);
    
        return response()->json([ 
          'success' => true,
          'message' => 'Voter card result list retrieved successfully',
          'data' => $transformedData,
        ], 200);
      }
    
      public function getVoterWithId(Request $request, $id){
    
        $constituency_id = explode(',', auth()->user()->constituency_id);
        $voter = Voter::where('voter', $id)->whereIn('const', $constituency_id)->first();
        if (!$voter) {
          return response()->json([
            'success' => false,
            'message' => 'Voter not found',
            'data' => null
          ], 404);
        } 
        
        return response()->json([
          'success' => true,
          'message' => 'Voter retrieved successfully',
          'data' => $voter->first_name . ' ' . $voter->second_name . ' ' . $voter->surname
        ], 200);
      }





    public function getConstituencyReport4(Request $request)
    { 
 
          // Get party names using EXACT same method as getVotersInSurvey
          // getVotersInSurvey uses: Party::whereRaw('LOWER(name) = ?', [strtolower($voting_for)])->first()
          $constituencyIds = explode(',', auth()->user()->constituency_id);
          $fnmParty = DB::table('parties')->whereRaw('LOWER(name) = ?', ['free national movement'])->first();
          $plpParty = DB::table('parties')->whereRaw('LOWER(name) = ?', ['progressive liberal party'])->first();
          $coiParty = DB::table('parties')->whereRaw('LOWER(name) = ?', ['coalition of independents'])->first();
          
          // Use the exact party name from database (same as getVotersInSurvey)
          $fnmName = $fnmParty ? $fnmParty->name : 'Free National Movement';
          $plpName = $plpParty ? $plpParty->name : 'Progressive Liberal Party';
          $coiName = $coiParty ? $coiParty->name : 'Coalition of Independents';

          // Build query EXACTLY like getVotersInSurvey - using INNER JOIN with raw subquery
          // This ensures only voters WITH surveys are counted (same as getVotersInSurvey)
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
                ->whereIn('v.const', $constituencyIds);  // INNER JOIN - only voters with surveys

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

          // Apply challenge filter - SAME as getVotersInSurvey
          if ($challenge === 'true') {
              $query->whereRaw('ls.challenge IS TRUE');
          } elseif ($challenge === 'false') {
              $query->whereRaw('ls.challenge IS FALSE');
          }

          // Apply exists_in_database filter - SAME as getVotersInSurvey
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

          // Apply is_died filter - SAME as getVotersInSurvey
          if ($is_died !== null && $is_died !== '') {
              $query->where('ls.is_died', $is_died);
          }

          // Apply died_date filter - SAME as getVotersInSurvey
          if ($died_date !== null && $died_date !== '') {
              $query->where('ls.died_date', $died_date);
          }

          // Apply voting_decision filter - SAME as getVotersInSurvey
          if (!empty($voting_decision)) {
              $query->where('ls.voting_decision', $voting_decision);
          }

          // Apply located filter - SAME as getVotersInSurvey
          if (!empty($located)) {
              $query->whereRaw('LOWER(ls.located) = ?', [strtolower($located)]);
          }

          // Apply polling filter - SAME as getVotersInSurvey
          if (!empty($polling) && is_numeric($polling)) {
              $query->where('v.polling', $polling);
          }

          // Apply under_age_25 filter - SAME as getVotersInSurvey
          if ($underAge25 === 'yes') {
              $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, v.dob)) < 25');
          }

          // Apply user_id filter - SAME as getVotersInSurvey
          if (!empty($user_id)) {
              $query->where('ls.user_id', $user_id);
          }

          // Apply date range filters - SAME as getVotersInSurvey
          if (!empty($start_date)) {
              $query->where('ls.created_at', '>=', $start_date . ' 00:00:00');
          }
          if (!empty($end_date)) {
              $query->where('ls.created_at', '<=', $end_date . ' 23:59:59');
          }

          // Apply name filters - SAME as getVotersInSurvey
          if (!empty($surname)) {
              $query->whereRaw('LOWER(v.surname) LIKE ?', ['%' . strtolower($surname) . '%']);
          }
          if (!empty($firstName)) {
              $query->whereRaw('LOWER(v.first_name) LIKE ?', ['%' . strtolower($firstName) . '%']);
          }
          if (!empty($secondName)) {
              $query->whereRaw('LOWER(v.second_name) LIKE ?', ['%' . strtolower($secondName) . '%']);
          }

          // Apply address filters - SAME as getVotersInSurvey
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

          // Apply voter ID filter - SAME as getVotersInSurvey
          if (!empty($voterId) && is_numeric($voterId)) {
              $query->where('v.voter', $voterId);
          }

          // Apply constituency name filter - SAME as getVotersInSurvey
          if (!empty($constituencyName)) {
              $query->whereRaw('LOWER(c.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
          }

          // Apply constituency ID filter - SAME as getVotersInSurvey
          if (!empty($constituencyId) && is_numeric($constituencyId)) {
              $query->where('v.const', $constituencyId);
          }

          // Select aggregated data by polling division
          // Using exact party names from database for consistent matching
          $results = $query->select(
              'v.polling as polling_division', 
              // Count by voting_for from latest survey per voter - using DB party names
              DB::raw("COUNT(DISTINCT CASE WHEN ls.voting_for = '$fnmName' THEN v.id END) as fnm_count"),
              DB::raw("COUNT(DISTINCT CASE WHEN ls.voting_for = '$plpName' THEN v.id END) as plp_count"),
              DB::raw("COUNT(DISTINCT CASE WHEN ls.voting_for = '$coiName' THEN v.id END) as coi_count"),
              DB::raw("COUNT(DISTINCT CASE WHEN ls.voting_for IS NOT NULL AND ls.voting_for NOT IN ('$fnmName', '$plpName', '$coiName') THEN v.id END) as other_count"),
              // Voters with NULL voting_for (they have survey but no voting preference)
              DB::raw("COUNT(DISTINCT CASE WHEN ls.voting_for IS NULL THEN v.id END) as no_vote_count"),
              // All surveyed voters in polling division
              DB::raw("COUNT(DISTINCT v.id) as total_count"),

              // Percentages based on surveyed voters
              DB::raw("ROUND((COUNT(DISTINCT CASE WHEN ls.voting_for = '$fnmName' THEN v.id END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as fnm_percentage"),
              DB::raw("ROUND((COUNT(DISTINCT CASE WHEN ls.voting_for = '$plpName' THEN v.id END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as plp_percentage"),
              DB::raw("ROUND((COUNT(DISTINCT CASE WHEN ls.voting_for = '$coiName' THEN v.id END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as coi_percentage"),
              DB::raw("ROUND((COUNT(DISTINCT CASE WHEN ls.voting_for IS NOT NULL AND ls.voting_for NOT IN ('$fnmName', '$plpName', '$coiName') THEN v.id END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as other_percentage"),
              DB::raw("ROUND((COUNT(DISTINCT CASE WHEN ls.voting_for IS NULL THEN v.id END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as no_vote_percentage")
          )
          ->groupBy('v.polling')
          ->orderBy('v.polling', 'asc')
          ->paginate($request->input('per_page', 20));

          // Transform: add total_party_count (sum of fnm, plp, coi, other counts) to each item
          $results->getCollection()->transform(function ($item) {
              $item->total_party_count =
                  $item->fnm_count
                  + $item->plp_count
                  + $item->coi_count
                  + $item->other_count;
              return $item;
          });

          // Calculate grand totals across ALL polling divisions (not just current page)
          // Clone the base query to get totals without pagination
          $totalsQuery = DB::table('voters as v')
              ->leftJoin('constituencies as c', 'v.const', '=', 'c.id')
              ->join(DB::raw("(
                  SELECT DISTINCT ON (voter_id) 
                      voter_id,
                      voting_for
                  FROM surveys 
                  ORDER BY voter_id, id DESC
              ) as ls"), 'ls.voter_id', '=', 'v.id');

          // Apply same filters to totals query
          $existsInDatabase = $request->input('exists_in_database');
          $constituencyId = $request->input('const') ?? $request->input('constituency_id');
          $constituencyName = $request->input('constituency_name');
          $voting_for = $request->input('voting_for');

          if ($existsInDatabase === 'true') {
              $totalsQuery->where('v.exists_in_database', true);
          } elseif ($existsInDatabase === 'false') {
              $totalsQuery->where('v.exists_in_database', false);
          }

          if (!empty($constituencyId) && is_numeric($constituencyId)) {
              $totalsQuery->where('v.const', $constituencyId);
          }

          if (!empty($constituencyName)) {
              $totalsQuery->whereRaw('LOWER(c.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
          }

          if ($voting_for !== null && $voting_for !== '') {
              if (is_numeric($voting_for)) {
                  $get_party = DB::table('parties')->where('id', $voting_for)->first();
              } else {
                  $get_party = DB::table('parties')->whereRaw('LOWER(name) = ?', [strtolower($voting_for)])->first();
              }
              if ($get_party) {
                  $totalsQuery->where('ls.voting_for', $get_party->name);
              }
          }

          // Get grand totals
          $grandTotals = $totalsQuery->selectRaw("
              COUNT(DISTINCT CASE WHEN ls.voting_for = '$fnmName' THEN v.id END) as fnm_total,
              COUNT(DISTINCT CASE WHEN ls.voting_for = '$plpName' THEN v.id END) as plp_total,
              COUNT(DISTINCT CASE WHEN ls.voting_for = '$coiName' THEN v.id END) as coi_total,
              COUNT(DISTINCT CASE WHEN ls.voting_for IS NOT NULL AND ls.voting_for NOT IN ('$fnmName', '$plpName', '$coiName') THEN v.id END) as other_total,
              COUNT(DISTINCT CASE WHEN ls.voting_for IS NULL THEN v.id END) as no_vote_total,
              COUNT(DISTINCT v.id) as grand_total
          ")->first();

          // DEBUG: Get verification count using EXACT same query as getVotersInSurvey
          // This should match the total from getVotersInSurvey API
          $verificationQuery = DB::table('voters')
              ->select('voters.id')
              ->join(DB::raw("(
                  SELECT DISTINCT ON (voter_id) voter_id, voting_for
                  FROM surveys 
                  ORDER BY voter_id, id DESC
              ) as ls"), 'ls.voter_id', '=', 'voters.id');
          
          // Apply FNM filter for verification (same as getVotersInSurvey)
          $verificationQuery->where('ls.voting_for', $fnmName);
          $fnmVerificationCount = $verificationQuery->count();

          // Also get all distinct voting_for values to debug
          $votingForValues = DB::table('surveys')
              ->select('voting_for')
              ->distinct()
              ->whereNotNull('voting_for')
              ->pluck('voting_for')
              ->toArray();

          return response()->json([
              'success' => true,
              'message' => 'Voter cards report retrieved successfully',
              'data' => $results,
              'grand_totals' => [
                  'fnm_total' => (int)$grandTotals->fnm_total,
                  'plp_total' => (int)$grandTotals->plp_total,
                  'coi_total' => (int)$grandTotals->coi_total,
                  'other_total' => (int)$grandTotals->other_total,
                  'no_vote_total' => (int)$grandTotals->no_vote_total,
                  'grand_total' => (int)$grandTotals->grand_total
              ],
              'debug' => [
                  'fnm_name_used' => $fnmName,
                  'fnm_verification_count' => $fnmVerificationCount,
                  'distinct_voting_for_values' => $votingForValues,
                  'message' => 'fnm_verification_count should equal 15739 (getVotersInSurvey FNM count)'
              ]
          ]);
      }


      public function electionDayGraph(Request $request)
      { 
           
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
                  ->whereNotNull('vci.created_at')
                  ->whereIn('voters.const', explode(',', auth()->user()->constituency_id));
                 
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

              // Y-axis: Generate sensible tick values without duplicates
              $tickCount = 12;
              $yAxis = [];
              
              if ($totalVoters == 0) {
                  // No data - return default scale 0 to 100
                  for ($i = 0; $i < $tickCount; $i++) {
                      $yAxis[] = (int)round($i * (100 / ($tickCount - 1)));
                  }
              } elseif ($totalVoters <= $tickCount) {
                  // Small number of voters - return unique values from 0 to totalVoters
                  for ($i = 0; $i <= $totalVoters; $i++) {
                      $yAxis[] = $i;
                  }
              } else {
                  // Normal case - evenly spaced ticks
                  $step = $totalVoters / ($tickCount - 1);
                  for ($i = 0; $i < $tickCount; $i++) {
                      $yAxis[] = (int)round($i * $step);
                  }
                  // Remove any duplicates and re-index
                  $yAxis = array_values(array_unique($yAxis));
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

}