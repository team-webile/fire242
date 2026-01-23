<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\UnregisteredVoter;
use App\Models\Voter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UnregisteredVoterController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $perPage = $request->input('per_page', 20); 

            // Get counts
            $totalUnregistered = UnregisteredVoter::count();
            $totalMale = UnregisteredVoter::where('gender', 'male')->count();
            $totalFemale = UnregisteredVoter::where('gender', 'female')->count();
            $totalContacted = UnregisteredVoter::where('contacted', 1)->count();
            $totalUncontacted = UnregisteredVoter::where('contacted', 0)->count(); 

            // $query = UnregisteredVoter::with(['voter' => function($query) {
            //     $query->select('id', 'voter', 'first_name', 'second_name', 'address', 'pobse', 'const');
            // }]);


            $query = UnregisteredVoter::with([
                'voter' => function($query) {
                    $query->select('id', 'voter', 'first_name', 'second_name', 'address', 'pobse', 'const');
                },
                'surveyerConstituency:id,name',
                'livingConstituency:id,name'
            ]); 



            // Add search functionality
            // Apply individual search filters

            if (isset($request->surveyer_constituency) && !empty($request->surveyer_constituency)) {
                $query->whereHas('surveyerConstituency', function($q) use ($request) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->surveyer_constituency) . '%']);
                });
            } 

            // Search in livingConstituency
            if (isset($request->living_constituency) && !empty($request->living_constituency)) {
                $query->whereHas('livingConstituency', function($q) use ($request) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->living_constituency) . '%']);
                });
            }

            if ($request->has('start_date')) {
                $query->where('created_at', '>=', $request->start_date . ' 00:00:00');
            }

            if ($request->has('end_date')) {
                $query->where('created_at', '<=', $request->end_date . ' 23:59:59'); 
            } 

            if (isset($request->under_age_25) && $request->input('under_age_25') === 'yes') {
                $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, CAST(unregistered_voters.date_of_birth AS DATE))) < 25');
            }
            
            

            if (isset($request->first_name) && !empty($request->first_name)) {
                $query->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->first_name) . '%']);
            }

            if (isset($request->last_name) && !empty($request->last_name)) {
                $query->whereRaw('LOWER(last_name) LIKE ?', ['%' . strtolower($request->last_name) . '%']); 
            }
            
            if (isset($request->phone_number) && !empty($request->phone_number)) {
                $query->where('phone_number', 'LIKE', '%' . $request->phone_number . '%');
            }

            if (isset($request->new_email) && !empty($request->new_email)) {
                $query->whereRaw('LOWER(new_email) LIKE ?', ['%' . strtolower($request->new_email) . '%']);
            }

            if (isset($request->new_address) && !empty($request->new_address)) {
                $query->whereRaw('LOWER(new_address) LIKE ?', ['%' . strtolower($request->new_address) . '%']);
            }

            if (isset($request->survey_id) && !empty($request->survey_id)) {
                $query->where('survey_id', 'LIKE', '%' . $request->survey_id . '%');
            }

            if (isset($request->user_id) && !empty($request->user_id)) {
                $query->where('user_id', 'LIKE', '%' . $request->user_id . '%');
            }

            if (isset($request->voter_id) && !empty($request->voter_id)) {
                $query->where('voter_id', 'LIKE', '%' . $request->voter_id . '%');
            }

            // Search in related voter fields
            if (isset($request->voter_first_name) && !empty($request->voter_first_name)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->voter_first_name) . '%']);
                });
            }

            if (isset($request->voter_second_name) && !empty($request->voter_second_name)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(second_name) LIKE ?', ['%' . strtolower($request->voter_second_name) . '%']);
                });
            }

            if (isset($request->voter_number) && !empty($request->voter_number)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->where('voter', 'LIKE', '%' . $request->voter_number . '%');
                });
            }

            if (isset($request->voter_address) && !empty($request->voter_address)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(address) LIKE ?', ['%' . strtolower($request->voter_address) . '%']);
                });
            }

            // Add filters
            if (isset($request->gender) && !empty($request->gender)) {
                $query->whereRaw('LOWER(gender) = ?', [strtolower($request->gender)]);
            }

            if (isset($request->date_from) && !empty($request->date_from) && isset($request->date_to) && !empty($request->date_to)) {
                $query->whereBetween('date_of_birth', [$request->date_from, $request->date_to]);
            } 

            // Add sorting
            $query->orderBy('id', 'desc');

            // Get paginated results
            $unregisteredVoters = $query->paginate($perPage);

            // Build search parameters object
            $searchParams = [
                'first_name' => $request->first_name ?? null,
                'last_name' => $request->last_name ?? null,
                'phone_number' => $request->phone_number ?? null,
                'new_email' => $request->new_email ?? null,
                'new_address' => $request->new_address ?? null,
                'survey_id' => $request->survey_id ?? null,
                'user_id' => $request->user_id ?? null,
                'voter_id' => $request->voter_id ?? null,
                'voter_first_name' => $request->search ?? null,
                'voter_second_name' => $request->search ?? null,
                'voter_number' => $request->search ?? null,
                'voter_address' => $request->search ?? null,
                'under_age_25' => $request->under_age_25 ?? null
            ];

            return response()->json([
                'success' => true,
                'data' => $unregisteredVoters,
                'search_params' => $searchParams,
                'counts' => [
                    'total_unregistered' => $totalUnregistered,
                    'total_male' => $totalMale,
                    'total_female' => $totalFemale,
                    'total_contacted' => $totalContacted,
                    'total_uncontacted' => $totalUncontacted
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving unregistered voters',
                'error' => $e->getMessage()
            ], 500);
        }
    } 
    public function getUnregisteredVotersDiffAddress(Request $request)
    {
        try {
            $user = Auth::user();
            $perPage = $request->input('per_page', 20); 

            // Get counts
            $totalUnregistered = UnregisteredVoter::count();
            $totalMale = UnregisteredVoter::where('gender', 'male')->count();
            $totalFemale = UnregisteredVoter::where('gender', 'female')->count();
            $totalContacted = UnregisteredVoter::where('contacted', 1)->count();
            $totalUncontacted = UnregisteredVoter::where('contacted', 0)->count(); 

            $query = UnregisteredVoter::with(['voter' => function($query) {
                $query->select('id', 'voter', 'first_name', 'second_name', 'address', 'pobse', 'const');
            }])
            ->leftJoin('constituencies as lc', 'unregistered_voters.living_constituency', '=', 'lc.id')
            ->leftJoin('constituencies as sc', 'unregistered_voters.surveyer_constituency', '=', 'sc.id')
            ->select(
                'unregistered_voters.*',
                'lc.name as living_constituency_name',
                'sc.name as surveyer_constituency_name'
            )
            ->where('diff_address', 'yes');

            if (isset($request->surveyer_constituency) && !empty($request->surveyer_constituency)) {
                $query->whereHas('surveyerConstituency', function($q) use ($request) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->surveyer_constituency) . '%']);
                });
            } 

            // Search in livingConstituency
            if (isset($request->living_constituency) && !empty($request->living_constituency)) {
                $query->whereHas('livingConstituency', function($q) use ($request) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->living_constituency) . '%']);
                });
            }
        
            // Add search functionality
            // Apply individual search filters

            if ($request->has('start_date')) {
                $query->where('created_at', '>=', $request->start_date . ' 00:00:00');
            }

            if ($request->has('end_date')) {
                $query->where('created_at', '<=', $request->end_date . ' 23:59:59'); 
            } 

            if (isset($request->under_age_25) && $request->input('under_age_25') === 'yes') {
                $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, CAST(unregistered_voters.date_of_birth AS DATE))) < 25');
            }
            
            if (isset($request->first_name) && !empty($request->first_name)) {
                $query->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->first_name) . '%']);
            }

            if (isset($request->last_name) && !empty($request->last_name)) {
                $query->whereRaw('LOWER(last_name) LIKE ?', ['%' . strtolower($request->last_name) . '%']); 
            }
            
            if (isset($request->phone_number) && !empty($request->phone_number)) {
                $query->where('phone_number', 'LIKE', '%' . $request->phone_number . '%');
            }

            if (isset($request->new_email) && !empty($request->new_email)) {
                $query->whereRaw('LOWER(new_email) LIKE ?', ['%' . strtolower($request->new_email) . '%']);
            }

            if (isset($request->new_address) && !empty($request->new_address)) {
                $query->whereRaw('LOWER(new_address) LIKE ?', ['%' . strtolower($request->new_address) . '%']);
            }

            if (isset($request->survey_id) && !empty($request->survey_id)) {
                $query->where('survey_id', 'LIKE', '%' . $request->survey_id . '%');
            }

            if (isset($request->user_id) && !empty($request->user_id)) {
                $query->where('user_id', 'LIKE', '%' . $request->user_id . '%');
            }

            if (isset($request->voter_id) && !empty($request->voter_id)) {
                $query->where('voter_id', 'LIKE', '%' . $request->voter_id . '%');
            }

            // Search in related voter fields
            if (isset($request->voter_first_name) && !empty($request->voter_first_name)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->voter_first_name) . '%']);
                });
            }

            if (isset($request->voter_second_name) && !empty($request->voter_second_name)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(second_name) LIKE ?', ['%' . strtolower($request->voter_second_name) . '%']);
                });
            }

            if (isset($request->voter_number) && !empty($request->voter_number)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->where('voter', 'LIKE', '%' . $request->voter_number . '%');
                });
            }

            if (isset($request->voter_address) && !empty($request->voter_address)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(address) LIKE ?', ['%' . strtolower($request->voter_address) . '%']);
                });
            }

            // Add filters
            if (isset($request->gender) && !empty($request->gender)) {
                $query->whereRaw('LOWER(gender) = ?', [strtolower($request->gender)]);
            }

            if (isset($request->date_from) && !empty($request->date_from) && isset($request->date_to) && !empty($request->date_to)) {
                $query->whereBetween('date_of_birth', [$request->date_from, $request->date_to]);
            }

            // Add constituency filters
            // if (isset($request->constituency_id) && !empty($request->constituency_id)) {
            //     $query->where('constituencies.id', $request->constituency_id);
            // } 

            // if (isset($request->constituency_name) && !empty($request->constituency_name)) {
            //     $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($request->constituency_name) . '%']);
            // } 

            


            // Add sorting
            $query->orderBy('id', 'desc');

            // Get paginated results
            $unregisteredVoters = $query->paginate($perPage);

            // Build search parameters object
            $searchParams = [
                'first_name' => $request->first_name ?? null,
                'last_name' => $request->last_name ?? null,
                'phone_number' => $request->phone_number ?? null,
                'new_email' => $request->new_email ?? null,
                'new_address' => $request->new_address ?? null,
                'survey_id' => $request->survey_id ?? null,
                'user_id' => $request->user_id ?? null,
                'voter_id' => $request->voter_id ?? null,
                'voter_first_name' => $request->search ?? null,
                'voter_second_name' => $request->search ?? null,
                'voter_number' => $request->search ?? null,
                'voter_address' => $request->search ?? null,
                'under_age_25' => $request->under_age_25 ?? null
            ];

            return response()->json([
                'success' => true,
                'data' => $unregisteredVoters,
                'search_params' => $searchParams,
                'counts' => [
                    'total_unregistered' => $totalUnregistered,
                    'total_male' => $totalMale,
                    'total_female' => $totalFemale,
                    'total_contacted' => $totalContacted,
                    'total_uncontacted' => $totalUncontacted
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving unregistered voters',
                'error' => $e->getMessage()
            ], 500);
        }
    } 

    public function show($id)
    {
        try {
            $unregisteredVoter = UnregisteredVoter::with(['voter' => function($query) {
                $query->select('id', 'voter', 'first_name', 'second_name', 'address', 'pobse', 'const','surname');
            }])->find($id); 

            if (!$unregisteredVoter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unregistered voter not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $unregisteredVoter
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving unregistered voter',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try { 
            $unregisteredVoter = UnregisteredVoter::find($id);

            if (!$unregisteredVoter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unregistered voter not found'
                ], 404);
            }

            // Validate the request data
            $validatedData = $request->validate([
             
                'first_name' => 'sometimes|string|max:255',
                'last_name' => 'sometimes|string|max:255',
                'phone_number' => 'sometimes|string|max:20',
                'new_email' => 'sometimes|email|max:255',
                'new_address' => 'sometimes|string|max:500',
                'gender' => 'sometimes|in:male,female,other',
                // 'date_of_birth' => 'sometimes|date',
                'note' => 'nullable|sometimes|string',
            ]); 

            if($request->has('diff_address')){
                $validatedData['diff_address'] = $request->input('diff_address');
            }
            if($request->has('living_constituency')){
                $validatedData['living_constituency'] = $request->input('living_constituency');
            }
 
            // Update the unregistered voter
            $unregisteredVoter->update($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Unregistered voter updated successfully',
                'data' => $unregisteredVoter
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating unregistered voter',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $unregisteredVoter = UnregisteredVoter::find($id);

            if (!$unregisteredVoter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unregistered voter not found'
                ], 404);
            }

            $unregisteredVoter->delete();

            return response()->json([
                'success' => true,
                'message' => 'Unregistered voter deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting unregistered voter',
                'error' => $e->getMessage()
            ], 500); 
        }
    }



    // ... existing code ...

    public function updatecontacted(Request $request, $id)
    {
        try {
            $unregisteredVoter = UnregisteredVoter::with('voter')->find($id);

            if (!$unregisteredVoter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unregistered voter not found'
                ], 404);
            }

            // Validate the request data
            $validatedData = $request->validate([
                'note' => 'required|string|max:1000',
            ]);

            // Increment contacted count and update note
            $unregisteredVoter->contacted = 1;
            $unregisteredVoter->note = $validatedData['note'];
            $unregisteredVoter->save();

            return response()->json([
                'success' => true,
                'message' => 'User Contacted updated successfully',
                'data' => [
                    'id' => $unregisteredVoter->id,
                    'contacted' => $unregisteredVoter->contacted,
                    'note' => $unregisteredVoter->note
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating connection',
                'error' => $e->getMessage()
            ], 500);
        }
    }


     
    public function get_contacted_voters(Request $request)
    {  
        try {
            $user = Auth::user();
            $perPage = $request->input('per_page', 20);

            $query = UnregisteredVoter::with([
                'voter' => function($query) {
                    $query->select('id', 'voter', 'first_name', 'second_name', 'address', 'pobse', 'const','surname');
                },
                'surveyerConstituency:id,name',
                'livingConstituency:id,name',
                'voterNotes' => function($query) {
                    $query->select('id', 'unregistered_voter_id', 'note');
                }
            ])
            // ->where('user_id', $user->id)
            ->where('contacted', true)
            ->whereHas('voterNotes', function($query) {
                $query->whereColumn('unregistered_voter_id', 'unregistered_voters.id');
            });

             
            $searchParams = [ 
              
                'first_name' => $request->first_name ?? null,
                'last_name' => $request->last_name ?? null,
                'phone_number' => $request->phone_number ?? null,
                'new_email' => $request->new_email ?? null,
                'new_address' => $request->new_address ?? null,
                'survey_id' => $request->survey_id ?? null,
                'user_id' => $request->user_id ?? null,
                'voter_id' => $request->voter_id ?? null,
                'under_age_25' => $request->under_age_25 ?? null
            ];

            if (isset($request->surveyer_constituency) && !empty($request->surveyer_constituency)) {
                $query->whereHas('surveyerConstituency', function($q) use ($request) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->surveyer_constituency) . '%']);
                });
            } 

            // Search in livingConstituency
            if (isset($request->living_constituency) && !empty($request->living_constituency)) {
                $query->whereHas('livingConstituency', function($q) use ($request) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->living_constituency) . '%']);
                });
            }
            
            $underAge25 = $request->input('under_age_25');
            if (isset($request->under_age_25) && $request->input('under_age_25') === 'yes') {
                $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, CAST(unregistered_voters.date_of_birth AS DATE))) < 25');
            }
            if (isset($request->start_date) && !empty($request->start_date)) {
                $query->where('created_at', '>=', $request->start_date . ' 00:00:00');
            }

            if (isset($request->end_date) && !empty($request->end_date)) {
                $query->where('created_at', '<=', $request->end_date . ' 23:59:59');
            }


            if (isset($request->first_name) && !empty($request->first_name)) {
                $query->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->first_name) . '%']);
            }
            if (isset($request->last_name) && !empty($request->last_name)) {
                $query->whereRaw('LOWER(last_name) LIKE ?', ['%' . strtolower($request->last_name) . '%']); 
            } 
             

            if (isset($request->phone_number) && !empty($request->phone_number)) {
                $query->where('phone_number', 'LIKE', '%' . $request->phone_number . '%');
            }

            if (isset($request->new_email) && !empty($request->new_email)) {
                $query->whereRaw('LOWER(new_email) LIKE ?', ['%' . strtolower($request->new_email) . '%']);
            }

            if (isset($request->new_address) && !empty($request->new_address)) {
                $query->whereRaw('LOWER(new_address) LIKE ?', ['%' . strtolower($request->new_address) . '%']);
            }

            if (isset($request->survey_id) && !empty($request->survey_id)) {
                $query->where('survey_id', 'LIKE', '%' . $request->survey_id . '%');
            }

            if (isset($request->user_id) && !empty($request->user_id)) {
                $query->where('user_id', 'LIKE', '%' . $request->user_id . '%');
            }

            if (isset($request->voter_id) && !empty($request->voter_id)) {
                $query->where('voter_id', 'LIKE', '%' . $request->voter_id . '%');
            }

            // Search in related voter fields
            if (isset($request->voter_first_name) && !empty($request->voter_first_name)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->voter_first_name) . '%']);
                });
            }

            if (isset($request->voter_second_name) && !empty($request->voter_second_name)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(second_name) LIKE ?', ['%' . strtolower($request->voter_second_name) . '%']);
                });
            }

            if (isset($request->voter_number) && !empty($request->voter_number)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->where('voter', 'LIKE', '%' . $request->voter_number . '%');
                });
            }

            if (isset($request->voter_address) && !empty($request->voter_address)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(address) LIKE ?', ['%' . strtolower($request->voter_address) . '%']);
                });
            }

            // Add filters
            if (isset($request->gender) && !empty($request->gender)) {
                $query->whereRaw('LOWER(gender) = ?', [strtolower($request->gender)]);
            }

            if (isset($request->date_from) && !empty($request->date_from) && isset($request->date_to) && !empty($request->date_to)) {
                $query->whereBetween('date_of_birth', [$request->date_from, $request->date_to]);
            }

            // Add sorting
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy('updated_at', 'desc');

            $contactedVoters = $query->paginate($perPage);

            // Build search parameters object
            

            return response()->json([
                'success' => true,
                'data' => $contactedVoters,
                'search_params' => $searchParams
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving contacted voters',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // public function get_uncontacted_voters(Request $request)
    // { 
         
    //     try {
          
    //         $perPage = $request->input('per_page', 20);

    //         $query = UnregisteredVoter::with(['voter' => function($query) {
    //             $query->select('id', 'voter', 'first_name', 'second_name', 'address', 'pobse', 'const','surname');
    //         }])->where('contacted', false); // Changed from 0 to false for PostgreSQL boolean

    //         // Add search filters
    //             if ($request->has('first_name')) {
    //                 $query->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->first_name) . '%']);
    //             }
    //             if ($request->has('last_name')) {
    //                 $query->whereRaw('LOWER(last_name) LIKE ?', ['%' . strtolower($request->last_name) . '%']);
    //             }

    //         if ($request->has('phone_number')) {
    //             $query->where('phone_number', 'LIKE', '%' . $request->phone_number . '%');
    //         }

    //         if ($request->has('new_email')) {
    //             $query->whereRaw('LOWER(new_email) LIKE ?', ['%' . strtolower($request->new_email) . '%']);
    //         }
            
    //         // Fixed voter_id search to use correct column name 'voter'
    //         if ($request->has('voter_id') && !empty($request->voter_id)) {
    //             $query->whereHas('voter', function($q) use ($request) {
    //                 $q->where('voter', $request->voter_id);
    //             });
    //         }

    //         if ($request->has('new_address')) {
    //             $query->whereRaw('LOWER(new_address) LIKE ?', ['%' . strtolower($request->new_address) . '%']);
    //         }

    //         if ($request->has('survey_id')) {
    //             $query->where('survey_id', 'LIKE', '%' . $request->survey_id . '%');
    //         }

    //         // Search in related voter fields
    //         if ($request->has('voter_first_name')) {
    //             $query->whereHas('voter', function($q) use ($request) {
    //                 $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->voter_first_name) . '%']);
    //             });
    //         }

    //         if ($request->has('voter_second_name')) {
    //             $query->whereHas('voter', function($q) use ($request) {
    //                 $q->whereRaw('LOWER(second_name) LIKE ?', ['%' . strtolower($request->voter_second_name) . '%']);
    //             });
    //         }

    //         if ($request->has('voter_number')) {
    //             $query->whereHas('voter', function($q) use ($request) {
    //                 $q->where('voter', 'LIKE', '%' . $request->voter_number . '%');
    //             });
    //         }

    //         if ($request->has('voter_address')) {
    //             $query->whereHas('voter', function($q) use ($request) {
    //                 $q->whereRaw('LOWER(address) LIKE ?', ['%' . strtolower($request->voter_address) . '%']);
    //             });
    //         }

    //         // Add filters
    //         if ($request->has('gender')) {
    //             $query->whereRaw('LOWER(gender) = ?', [strtolower($request->gender)]);
    //         }

    //         if ($request->has('date_from') && $request->has('date_to')) {
    //             $query->whereBetween('date_of_birth', [$request->date_from, $request->date_to]);
    //         }

    //         $uncontactedVoters = $query->orderBy('id', 'desc')->paginate($perPage);

    //         // Build search parameters object
    //         $searchParams = [
    //             'first_name' => $request->first_name ?? null,
    //             'last_name' => $request->last_name ?? null,
    //             'phone_number' => $request->phone_number ?? null,
    //             'new_email' => $request->new_email ?? null,
    //             'new_address' => $request->new_address ?? null,
    //             'survey_id' => $request->survey_id ?? null,
    //             'voter_first_name' => $request->voter_first_name ?? null,
    //             'voter_second_name' => $request->voter_second_name ?? null,
    //             'voter_number' => $request->voter_number ?? null,
    //             'voter_address' => $request->voter_address ?? null,
    //             'gender' => $request->gender ?? null,
    //             'date_from' => $request->date_from ?? null,
    //             'date_to' => $request->date_to ?? null,
    //             'voter_id' => $request->voter_id ?? null
    //         ];

    //         return response()->json([
    //             'success' => true,
    //             'data' => $uncontactedVoters,
    //             'search_params' => $searchParams
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error retrieving uncontacted voters',
    //             'error' => $e->getMessage()
    //         ], 500); 
    //     } 
    // }

    public function get_uncontacted_voters(Request $request)
    {
        try { 
            $user = Auth::user();
            $perPage = $request->input('per_page', 20);

            // $query = UnregisteredVoter::with(['voter' => function($query) {
            //     $query->select('id', 'voter', 'first_name', 'second_name', 'address', 'pobse', 'const','surname');
            // }])
            // // ->where('user_id', $user->id)
            // ->where('contacted', false);

            $query = UnregisteredVoter::with([
                'voter' => function($query) {
                    $query->select('id', 'voter', 'first_name', 'second_name', 'address', 'pobse', 'const','surname');
                },
                'surveyerConstituency:id,name',
                'livingConstituency:id,name',
                'voterNotes' => function($query) {
                    $query->select('id', 'unregistered_voter_id', 'note');
                }
            ])
            // ->where('user_id', $user->id)
            ->where('contacted', false);
            // Add search filters

            $searchParams = [
               
                'first_name' => $request->first_name ?? null,
                'last_name' => $request->last_name ?? null,
                'phone_number' => $request->phone_number ?? null,
                'new_email' => $request->new_email ?? null,
                'new_address' => $request->new_address ?? null,
                'survey_id' => $request->survey_id ?? null,
                'user_id' => $request->user_id ?? null,
                'voter_id' => $request->voter_id ?? null,
                'under_age_25' => $request->under_age_25 ?? null
            ];

            
            if (isset($request->surveyer_constituency) && !empty($request->surveyer_constituency)) {
                $query->whereHas('surveyerConstituency', function($q) use ($request) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->surveyer_constituency) . '%']);
                });
            } 

            // Search in livingConstituency
            if (isset($request->living_constituency) && !empty($request->living_constituency)) {
                $query->whereHas('livingConstituency', function($q) use ($request) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->living_constituency) . '%']);
                });
            }
            
            if (isset($request->under_age_25) && $request->input('under_age_25') === 'yes') {
                $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, CAST(unregistered_voters.date_of_birth AS DATE))) < 25');
            }

            if (isset($request->start_date) && !empty($request->start_date)) {
                $query->where('created_at', '>=', $request->start_date . ' 00:00:00');
            }

            if (isset($request->end_date) && !empty($request->end_date)) {
                $query->where('created_at', '<=', $request->end_date . ' 23:59:59');
            }
            
            if (isset($request->first_name) && !empty($request->first_name)) {
                $query->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->first_name) . '%']);
            }
            if (isset($request->last_name) && !empty($request->last_name)) {
                $query->whereRaw('LOWER(last_name) LIKE ?', ['%' . strtolower($request->last_name) . '%']); 
            } 

            if (isset($request->phone_number) && !empty($request->phone_number)) {
                $query->where('phone_number', 'LIKE', '%' . $request->phone_number . '%');
            }

            if (isset($request->new_email) && !empty($request->new_email)) {
                $query->whereRaw('LOWER(new_email) LIKE ?', ['%' . strtolower($request->new_email) . '%']);
            }

            if (isset($request->new_address) && !empty($request->new_address)) {
                $query->whereRaw('LOWER(new_address) LIKE ?', ['%' . strtolower($request->new_address) . '%']);
            }

            if (isset($request->survey_id) && !empty($request->survey_id)) {
                $query->where('survey_id', 'LIKE', '%' . $request->survey_id . '%');
            }

            if (isset($request->user_id) && !empty($request->user_id)) {
                $query->where('user_id', $request->user_id);
            }

            if (isset($request->voter_id) && !empty($request->voter_id)) {
                $query->where('voter_id', 'LIKE', '%' . $request->voter_id . '%');
            }

            // Search in related voter fields
            if (isset($request->voter_first_name) && !empty($request->voter_first_name)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->voter_first_name) . '%']);
                });
            }

            if (isset($request->voter_second_name) && !empty($request->voter_second_name)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(second_name) LIKE ?', ['%' . strtolower($request->voter_second_name) . '%']);
                });
            }

            if (isset($request->voter_number) && !empty($request->voter_number)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->where('voter', 'LIKE', '%' . $request->voter_number . '%');
                });
            }

            if (isset($request->voter_address) && !empty($request->voter_address)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(address) LIKE ?', ['%' . strtolower($request->voter_address) . '%']);
                });
            }

            // Add filters
            if (isset($request->gender) && !empty($request->gender)) {
                $query->whereRaw('LOWER(gender) = ?', [strtolower($request->gender)]);
            }

            if (isset($request->date_from) && !empty($request->date_from) && isset($request->date_to) && !empty($request->date_to)) {
                $query->whereBetween('date_of_birth', [$request->date_from, $request->date_to]);
            }

            // Add sorting
            $sortField = $request->get('sort_by', 'id');
            $sortDirection = $request->get('sort_direction', 'desc');
            $allowedSortFields = ['id', 'name', 'date_of_birth', 'gender', 'created_at', 'survey_id', 'user_id', 'voter_id'];
            
            // if (in_array($sortField, $allowedSortFields)) {
            //     $query->orderBy($sortField, $sortDirection);
            // }
            $query->orderBy('id', 'desc');
            $uncontactedVoters = $query->paginate($perPage);

            // Build search parameters object
             

            return response()->json([
                'success' => true,
                'data' => $uncontactedVoters,
                'search_params' => $searchParams
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving uncontacted voters',
                'error' => $e->getMessage()
            ], 500);
        }
    } 



}
