<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\UnregisteredVoter;
use App\Models\Voter;
use App\Models\VoterNote; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class UnregisteredVoterController extends Controller
{

    public function unregister_voter_store(Request $request)
    {
        try {


            $createdVoters = [];
            $user = Auth::user();
            $errors = [];


            if($request->is_died){



                $unregisteredVoter = UnregisteredVoter::create([
                     
                    'is_died' => 1,
                    'died_date' => $request->died_date,
                    'voter_id' => isset($voterData['voter_id']) ? $voterData['voter_id'] : null,
                    'user_id' => $user->id
                ]); 


                return response()->json([
                    'success' => true,
                    'message' => 'Unregistered voters created successfully',
                    'data' => $createdVoters
                ], 201);


            }else{


                  // Validate the request data
            $validator = \Validator::make($request->voters, [
           
                '*.first_name' => 'required|string|max:255',
                '*.last_name' => 'required|string|max:255',
                // '*.dob' => 'required|date',
                '*.gender' => 'required|in:male,female,other',
                '*.address' => 'required|string',
                // '*.email' => 'nullable|email|max:255',
                '*.phone' => 'required|string|max:20',
                '*.party' => 'string', 
                '*.voter_id' => 'nullable|string'
            ]);   

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            } 

          
            foreach ($request->voters as $index => $voterData) {



                    if($voterData['is_national'] == 'true'){

    
                        $validator = Validator::make($voterData, [
                            // 'name' => [
                            //     'required', 'string', 'max:255',
                            //     Rule::unique('unregistered_voters')->where(fn ($query) => 
                            //         $query->where('date_of_birth', $voterData['dob'])
                            //     )
                            // ],
                            'first_name' => 'required|string|max:255',
                            'last_name' => 'required|string|max:255',
                            //'dob' => 'required|date',
                            'gender' => 'required|in:male,female,other',
                            'address' => 'required|string',
                            // 'email' => [
                            //     'nullable', 'email', 'max:255',
                            //     Rule::unique('unregistered_voters', 'new_email')
                            // ],
                            'phone' => 'required|string|max:20',
                            'party' => 'nullable|string',
                            'voter_id' => 'nullable|string'
                        ]); 
                    
                        if ($validator->fails()) {
                            $errors[$index] = $validator->errors();
                            continue; // Skip this iteration and move to the next voter
                        }
                        
                        $getVoter = Voter::where('voter', $voterData['voter_id'])->first();
                         

                        if ($getVoter) {
                            // Update the voter with new details from $voterData
                            $getVoter->first_name = $voterData['first_name'] ?? null;
                            $getVoter->second_name = $voterData['last_name'] ?? null;
                            $getVoter->gender = $voterData['gender'] ?? null;
                            $getVoter->address = $voterData['address'] ?? null;
                            $getVoter->phone_number = $voterData['phone'] ?? null;
                            $getVoter->voter_voting_for = $voterData['party'] ?? null;
                            $getVoter->living_constituency = $voterData['living_constituency'] ?? null;
                            $getVoter->surveyer_constituency = $voterData['surveyer_constituency'] ?? null;
                            $getVoter->note = $voterData['note'] ?? null;
                            $getVoter->email = $voterData['email'] ?? null;
                            $getVoter->is_national =  1;
                            $getVoter->user_id  = $user->id;
                            $getVoter->save();
                           
                             
                        }
                        
                        $createdVoters[] = $getVoter;

                    }else{



                        $validator = Validator::make($voterData, [
                            // 'name' => [
                            //     'required', 'string', 'max:255',
                            //     Rule::unique('unregistered_voters')->where(fn ($query) => 
                            //         $query->where('date_of_birth', $voterData['dob'])
                            //     )
                            // ],
                            'first_name' => 'required|string|max:255',
                            'last_name' => 'required|string|max:255',
                            //'dob' => 'required|date',
                            'gender' => 'required|in:male,female,other',
                            'address' => 'required|string',
                            // 'email' => [
                            //     'nullable', 'email', 'max:255',
                            //     Rule::unique('unregistered_voters', 'new_email')
                            // ],
                            'phone' => 'required|string|max:20',
                            'party' => 'nullable|string',
                            'voter_id' => 'nullable|string'
                        ]); 
                    
                        if ($validator->fails()) {
                            // Check specifically for email error
                            if ($validator->errors()->has('email')) {
                                $errors[$index] = [
                                    'email' => 'The email "' . $voterData['email'] . '" is already taken.',
                                    'all_errors' => $validator->errors()
                                ];
                            } else {
                                $errors[$index] = $validator->errors();
                            }
                            continue; // Skip this iteration and move to the next voter
                        }
        
        
                        $unregisteredVoter = UnregisteredVoter::create([
                            
                            'first_name' => $voterData['first_name'],
                            'last_name' => $voterData['last_name'],
                            'date_of_birth' => $voterData['dob'] ?? null,
                            'gender' => $voterData['gender'], 
                            'new_address' => $voterData['address'],
                            'new_email' => $voterData['email'] ?? null,
                            'phone_number' => $voterData['phone'],
                            'party' => $voterData['party'] ?? null,
                            'note' => $voterData['note'] ?? null,
                            'voter_id' => isset($voterData['voter_id']) ? $voterData['voter_id'] : null,
                            'user_id' => $user->id,
                            'diff_address' => $voterData['diff_address'] ?? null,
                            'living_constituency' => $voterData['living_constituency'] ?? null, 
                            'surveyer_constituency' => $voterData['surveyer_constituency'] ?? null, 
                        ]); 
        
                        $createdVoters[] = $unregisteredVoter;



                    }


                
            }

            if (!empty($errors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $errors,
                    'error_type' => 'validation'
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Unregistered voters created successfully',
                'data' => $createdVoters
            ], 201);

            }
          

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating unregistered voters',
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
            ->leftJoin('constituencies', 'unregistered_voters.living_constituency', '=', 'constituencies.id')
            ->select('unregistered_voters.*', 'constituencies.name as living_constituency_name');

            $query->where('diff_address', 'yes');   

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
            if (isset($request->constituency_id) && !empty($request->constituency_id)) {
                $query->where('constituencies.id', $request->constituency_id);
            } 

            if (isset($request->constituency_name) && !empty($request->constituency_name)) {
                $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($request->constituency_name) . '%']);
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
    public function index(Request $request)
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

            $query = UnregisteredVoter::with([
                'voter' => function($query) {
                    $query->select('id', 'voter', 'first_name', 'second_name', 'address', 'pobse', 'const','surname');
                },
                'surveyerConstituency:id,name',
                'livingConstituency:id,name'
            ]); 

            // Add date range filter for created_at
            $underAge25 = $request->input('under_age_25');
            if (isset($underAge25) && $underAge25=== 'yes') {
                $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, CAST(date_of_birth AS DATE))) < 25');
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

            // Filter by birth date
            if (isset($request->birth_date) && !empty($request->birth_date)) {
                $query->whereDate('date_of_birth', $request->birth_date);
            }

            // Search in surveyerConstituency
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
            
            $query->orderBy('id', 'desc');
            // Get paginated results
            $unregisteredVoters = $query->paginate($perPage); 

            // Build search parameters object
            $searchParams = [
                'first_name' => $request->search ?? null,
                'last_name' => $request->search ?? null,
                'phone_number' => $request->search ?? null,
                'new_email' => $request->search ?? null,
                'new_address' => $request->search ?? null,
                'survey_id' => $request->search ?? null,
                'user_id' => $request->search ?? null,
                'voter_id' => $request->search ?? null,
                'voter_first_name' => $request->search ?? null,
                'voter_second_name' => $request->search ?? null,
                'voter_number' => $request->search ?? null,
                'voter_address' => $request->search ?? null,
                'start_date' => $request->start_date ?? null,
                'end_date' => $request->end_date ?? null,
                'birth_date' => $request->birth_date ?? null,
                'surveyer_constituency' => $request->surveyer_constituency ?? null,
                'living_constituency' => $request->living_constituency ?? null
            ];

            return response()->json([
                'success' => true,
                'data' => $unregisteredVoters,
                'search_params' => $searchParams
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving unregistered voters',
                'error' => $e->getMessage()
            ], 500);
        }
    }  


    // ... existing code ...

    public function show($id)
    {
        try {
            $user = Auth::user();
            
            if (empty($user->constituency_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No constituency assigned to user'
                ], 403);
            }

            $constituency_ids = explode(',', $user->constituency_id);
            
            $unregisteredVoter = UnregisteredVoter::with(['voter' => function($query) {
                $query->select('id', 'voter', 'first_name', 'second_name', 'address', 'pobse', 'const');
            }])
            // ->where('user_id', $user->id)
            // ->whereHas('voter', function($query) use ($constituency_ids) {
            //     $query->whereIn('const', $constituency_ids);
            // })
            ->find($id); 

            if (!$unregisteredVoter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unregistered voter not found or not authorized'
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


    // ... existing code ...

    public function update(Request $request, $id)
    {
        
        try { 
            $user = Auth::user();
            
            if (empty($user->constituency_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No constituency assigned to user'
                ], 403);
            }

            $constituency_ids = explode(',', $user->constituency_id);
            
            $unregisteredVoter = UnregisteredVoter::with('voter')
                // ->where('user_id', $user->id)
                // ->whereHas('voter', function($query) use ($constituency_ids) {
                //     $query->whereIn('const', $constituency_ids);
                // })
                ->find($id);
 
            if (!$unregisteredVoter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unregistered voter not found or not authorized'
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
                'date_of_birth' => 'sometimes|date',
                'note' => 'nullable|sometimes|string|max:1000',
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
            $user = Auth::user();
            
            if (empty($user->constituency_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No constituency assigned to user'
                ], 403);
            }

            $constituency_ids = explode(',', $user->constituency_id);
            
            $unregisteredVoter = UnregisteredVoter::with('voter')
                // ->where('user_id', $user->id)
                // ->whereHas('voter', function($query) use ($constituency_ids) {
                //     $query->whereIn('const', $constituency_ids);
                // })
                ->find($id);

            if (!$unregisteredVoter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unregistered voter not found or not authorized'
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

    public function updateContacted(Request $request, $id)
    {
        try {
            $user = Auth::user();
            
            if (empty($user->constituency_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No constituency assigned to user'
                ], 403);
            }

            // $constituency_ids = explode(',', $user->constituency_id);
            
            $unregisteredVoter = UnregisteredVoter::with('voter')
                ->where('user_id', $user->id)
                // ->whereHas('voter', function($query) use ($constituency_ids) {
                //     $query->whereIn('const', $constituency_ids);
                // })
                ->find($id);

            if (!$unregisteredVoter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unregistered voter not found or not authorized'
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
                'message' => 'Error updating User Contacted',
                'error' => $e->getMessage()
            ], 500);
        }
    } 


    // ... existing code ...

    public function get_contacted_voters(Request $request)
    { 
        try {
            $user = Auth::user();
            $perPage = $request->input('per_page', 20);

            $query = UnregisteredVoter::with(['voter' => function($query) {
                $query->select('id', 'voter', 'first_name', 'second_name', 'address', 'pobse', 'const','surname');
            }, 'voterNotes' => function($query) {
                $query->select('id', 'unregistered_voter_id', 'note'); 
            }])
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
                'start_date' => $request->start_date ?? null,
                'end_date' => $request->end_date ?? null,
                'under_age_25' => $request->under_age_25 ?? null,
            ];

            $underAge25 = $request->input('under_age_25');
            if (isset($underAge25) && $underAge25=== 'yes') {
                $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, CAST(date_of_birth AS DATE))) < 25');
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

    public function get_uncontacted_voters(Request $request)
    {
        try {
            $user = Auth::user();
            $perPage = $request->input('per_page', 20);

            $query = UnregisteredVoter::with(['voter' => function($query) {
                $query->select('id', 'voter', 'first_name', 'second_name', 'address', 'pobse', 'const','surname');
            }])
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
                'start_date' => $request->start_date ?? null,
                'end_date' => $request->end_date ?? null,
                'under_age_25' => $request->under_age_25 ?? null,
            ];
            
            $underAge25 = $request->input('under_age_25');
            if (isset($underAge25) && $underAge25=== 'yes') {
                $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, CAST(date_of_birth AS DATE))) < 25');
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

    public function updateDiffAddress(Request $request){
        try{
            $user = Auth::user();
            $unregisteredVoter = UnregisteredVoter::find($request->id);
            $unregisteredVoter->diff_address = $request->diff_address;
            $unregisteredVoter->living_constituency = $request->living_constituency;
            $unregisteredVoter->save();
            return response()->json([
                'success' => true,
                'message' => 'Different address updated successfully',
                'data' => $unregisteredVoter
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating diff address',
                'error' => $e->getMessage()
            ], 500);
        }

    } 
}
