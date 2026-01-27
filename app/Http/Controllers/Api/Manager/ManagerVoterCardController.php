<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\Voter;
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
        if ($request->has('voting_for') && !empty($request->get('voting_for'))) {
          $query->whereRaw('LOWER(exit_poll) = ?', [strtolower($request->get('voting_for'))]);
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







}