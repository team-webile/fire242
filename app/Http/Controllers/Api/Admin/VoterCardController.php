<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\Voter;
use App\Models\Constituency;
use App\Models\VoterCardImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class VoterCardController extends Controller
{
    /**
     * Apply common filters to voter card query
     */
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

    /**
     * Transform VoterCardImage data to match old VoterCard response structure
     */
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

    /**
     * Transform paginated response
     */
    private function transformPaginatedResponse($paginatedData)
    {
        $transformedData = $paginatedData->getCollection()->map(function ($item) {
            return $this->transformVoterCardResponse($item);
        });
        
        $paginatedData->setCollection($transformedData);
        return $paginatedData;
    }

    public function voter_cards_stats(Request $request){
        $total_card_voter = VoterCardImage::count();
        $pending_card_voter = VoterCardImage::where('processed', 0)->count();
        $completed_card_voter = VoterCardImage::where('processed', 1)->count();
        
        return response()->json([
            'success' => true,
            'message' => 'Voter cards statistics retrieved successfully',
            'data' => [
                'total_card_voter' => $total_card_voter,
                'pending_card_voter' => $pending_card_voter,
                'completed_card_voter' => $completed_card_voter
            ]
        ]);   
    }
  public function getVoterCard(Request $request){
     $tableName = (new VoterCardImage())->getTable();
     $query = VoterCardImage::query()
         ->leftJoin('voters', $tableName . '.reg_no', '=', 'voters.voter')
         ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
         ->select($tableName . '.*');

     if ($request->has('voter_for')) {
        $query->whereRaw('LOWER(' . $tableName . '.exit_poll) LIKE ?', ['%' . strtolower($request->input('voter_for')) . '%']);
     }

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

     return response()->json([
      'success' => true,
      'message' => 'Voter Card retrieved successfully', 
      'data' => $transformedData,
      'search_parameters' => $searchParameters
     ]);
  }
  /**
   * Get voter cards filtered by party
   */
  private function getVoterCardByParty(Request $request, $party)
  {
    // Generate cache key based on all request parameters and party
    $cacheKey = 'voter_card_by_party_' . strtoupper($party) . '_' . md5(json_encode($request->all()) . '_' . $request->get('per_page', 20)); 
    
    // Check if data exists in cache, otherwise execute query and cache forever
    $response = Cache::rememberForever($cacheKey, function() use ($request, $party) {
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
            ->whereNotNull($tableName . '.reg_no');

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


 
  

  public function getVoterCard_FNM(Request $request){
     return $this->getVoterCardByParty($request, 'FNM');
  }

  public function getVoterCard_PLP(Request $request){
     return $this->getVoterCardByParty($request, 'PLP');
  }

  public function getVoterCard_DNA(Request $request){
     return $this->getVoterCardByParty($request, 'COI');
  }

  public function getVoterCard_UNK(Request $request){
     return $this->getVoterCardByParty($request, 'UNK');
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
    $query = VoterCardImage::with(['user', 'voter.constituency'])->orderBy('id', 'desc');

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
    $voter = Voter::where('voter', $id)->first();
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