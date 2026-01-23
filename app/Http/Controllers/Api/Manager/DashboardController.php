<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Survey;
use App\Models\Voter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Models\Constituency;
use Illuminate\Support\Facades\DB;
class DashboardController extends Controller
{
    /**
     * Get user profile by ID
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function getProfile()
    {
        try {
            $user = Auth::user();
            
            // Get all constituencies
            $constituencies = Constituency::all();
            
            // Add image path if image exists
           
            return response()->json([
                'success' => true,
                'message' => 'User profile retrieved successfully',
                'data' => [
                    'user' => $user,
                     
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'error' => $e->getMessage()
            ], Response::HTTP_NOT_FOUND);
        }
    }

 // ... existing code ...

    /**
     * Update user profile
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function updateProfile(Request $request) 
    {
        try { 
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], Response::HTTP_NOT_FOUND);
            }
            
            try {
                // Check if email already exists for another user
                $existingUser = User::where('email', $request->email)
                    ->where('id', '!=', $user->id)
                    ->first();
                    
                if ($existingUser) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Email already exists'
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                // Basic validation for profile fields
                $request->validate([
                    'name' => 'required|string|max:255',
                    'email' => 'required|email',
                    'phone' => 'nullable|string|max:20', 
                    'address' => 'nullable|string|max:255',
                    'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                    'password' => 'nullable|min:6'
                ]);
            } catch (\Illuminate\Validation\ValidationException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
    
            $input = $request->only(['name', 'email', 'phone', 'address']);
    
            // Handle image upload if provided
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($user->image) {
                    Storage::disk('public')->delete('users/' . $user->image);
                }
    
                // Store new image
                $imagePath = $request->file('image')->store('users', 'public');
                $input['image'] = $imagePath;
            }  
    
            // Handle password update if provided
            if ($request->filled('password')) {
                $input['password'] = Hash::make($request->password);
            }
    
            $user->update($input);
    
            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $user
            ], Response::HTTP_OK);
    
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }




    public function stats(Request $request)
    {

         
            $surveyor = User::where('id', Auth::user()->id)->first();
            $existsInDatabase = $request->input('exists_in_database');

            $registeredQuery = Voter::whereIn('const', explode(',', $surveyor->constituency_id));
            
            // Apply exists_in_database filter
            if ($existsInDatabase === 'true') {
                $registeredQuery->where('voters.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $registeredQuery->where('voters.exists_in_database', false);
            }
            
            $registered = $registeredQuery->count();
            
            
            $fnm = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
            ->join(DB::raw("(
                SELECT DISTINCT ON (voter_id) id
                FROM surveys
                ORDER BY voter_id, created_at DESC
            ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
            ->where(function($query) {
                $query->where('surveys.voting_for', 'FNM')
                    ->orWhere('surveys.voting_for', 'Free National Movement');
            })
            ->whereIn('voters.const', explode(',', $surveyor->constituency_id));
                
            // Apply exists_in_database filter
            if ($existsInDatabase === 'true') {
                $fnm->where('voters.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $fnm->where('voters.exists_in_database', false);
            }
            $fnm = $fnm->count();
    
            $coi =  Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
            ->join(DB::raw("(
                SELECT DISTINCT ON (voter_id) id
                FROM surveys
                ORDER BY voter_id, created_at DESC
            ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
            ->where(function($query) {
                $query->where('voting_for', 'COI')
                    ->orWhere('voting_for', 'Coalition of Independents');
            })
            ->whereIn('voters.const', explode(',', $surveyor->constituency_id));
            
            
    
            // Apply exists_in_database filter
            if ($existsInDatabase === 'true') {
                $coi->where('voters.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $coi->where('voters.exists_in_database', false);
            }
            $coi = $coi->count();

            $plp = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
            ->join(DB::raw("(
                SELECT DISTINCT ON (voter_id) id
                FROM surveys
                ORDER BY voter_id, created_at DESC
            ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
            ->where(function($query) {
                $query->where('voting_for', 'PLP')
                ->orWhere('voting_for', 'Progressive Liberal Party');
            })
            ->whereIn('voters.const', explode(',', $surveyor->constituency_id));
        
            
            // Apply exists_in_database filter
            if ($existsInDatabase === 'true') {
                $plp->where('voters.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $plp->where('voters.exists_in_database', false);
            }
            $plp = $plp->count();

            $other = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
            ->join(DB::raw("(
                SELECT DISTINCT ON (voter_id) id
                FROM surveys
                ORDER BY voter_id, created_at DESC
            ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
            ->where('voting_for', 'Other')
            ->whereIn('voters.const', explode(',', $surveyor->constituency_id));
                
            // Apply exists_in_database filter
            if ($existsInDatabase === 'true') {
                $other->where('voters.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $other->where('voters.exists_in_database', false);
            }
            $other = $other->count();

            $other_parties = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
            ->join(DB::raw("(
                SELECT DISTINCT ON (voter_id) id
                FROM surveys
                ORDER BY voter_id, created_at DESC
            ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
                ->where(function($query) {
                    $query->whereNotIn('voting_for', ['FNM', 'Free National Movement', 'PLP', 'Progressive Liberal Party', 'COI', 'Coalition of Independents', 'Other'])
                        ->whereNotNull('voting_for');
                    
                }) 
                ->whereIn('voters.const', explode(',', $surveyor->constituency_id));
                
            // Apply exists_in_database filter
            if ($existsInDatabase === 'true') {
                $other_parties->where('voters.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $other_parties->where('voters.exists_in_database', false);
            }
            $other_parties = $other_parties->count();

        $total_unknown = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
        ->join(DB::raw("(
            SELECT DISTINCT ON (voter_id) id
            FROM surveys
            ORDER BY voter_id, created_at DESC
        ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
        ->whereNull('voting_for')->where('voting_decision','undecided')
        ->whereIn('voters.const', explode(',', $surveyor->constituency_id));
        
        // Apply exists_in_database filter
        if ($existsInDatabase === 'true') {
            $total_unknown->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $total_unknown->where('voters.exists_in_database', false);
        }
        $total_unknown = $total_unknown->count();



        $total_naver_vote = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
        ->join(DB::raw("(
            SELECT DISTINCT ON (voter_id) id
            FROM surveys
            ORDER BY voter_id, created_at DESC
        ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
        ->whereNull('voting_for')->where('voting_decision','no')
        ->whereIn('voters.const', explode(',', $surveyor->constituency_id));
    
    
            
        // Apply exists_in_database filter
        if ($existsInDatabase === 'true') {
            $total_naver_vote->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $total_naver_vote->where('voters.exists_in_database', false);
        }
        $total_naver_vote = $total_naver_vote->count();

        // Capture uncategorized surveys (voting_for is NULL and voting_decision is not 'undecided' or 'no')
        $uncategorized = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
        ->join(DB::raw("(
            SELECT DISTINCT ON (voter_id) id
            FROM surveys
            ORDER BY voter_id, created_at DESC
        ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
        ->whereNull('voting_for')
        ->where(function($query) {
            $query->whereNull('voting_decision')
                ->orWhereNotIn('voting_decision', ['undecided', 'no']);
        })
        ->whereIn('voters.const', explode(',', $surveyor->constituency_id));
        
        // Apply exists_in_database filter
        if ($existsInDatabase === 'true') {
            $uncategorized->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $uncategorized->where('voters.exists_in_database', false);
        }
        $uncategorized = $uncategorized->count();

        $firstTimeVotersQuery =   Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
        ->join(DB::raw("(
            SELECT DISTINCT ON (voter_id) id
            FROM surveys
            ORDER BY voter_id, created_at DESC
        ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
        ->whereRaw("EXTRACT(YEAR FROM AGE(CURRENT_DATE, dob::date)) <= 25")
        ->whereIn('voters.const', explode(',', $surveyor->constituency_id));   
        

        // Apply exists_in_database filter
        if ($existsInDatabase === 'true') {
            $firstTimeVotersQuery->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $firstTimeVotersQuery->where('voters.exists_in_database', false);
        }
        $first_time_voters = $firstTimeVotersQuery->count();         


    
            $total_surveys =   Voter::with('user') 
            ->select(
                'voters.*',
                'constituencies.name as constituency_name',
                'surveys.id as survey_id',
                'surveys.created_at as survey_date',
                'surveys.user_id',
                'surveys.located',
                'surveys.voting_decision',
                'surveys.voting_for',
                'surveys.is_died',
                'surveys.died_date',
                'surveys.work_phone_code',
                'surveys.work_phone',
                'surveys.cell_phone_code',
                'surveys.cell_phone',
                'surveys.email',
                'surveys.home_phone_code',
                'surveys.home_phone',
                'surveys.special_comments',
                'surveys.other_comments',

            )
            ->join('constituencies', 'voters.const', '=', 'constituencies.id')
            ->join(DB::raw("(
                SELECT DISTINCT ON (voter_id) * 
                FROM surveys 
                ORDER BY voter_id, created_at DESC
            ) as surveys"), 'voters.id', '=', 'surveys.voter_id')
            ->whereIn('voters.const', explode(',', auth()->user()->constituency_id));

            // Apply exists_in_database filter
            if ($existsInDatabase === 'true') {
                $total_surveys->where('voters.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $total_surveys->where('voters.exists_in_database', false);
            }
            $total_surveys = $total_surveys->count();
    
            
    
            $plus_amount = $fnm + $plp + $coi + $other + $other_parties + $total_unknown + $total_naver_vote + $uncategorized;

            $data = [    
                'registered' => $registered,
                'fnm' => $fnm,
                'plp' => $plp,
                'coi' => $coi,
                'other' => $other,
                'other_parties' => $other_parties,
                'total_unknown' => $total_unknown,
                'total_naver_vote' => $total_naver_vote,
                'uncategorized' => $uncategorized,
                'total_surveys' => $total_surveys,
                'first_time_voters' => $first_time_voters,
                'plus_amount' => $plus_amount
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]); 
 }

    

    // public function stats(Request $request)
    // {

         
    //     $surveyor = User::where('id', Auth::user()->id)->first();
    //     $existsInDatabase = $request->input('exists_in_database');

    //     $registeredQuery = Voter::whereIn('const', explode(',', $surveyor->constituency_id));
        
    //     // Apply exists_in_database filter
    //     if ($existsInDatabase === 'true') {
    //         $registeredQuery->where('voters.exists_in_database', true);
    //     } elseif ($existsInDatabase === 'false') {
    //         $registeredQuery->where('voters.exists_in_database', false);
    //     }
        
    //     $registered = $registeredQuery->count();
        
         
    //     $fnm = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
    //         ->where(function($query) {
    //             $query->where('voting_for', 'FNM')
    //                   ->orWhere('voting_for', 'Free National Movement');
    //         })
    //         ->whereIn('voters.const', explode(',', $surveyor->constituency_id));
            
    //     // Apply exists_in_database filter
    //     if ($existsInDatabase === 'true') {
    //         $fnm->where('voters.exists_in_database', true);
    //     } elseif ($existsInDatabase === 'false') {
    //         $fnm->where('voters.exists_in_database', false);
    //     }
    //     $fnm = $fnm->count();
 
    //     $coi = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
    //         ->where(function($query) {
    //             $query->where('voting_for', 'PLP')
    //                   ->orWhere('voting_for', 'Coalition of Independents');
    //         })
    //         ->whereIn('voters.const', explode(',', $surveyor->constituency_id));
            
    //     // Apply exists_in_database filter
    //     if ($existsInDatabase === 'true') {
    //         $coi->where('voters.exists_in_database', true);
    //     } elseif ($existsInDatabase === 'false') {
    //         $coi->where('voters.exists_in_database', false);
    //     }
    //     $coi = $coi->count();

    //     $plp = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
    //         ->where(function($query) {
    //             $query->where('voting_for', 'PLP')
    //                   ->orWhere('voting_for', 'Progressive Liberal Party');
    //         })
    //         ->whereIn('voters.const', explode(',', $surveyor->constituency_id));
            
    //     // Apply exists_in_database filter
    //     if ($existsInDatabase === 'true') {
    //         $plp->where('voters.exists_in_database', true);
    //     } elseif ($existsInDatabase === 'false') {
    //         $plp->where('voters.exists_in_database', false);
    //     }
    //     $plp = $plp->count();

    //     $other_parties = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
    //         ->where(function($query) {
    //             $query->whereNotIn('voting_for', ['FNM', 'Free National Movement', 'PLP', 'Progressive Liberal Party'])
    //                   ->whereNotNull('voting_for');
    //         })
    //         ->whereIn('voters.const', explode(',', $surveyor->constituency_id));
            
    //     // Apply exists_in_database filter
    //     if ($existsInDatabase === 'true') {
    //         $other_parties->where('voters.exists_in_database', true);
    //     } elseif ($existsInDatabase === 'false') {
    //         $other_parties->where('voters.exists_in_database', false);
    //     }
    //     $other_parties = $other_parties->count();

    //     $query = Survey::with('voter');
    //     $query->whereHas('voter', function($q) use ($existsInDatabase) {
    //         $q->whereIn('voters.const', explode(',', Auth::user()->constituency_id));
            
    //         // Apply exists_in_database filter
    //         if ($existsInDatabase === 'true') {
    //             $q->where('voters.exists_in_database', true);
    //         } elseif ($existsInDatabase === 'false') {
    //             $q->where('voters.exists_in_database', false);
    //         }
    //     });
  
    //     $total_surveys =   Voter::with('user') 
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
    //     ->whereIn('voters.const', explode(',', auth()->user()->constituency_id));

    //     // Apply exists_in_database filter
    //     if ($existsInDatabase === 'true') {
    //         $total_surveys->where('voters.exists_in_database', true);
    //     } elseif ($existsInDatabase === 'false') {
    //         $total_surveys->where('voters.exists_in_database', false);
    //     }
    //     $total_surveys = $total_surveys->count();
 
    //     $total_unknown = $query->whereNull('voting_for')->where('voting_decision','undecided')->count();


    //     $total_naver_vote =  Voter::with('user')
    //         ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date','surveys.user_id','surveys.voting_for')
    //         ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
    //         ->join('surveys', 'voters.id', '=', 'surveys.voter_id')
    //         ->whereExists(function ($query) {
    //             $query->select('id')
    //                 ->from('surveys')
    //                 ->whereColumn('surveys.voter_id', 'voters.id');
    //         })
    //         ->whereNull('surveys.voting_for')
    //         ->where('surveys.voting_decision','no')
    //         ->whereIn('voters.const', explode(',', Auth::user()->constituency_id));
            
    //     // Apply exists_in_database filter
    //     if ($existsInDatabase === 'true') {
    //         $total_naver_vote->where('voters.exists_in_database', true);
    //     } elseif ($existsInDatabase === 'false') {
    //         $total_naver_vote->where('voters.exists_in_database', false);
    //     }
    //     $total_naver_vote = $total_naver_vote->count();
        
           

    //     $firstTimeVotersQuery = Voter::whereRaw("EXTRACT(YEAR FROM AGE(CURRENT_DATE, dob::date)) <= 25")->whereIn('voters.const', explode(',', $surveyor->constituency_id));
        
    //     // Apply exists_in_database filter
    //     if ($existsInDatabase === 'true') {
    //         $firstTimeVotersQuery->where('voters.exists_in_database', true);
    //     } elseif ($existsInDatabase === 'false') {
    //         $firstTimeVotersQuery->where('voters.exists_in_database', false);
    //     }
        
    //     $first_time_voters = $firstTimeVotersQuery->count();     
        
       


    //     $data = [    
    //         'registered' => $registered,
    //         'fnm' => $fnm,
    //         'plp' => $plp,
    //         'other_parties' => $other_parties,
    //         'total_unknown' => $total_unknown,
    //         'total_surveys' => $total_surveys,
    //         'total_naver_vote' => $total_naver_vote,
    //         'first_time_voters' => $first_time_voters,
    //         'coi' => $coi
    //     ];

    //     return response()->json([
    //         'success' => true,
    //         'data' => $data
    //     ]); 
    // }


    
    // public function stats(Request $request)
    // {
    //     $surveyor = User::where('id', Auth::user()->id)->first();
    //     $existsInDatabase = $request->input('exists_in_database');

    //     $registeredQuery = Voter::whereIn('const', explode(',', $surveyor->constituency_id));
        
    //     // Apply exists_in_database filter
    //     if ($existsInDatabase === 'true') {
    //         $registeredQuery->where('voters.exists_in_database', true);
    //     } elseif ($existsInDatabase === 'false') {
    //         $registeredQuery->where('voters.exists_in_database', false);
    //     }
        
    //     $registered = $registeredQuery->count();
        
    //     // Base query for all survey stats   
    //     $baseQuery = Voter::with('user')
    //         ->select(
    //             'voters.*',
    //             'constituencies.name as constituency_name',
    //             'surveys.id as survey_id',
    //             'surveys.created_at as survey_date', 
    //             'surveys.user_id',
    //             'surveys.located',
    //             'surveys.voting_decision',
    //             'surveys.voting_for',
    //             'surveys.is_died',
    //             'surveys.died_date',
    //             'surveys.work_phone_code',
    //             'surveys.work_phone',
    //             'surveys.cell_phone_code',
    //             'surveys.cell_phone',
    //             'surveys.email',
    //             'surveys.home_phone_code',
    //             'surveys.home_phone',
    //             'surveys.special_comments',
    //             'surveys.other_comments'
    //         )
    //         ->join('constituencies', 'voters.const', '=', 'constituencies.id')
    //         ->join(DB::raw("(
    //             SELECT DISTINCT ON (voter_id) * 
    //             FROM surveys 
    //             ORDER BY voter_id, created_at DESC
    //         ) as surveys"), 'voters.id', '=', 'surveys.voter_id')
    //         ->whereIn('voters.const', explode(',', Auth::user()->constituency_id));

    //     // Apply exists_in_database filter
    //     if ($existsInDatabase === 'true') {
    //         $baseQuery->where('voters.exists_in_database', true);
    //     } elseif ($existsInDatabase === 'false') {
    //         $baseQuery->where('voters.exists_in_database', false);
    //     }

   
       
        
    //     $total_unknown = (clone $baseQuery)
    //         ->whereNull('surveys.voting_for')
    //         ->where('surveys.voting_decision', 'undecided')
    //         ->count();

 
    //     $total_naver_vote = (clone $baseQuery)
    //         ->whereNull('surveys.voting_for')
    //         ->where('surveys.voting_decision', 'no')
    //         ->count();

    //     $first_time_voters = (clone $baseQuery)
    //         ->whereRaw("EXTRACT(YEAR FROM AGE(CURRENT_DATE, dob::date)) <= 25")
    //         ->count();
        
    //     $fnm = (clone $baseQuery)
    //         ->where(function($query) {
    //             $query->where('surveys.voting_for', 'FNM')
    //                   ->orWhere('surveys.voting_for', 'Free National Movement');
    //         })->count();

    //     $coi = (clone $baseQuery)
    //         ->where(function($query) {
    //             $query->where('surveys.voting_for', 'COI')
    //                   ->orWhere('surveys.voting_for', 'Coalition of Independents');
    //         })->count();
 
    //     $plp = (clone $baseQuery)
    //         ->where(function($query) {
    //             $query->where('surveys.voting_for', 'PLP')
    //                   ->orWhere('surveys.voting_for', 'Progressive Liberal Party');
    //         })->count();

    //     $other_parties = (clone $baseQuery)
    //         ->where(function($query) {
    //             $query->whereNotIn('surveys.voting_for', [
    //                 'FNM', 'Free National Movement',
    //                 'PLP', 'Progressive Liberal Party',
    //                 'COI', 'Coalition of Independents'
    //             ])
    //             ->whereNotNull('surveys.voting_for');
    //         })->count();

    //     $total_surveys = (clone $baseQuery)->count();
 

    //     $plus_amount = $fnm + $plp + $coi + $other_parties + $total_unknown + $total_naver_vote;
    //     $data = [    
           
    //         'fnm' => $fnm,
    //         'plp' => $plp,
    //         'coi' => $coi,
    //         'other_parties' => $other_parties,
    //         'total_unknown' => $total_unknown,
    //         'total_naver_vote' => $total_naver_vote,
            
    //         'total_surveys' => $total_surveys,
    //         'first_time_voters' => $first_time_voters,
    //         'registered' => $registered,
    //         'plus_amount' => $plus_amount,
    //     ];

    //     return response()->json([
    //         'success' => true,
    //         'data' => $data
    //     ]); 
    // }
    

     

}
