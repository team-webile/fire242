<?php

namespace App\Http\Controllers\Api\User;

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
use App\Models\SystemSetting;
use App\Models\UnregisteredVoter;
use App\Models\ManagerSystemSetting;
use App\Models\Party;
use Illuminate\Support\Facades\DB;
use App\Models\Question;
use App\Models\Answer;
use App\Models\SurveyAnswer;
use App\Models\Page;
use App\Models\VoterCardImage;
use LDAP\ResultEntry;
use thiagoalessio\TesseractOCR\TesseractOCR;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
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


    // public function stats()
    // {

    //     // Total Registered
    //     // Total FNM
    //     // Total Unknown
    //     // Total Surveyed
    //     $surveyor = User::where('id', Auth::user()->id)->first();

    //     $registered = Voter::whereIn('const', explode(',', $surveyor->constituency_id))->count();
        
         
    //     $fnm = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
    //         ->where(function($query) {
    //             $query->where('voting_for', 'FNM')
    //                   ->orWhere('voting_for', 'Free National Movement');
    //         })
    //         ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
    //         ->count(); 
 
    //     $plp = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
    //         ->where(function($query) {
    //             $query->where('voting_for', 'PLP')
    //                   ->orWhere('voting_for', 'Progressive Liberal Party');
    //         })
    //         ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
    //         ->count();

    //     $other_parties = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
    //         ->where(function($query) {
    //             $query->whereNotIn('voting_for', ['FNM', 'Free National Movement', 'PLP', 'Progressive Liberal Party'])
    //                   ->whereNotNull('voting_for');
    //         })
    //         ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
    //         ->count();

    //         $unSurveyVoters = Voter::select('voters.*')
    //             ->whereNotExists(function ($query) {
    //                 $query->select(DB::raw(1))
    //                     ->from('surveys')
    //                     ->whereRaw('surveys.voter_id = voters.id');
    //             })
    //             ->whereIn('const', explode(',', $surveyor->constituency_id))
    //             ->count();
        
       
    //     $total_surveys = Survey::where('user_id', Auth::user()->id)->count();
 
    //     // $total_unknown = $registered - ($fnm + $plp + $other_parties);

    //     $total_unknown= Survey::where('user_id', Auth::user()->id)->whereNull('voting_for')->where('voting_decision','undecided')->count();
    //     $total_naver_vote= Survey::where('user_id', Auth::user()->id)->whereNull('voting_for')->where('voting_decision','no')->count();
    //     $first_time_voters = Voter::whereRaw("EXTRACT(YEAR FROM AGE(CURRENT_DATE, dob::date)) <= 25")->whereIn('voters.const', explode(',', $surveyor->constituency_id))->count();     
        
       


    //     $data = [    
    //         'registered' => $registered,
    //         'fnm' => $fnm,
    //         'plp' => $plp,
    //         'other_parties' => $other_parties,
    //         'total_unknown' => $total_unknown,
    //         'total_naver_vote' => $total_naver_vote, 
    //         'total_surveys' => $total_surveys,
    //         'first_time_voters' => $first_time_voters,
    //         'unSurveyVoters' => $unSurveyVoters
    //     ];

    //     return response()->json([
    //         'success' => true,
    //         'data' => $data
    //     ]);
    // }
 
    



    public function stats(Request $request)
    {

        // Total Registered
        // Total FNM
        // Total Unknown
        // Total Surveyed
        $surveyor = User::where('id', Auth::user()->id)->first();
        $existsInDatabase = $request->input('exists_in_database');
        $query = Voter::whereIn('const', explode(',', $surveyor->constituency_id));
            if($existsInDatabase === 'true') { 
                $query->where('voters.exists_in_database', true);
            } elseif($existsInDatabase === 'false') {
                $query->where('voters.exists_in_database', false);
            }
        $registered =  $query->count();
        
         
        $fnm = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
            ->where(function($query) {
                $query->where('voting_for', 'FNM')
                      ->orWhere('voting_for', 'Free National Movement');
            })
            ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
            ->count(); 
 
        $plp = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
            ->where(function($query) {
                $query->where('voting_for', 'PLP')
                      ->orWhere('voting_for', 'Progressive Liberal Party');
            })
            ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
            ->count();

        $other_parties = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
            ->where(function($query) {
                $query->whereNotIn('voting_for', ['FNM', 'Free National Movement', 'PLP', 'Progressive Liberal Party'])
                      ->whereNotNull('voting_for');
            })
            ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
            ->count();

            $unSurveyVoters = Voter::select('voters.*')
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('surveys')
                        ->whereRaw('surveys.voter_id = voters.id');
                })
                ->whereIn('const', explode(',', $surveyor->constituency_id))
                ->when($existsInDatabase === 'true', function($query) {
                    $query->where('voters.exists_in_database', true);
                })
                ->when($existsInDatabase === 'false', function($query) {
                    $query->where('voters.exists_in_database', false);
                })
                ->count();
        
       
        $total_surveys = Survey::where('user_id', Auth::user()->id)->count();
 
        // $total_unknown = $registered - ($fnm + $plp + $other_parties);

        $total_unknown= Survey::where('user_id', Auth::user()->id)->whereNull('voting_for')->where('voting_decision','undecided')->count();
        $total_naver_vote= Survey::where('user_id', Auth::user()->id)->whereNull('voting_for')->where('voting_decision','no')->count();
        $first_time_voters = Voter::whereRaw("EXTRACT(YEAR FROM AGE(CURRENT_DATE, dob::date)) <= 25")->whereIn('voters.const', explode(',', $surveyor->constituency_id))->count();     
        
       


        $data = [    
            'registered' => $registered,
            'fnm' => $fnm,
            'plp' => $plp,
            'other_parties' => $other_parties,
            'total_unknown' => $total_unknown,
            'total_naver_vote' => $total_naver_vote, 
            'total_surveys' => $total_surveys,
            'first_time_voters' => $first_time_voters,
            'unSurveyVoters' => $unSurveyVoters
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
 



    public function appStats(Request $request)
    {
        $existsInDatabase = $request->input('exists_in_database');

        // Exit Poll Counts
        $fnm = VoterCardImage::where(function ($q) {
            $q->whereRaw('LOWER(exit_poll) = ?', ['fnm'])
            ->orWhereRaw('LOWER(exit_poll) = ?', ['free national movement']);
        })->count();

        $plp = VoterCardImage::where(function ($q) {
            $q->whereRaw('LOWER(exit_poll) = ?', ['plp'])
            ->orWhereRaw('LOWER(exit_poll) = ?', ['progressive liberal party']);
        })->count();

        $coi = VoterCardImage::where(function ($q) {
            $q->whereRaw('LOWER(exit_poll) = ?', ['coi'])
            ->orWhereRaw('LOWER(exit_poll) = ?', ['coalition of independents']);
        })->count();

        $unk = VoterCardImage::where(function ($q) {
            $q->whereRaw('LOWER(exit_poll) = ?', ['unk'])
            ->orWhereRaw('LOWER(exit_poll) = ?', ['unknown']);
        })->count();

        $other = VoterCardImage::where(function ($q) {
            $q->whereRaw('LOWER(exit_poll) = ?', ['other'])
            ->orWhereRaw('LOWER(exit_poll) = ?', ['other party']);
        })->count();

        $never_vote = VoterCardImage::whereRaw('LOWER(exit_poll) = ?', ['no'])->count();


        // Surveyed Voters (Latest Survey per Voter)
        $surveyedQuery = Voter::with('user')
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
                'surveys.other_comments'
            )
            ->join('constituencies', 'voters.const', '=', 'constituencies.id')
            ->join(DB::raw("
                (
                    SELECT DISTINCT ON (voter_id) *
                    FROM surveys
                    ORDER BY voter_id, created_at DESC
                ) as surveys
            "), 'voters.id', '=', 'surveys.voter_id');

        if ($existsInDatabase === 'true') {
            $surveyedQuery->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $surveyedQuery->where('voters.exists_in_database', false);
        }

        $surveyedCount = $surveyedQuery->count();


        // Unregistered Voters
        $unregistered = UnregisteredVoter::count();


        // Total Voters (Filtered if needed)
        $totalVoterQuery = Voter::query();

        if ($existsInDatabase === 'true') {
            $totalVoterQuery->where('exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $totalVoterQuery->where('exists_in_database', false);
        }

        $totalVoters = $totalVoterQuery->count();


        // Sum of exit poll results
        $plusAll = $fnm + $plp + $coi + $other + $never_vote;


        $total_unknown = Survey::whereNull('voting_for')
        ->where('voting_decision', 'undecided')
        ->count();
    
        $naver_vote = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
        ->join(DB::raw("(
            SELECT DISTINCT ON (voter_id) id
            FROM surveys
            ORDER BY voter_id, created_at DESC
        ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
        ->whereNull('voting_for')
        ->where('voting_decision', 'no');
        
        

        if ($existsInDatabase === 'true') {
            $naver_vote->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') { 
            $naver_vote->where('voters.exists_in_database', false);
        }
        $naver_vote = $naver_vote->count();
    


        $total_surveyed_fnm_voters = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
            ->where(function($query) {
                $query->where('voting_for', 'FNM')
                      ->orWhere('voting_for', 'Free National Movement');
            })->count(); 




            $totalvoters_not_yet_voted = DB::table('voters as v')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('voter_cards_images as i')
                    ->whereColumn('i.reg_no', 'v.voter');
            })
            ->count();

            $total_voters__yet_voted = DB::table('voters as v')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('voter_cards_images as i')
                    ->whereColumn('i.reg_no', 'v.voter');
            })
            ->count();



            
        $nulled_record = VoterCardImage::whereNull('reg_no')->count();
 

            
        $parties_stats = [
            'fnm'                     => $fnm,
            'plp'                     => $plp,
            'coi'                     => $coi,
            'unk'                     => $unk,
        ];
        // Final Response
        $data = [
        
            // 'unregistered_total'      => $unregistered,
            'total_registered_voters' => $totalVoters,
            'surveyed_voters'         => $surveyedCount,
            // 'total_naver_vote'        => $naver_vote,
            'total_surveyed_fnm_voters' => $total_surveyed_fnm_voters,
            'total_voters_not_yet_voted' => $totalvoters_not_yet_voted,
            // 'reg_no_null_record' => $nulled_record,
          'total_voters_voted' => $total_voters__yet_voted,
             
        ];

        return response()->json([
            'success' => true,
            'data'    => $data,
            'parties_stats' => $parties_stats,
        ]);
    }

  
 

    public function statsList(Request $request)
    { 
        $surveyor = User::where('id', Auth::user()->id)->first();
        $type = $request->get('type', 'registered');
        $perPage = $request->get('per_page', 20);

        $results = match($type) {
            'registered' => $this->getRegisteredVoters($surveyor, $perPage),
            'fnm' => $this->getFNMVoters($request,$surveyor, $perPage),
            'plp' => $this->getPLPVoters($request,$surveyor, $perPage), 
            'other_parties' => $this->getOtherPartyVoters($request,$surveyor, $perPage),
            'total_surveys' => $this->getTotalSurveys($request,$surveyor, $perPage),
            'total_unknown' => $this->getTotalUnknown($request,$surveyor, $perPage),
            'total_naver_vote' => $this->getTotalnaverVote($request,$surveyor, $perPage),
            'first_time_voters' => $this->getFirstTimeVoters($request,$surveyor, $perPage),
            default => response()->json([
                'success' => false,
                'message' => 'Invalid type specified'
            ], 400)
        };

        return response()->json([
            'success' => true,
            'data' => $results
        ]);
    }

    private function getRegisteredVoters($surveyor, $perPage)
    {
        return Voter::query()
            ->whereIn('const', explode(',', $surveyor->constituency_id))
            ->paginate($perPage);
    }

    private function getFNMVoters($request,$surveyor, $perPage) 
    {
          
       
        $surname = $request->input('surname');
        $firstName = $request->input('first_name');
        $secondName = $request->input('second_name'); 
        $last_name = $request->input('last_name'); 
        $address = $request->input('address');
        $voterId = $request->input('voter_id');
        $constituencyName = $request->input('constituency_name');
        $constituencyId = $request->input('constituency_id'); 
        $underAge25 = $request->input('under_age_25');
        $houseNumber = $request->input('house_number');
        $pobse = $request->input('pobse');
        $pobis = $request->input('pobis');
        $pobcn = $request->input('pobcn');
        $polling = $request->input('polling');
        $existsInDatabase = $request->input('exists_in_database');


    $query = Voter::with('user')
        ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date','surveys.user_id','surveys.voting_for')
        ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
        ->join('surveys', 'voters.id', '=', 'surveys.voter_id')
        ->whereExists(function ($query) {
            $query->select('id')
                ->from('surveys')
                ->whereColumn('surveys.voter_id', 'voters.id');
        })
        ->where(function($query) {
            $query->where('voting_for', 'FNM')
                  ->orWhere('voting_for', 'Free National Movement');
        })
        ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
        ->where('surveys.user_id', Auth::id())
        ->orderBy('surveys.id', 'desc');

    // Apply exists_in_database filter
    if ($existsInDatabase === 'true') {
        $query->where('voters.exists_in_database', true);
    } elseif ($existsInDatabase === 'false') {
        $query->where('voters.exists_in_database', false);
    }

     // Apply search filters

     $searchableFields = [
         'first_name' => 'First Name',
         'second_name' => 'Second Name',
         'surname' => 'Surname', 
         'address' => 'Address',
         'voter_id' => 'Voter ID',
         'polling' => 'Polling Station',
         'const' => 'Constituency ID',
         'constituency_name' => 'Constituency Name',
         'user_id' => 'User ID',
         'polling' => 'Polling Station',
         'voting_decision' => 'Voting Decision'
 
     ];  

       

        if (!empty($polling) && is_numeric($polling)) {
            $query->where('voters.polling', $polling);
        }
        if (!empty($voting_decision)) {
            $query->where('surveys.voting_decision', $voting_decision);
        }

        if ($underAge25 === 'yes') {
            $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
        }

        // Get search parameters
        if (isset($request->user_id) && !empty($request->user_id)) { 
            $query->where('surveys.user_id', $request->user_id);
        }
        if (isset($request->start_date) && !empty($request->start_date)) {
            $query->where('surveys.created_at', '>=', $request->start_date . ' 00:00:00');
        }

        if (isset($request->end_date) && !empty($request->end_date)) {
            $query->where('surveys.created_at', '<=', $request->end_date . ' 23:59:59');
        }

        if (!empty($surname)) {
            $query->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [strtolower($surname)]);
        }

        if (!empty($firstName)) {
            $query->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [strtolower($firstName)]);
        }

        if (!empty($secondName)) {
            $query->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [strtolower($secondName)]);

        }

        if (!empty($last_name)) {
            $query->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [strtolower($last_name)]);

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
            $query->where('voters.voter', $voterId); // Changed from voters.voter to voters.id since voter ID should match the primary key
        }

        if (!empty($constituencyName)) {
            $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
        }

        if (!empty($constituencyId)) {
            $query->where('voters.const', $constituencyId);
        }

        // Get paginated results with all surveys
        $voters = $query->paginate($perPage);

        
        return $voters; 

    }

    private function getPLPVoters($request,$surveyor, $perPage)
    {
        
            $surname = $request->input('surname');
            $firstName = $request->input('first_name');
            $secondName = $request->input('second_name');
            $last_name = $request->input('last_name');
            $address = $request->input('address');
            $voterId = $request->input('voter_id');
            $constituencyName = $request->input('constituency_name');
            $constituencyId = $request->input('constituency_id'); 
            $underAge25 = $request->input('under_age_25');
            $houseNumber = $request->input('house_number');
            $pobse = $request->input('pobse');
            $pobis = $request->input('pobis');
            $pobcn = $request->input('pobcn');
            $polling = $request->input('polling');
            $existsInDatabase = $request->input('exists_in_database');
    
    
        $query = Voter::with('user')
            ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date','surveys.user_id','surveys.voting_for')
            ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
            ->join('surveys', 'voters.id', '=', 'surveys.voter_id')
            ->whereExists(function ($query) {
                $query->select('id')
                    ->from('surveys')
                    ->whereColumn('surveys.voter_id', 'voters.id');
        })
        ->where(function($query) {
            $query->where('voting_for', 'PLP')
                  ->orWhere('voting_for', 'Progressive Liberal Party');
        })
        ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
        ->where('surveys.user_id', Auth::id())
        ->orderBy('surveys.id', 'desc');

        // Apply exists_in_database filter
        if ($existsInDatabase === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false);
        }
     
            if (!empty($polling) && is_numeric($polling)) {
                $query->where('voters.polling', $polling);
            }
            if (!empty($voting_decision)) {
                $query->where('surveys.voting_decision', $voting_decision);
            }
    
            if ($underAge25 === 'yes') {
                $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
            }
    
            // Get search parameters
            if (isset($request->user_id) && !empty($request->user_id)) { 
                $query->where('surveys.user_id', $request->user_id);
            }
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
                $query->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [strtolower($firstName)]);

            }
    
            if (!empty($secondName)) {
                $query->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [strtolower($secondName)]);

            }
            
            if (!empty($last_name)) {
                $query->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [strtolower($last_name)]);

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
                $query->where('voters.voter', $voterId); // Changed from voters.voter to voters.id since voter ID should match the primary key
            }
    
            if (!empty($constituencyName)) {
                $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
            }
    
            if (!empty($constituencyId)) {
                $query->where('voters.const', $constituencyId);
            } 
    
            // Get paginated results with all surveys
            $voters = $query->paginate($perPage);
    
            
            return $voters; 

    }

    private function getOtherPartyVoters($request,$surveyor, $perPage)
    {
          
            $surname = $request->input('surname');
            $firstName = $request->input('first_name');
            $secondName = $request->input('second_name');
            $last_name = $request->input('last_name');
            $address = $request->input('address');
            $voterId = $request->input('voter_id');
            $constituencyName = $request->input('constituency_name');
            $constituencyId = $request->input('constituency_id'); 
            $underAge25 = $request->input('under_age_25');
            $houseNumber = $request->input('house_number');
            $pobse = $request->input('pobse');
            $pobis = $request->input('pobis');
            $pobcn = $request->input('pobcn');
            $polling = $request->input('polling');
            $existsInDatabase = $request->input('exists_in_database');
    
    
        $query = Voter::with('user')
            ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date','surveys.user_id','surveys.voting_for')
            ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
            ->join('surveys', 'voters.id', '=', 'surveys.voter_id')
            ->whereExists(function ($query) {
                $query->select('id')
                    ->from('surveys')
                    ->whereColumn('surveys.voter_id', 'voters.id');
        })
        ->where(function($query) {
            $query->whereNotIn('voting_for', ['FNM', 'Free National Movement', 'PLP', 'Progressive Liberal Party'])
                  ->whereNotNull('voting_for');
        })
        ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
        ->where('surveys.user_id', Auth::id())
        ->orderBy('surveys.id', 'desc');

        // Apply exists_in_database filter
        if ($existsInDatabase === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false);
        }
     
            if (!empty($polling) && is_numeric($polling)) {
                $query->where('voters.polling', $polling);
            }
            if (!empty($voting_decision)) {
                $query->where('surveys.voting_decision', $voting_decision);
            }
    
            if ($underAge25 === 'yes') {
                $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
            }
    
            // Get search parameters
            if (isset($request->user_id) && !empty($request->user_id)) { 
                $query->where('surveys.user_id', $request->user_id);
            }
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
                $query->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [strtolower($firstName)]);

            }
    
            if (!empty($secondName)) {
                $query->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [strtolower($secondName)]);

            }
            
            if (!empty($last_name)) {
                $query->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [strtolower($last_name)]);

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
                $query->where('voters.voter', $voterId); // Changed from voters.voter to voters.id since voter ID should match the primary key
            }
    
            if (!empty($constituencyName)) {
                $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
            }
    
            if (!empty($constituencyId)) {
                $query->where('voters.const', $constituencyId);
            } 
    
            // Get paginated results with all surveys
            $voters = $query->paginate($perPage);
    
            
            return $voters; 

    }

    private function getTotalSurveys($request,$surveyor, $perPage)
    {
        $query = Survey::with('voter');

        // Search fields based on Survey model's fillable columns
        // Apply all search filters directly from URL parameters with case-insensitive search
        if ($request->has('sex')) {
            $query->whereRaw('LOWER(sex) = ?', [strtolower($request->sex)]);
        }
        if ($request->has('voting_decision')) {
            $query->where('voting_decision',$request->voting_decision);
        }

        if ($request->has('marital_status')) {
            $query->whereRaw('LOWER(marital_status) = ?', [strtolower($request->marital_status)]);
        }

        if ($request->has('employed')) {
            $query->where('employed', filter_var($request->employed, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('children')) {
            $query->where('children', filter_var($request->children, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('employment_type')) {
            $query->whereRaw('LOWER(employment_type) = ?', [strtolower($request->employment_type)]);
        }

        if ($request->has('religion')) {
            $query->whereRaw('LOWER(religion) = ?', [strtolower($request->religion)]);
        }

        if ($request->has('located')) {
            $query->whereRaw('LOWER(located) = ?', [strtolower($request->located)]);
        }

        if ($request->has('home_phone')) {
            $query->whereRaw('LOWER(home_phone) LIKE ?', ['%' . strtolower($request->home_phone) . '%']);
        }

        if ($request->has('work_phone')) {
            $query->whereRaw('LOWER(work_phone) LIKE ?', ['%' . strtolower($request->work_phone) . '%']);
        }

        if ($request->has('cell_phone')) {
            $query->whereRaw('LOWER(cell_phone) LIKE ?', ['%' . strtolower($request->cell_phone) . '%']);
        }

        if ($request->has('email')) {
            $query->whereRaw('LOWER(email) LIKE ?', ['%' . strtolower($request->email) . '%']);
        }

        if ($request->has('special_comments')) {
            $query->whereRaw('LOWER(special_comments) LIKE ?', ['%' . strtolower($request->special_comments) . '%']);
        }

        if ($request->has('other_comments')) {
            $query->whereRaw('LOWER(other_comments) LIKE ?', ['%' . strtolower($request->other_comments) . '%']);
        }

        if ($request->has('voting_for')) {
            $query->whereRaw('LOWER(voting_for) = ?', [strtolower($request->voting_for)]);
        }

        if ($request->has('voted_in_2017')) {
            $query->where('voted_in_2017', filter_var($request->voted_in_2017, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('where_voted_in_2017')) {
            $query->whereRaw('LOWER(where_voted_in_2017) LIKE ?', ['%' . strtolower($request->where_voted_in_2017) . '%']);
        }

        if ($request->has('voted_in_house')) {
            $query->whereRaw('LOWER(voted_in_house) = ?', [strtolower($request->voted_in_house)]);
        }

        if ($request->has('voter_id')) {
            $query->whereHas('voter', function($q) use ($request) {
                $q->where('id', $request->voter_id);
            });
        }

        if ($request->has('constituency_id')) {
            $query->whereHas('voter', function($q) use ($request) {
                $q->where('const', $request->constituency_id);
            });
        }

        if ($request->has('first_name')) {
            $query->whereHas('voter', function($q) use ($request) {
                $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->first_name) . '%']);
            });
        }

        if ($request->has('last_name')) {
            $query->whereHas('voter', function($q) use ($request) {
                $q->whereRaw('LOWER(surname) LIKE ?', ['%' . strtolower($request->last_name) . '%']);
            });
        } 

        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->start_date . ' 00:00:00');
        }

        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->end_date . ' 23:59:59');
        }

         
        // Get paginated results
        $surveys = $query->where('user_id', auth()->user()->id)->orderBy('id', 'desc')->paginate($perPage);

        return $surveys;
    }

    private function getTotalUnknown($request,$surveyor, $perPage)
    {
         

            $surname = $request->input('surname');
            $firstName = $request->input('first_name');
            $secondName = $request->input('second_name');
            $last_name = $request->input('last_name');
            $address = $request->input('address');
            $voterId = $request->input('voter_id');
            $constituencyName = $request->input('constituency_name');
            $constituencyId = $request->input('constituency_id');  
            $underAge25 = $request->input('under_age_25');
            $houseNumber = $request->input('house_number');
            $pobse = $request->input('pobse');
            $pobis = $request->input('pobis');
            $pobcn = $request->input('pobcn');
            $polling = $request->input('polling'); 
            $existsInDatabase = $request->input('exists_in_database');
    
    
        $query = Voter::with('user')
            ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date','surveys.user_id','surveys.voting_for')
            ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
            ->join('surveys', 'voters.id', '=', 'surveys.voter_id')
            ->whereExists(function ($query) {
                $query->select('id')
                    ->from('surveys')
                    ->whereColumn('surveys.voter_id', 'voters.id');
        })
        ->whereNull('surveys.voting_for')
        ->where('surveys.voting_decision','undecided')
        ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
        ->where('surveys.user_id', Auth::id())
        ->orderBy('surveys.id', 'desc');

        // Apply exists_in_database filter
        if ($existsInDatabase === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false);
        }
     
            if (!empty($polling) && is_numeric($polling)) {
                $query->where('voters.polling', $polling);
            }
            if (!empty($voting_decision)) {
                $query->where('surveys.voting_decision', $voting_decision);
            }
    
            if ($underAge25 === 'yes') {
                $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
            }
    
            // Get search parameters
            if (isset($request->user_id) && !empty($request->user_id)) { 
                $query->where('surveys.user_id', $request->user_id);
            }
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
                $query->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [strtolower($firstName)]);

            }
    
            if (!empty($secondName)) {
                $query->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [strtolower($secondName)]);

            }
            
            if (!empty($last_name)) {
                $query->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [strtolower($last_name)]);

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
                $query->where('voters.voter', $voterId); // Changed from voters.voter to voters.id since voter ID should match the primary key
            }
    
            if (!empty($constituencyName)) {
                $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
            }
    
            if (!empty($constituencyId)) {
                $query->where('voters.const', $constituencyId);
            } 
    
            // Get paginated results with all surveys
            $voters = $query->paginate($perPage);
    
            
            return $voters;
    }
    private function getTotalnaverVote($request,$surveyor, $perPage)
    {
         

            $surname = $request->input('surname');
            $firstName = $request->input('first_name');
            $secondName = $request->input('second_name');
            $last_name = $request->input('last_name');
            $address = $request->input('address');
            $voterId = $request->input('voter_id');
            $constituencyName = $request->input('constituency_name');
            $constituencyId = $request->input('constituency_id');  
            $underAge25 = $request->input('under_age_25');
            $houseNumber = $request->input('house_number');
            $pobse = $request->input('pobse');
            $pobis = $request->input('pobis');
            $pobcn = $request->input('pobcn');
            $polling = $request->input('polling'); 
            $existsInDatabase = $request->input('exists_in_database');
    
    
        $query = Voter::with('user')
            ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date','surveys.user_id','surveys.voting_for')
            ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
            ->join('surveys', 'voters.id', '=', 'surveys.voter_id')
            ->whereExists(function ($query) {
                $query->select('id')
                    ->from('surveys')
                    ->whereColumn('surveys.voter_id', 'voters.id');
        })
        ->whereNull('surveys.voting_for')
        ->where('surveys.voting_decision','no') 
        ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
        ->where('surveys.user_id', Auth::id())
        ->orderBy('surveys.id', 'desc');

        // Apply exists_in_database filter
        if ($existsInDatabase === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false);
        }
     
            if (!empty($polling) && is_numeric($polling)) {
                $query->where('voters.polling', $polling);
            }
            if (!empty($voting_decision)) {
                $query->where('surveys.voting_decision', $voting_decision);
            }
    
            if ($underAge25 === 'yes') {
                $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
            }
    
            // Get search parameters
            if (isset($request->user_id) && !empty($request->user_id)) { 
                $query->where('surveys.user_id', $request->user_id);
            }
            if (isset($request->start_date) && !empty($request->start_date)) {
                $query->where('surveys.created_at', '>=', $request->start_date . ' 00:00:00');
            }
    
            if (isset($request->end_date) && !empty($request->end_date)) {
                $query->where('surveys.created_at', '<=', $request->end_date . ' 23:59:59');
            }
    
            if (!empty($surname)) {
                $query->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [strtolower($surname)]);

            }
    
            if (!empty($firstName)) {
                $query->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [strtolower($firstName)]);

            }
    
            if (!empty($secondName)) {
                $query->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [strtolower($secondName)]);

            }
            
            if (!empty($last_name)) {
                $query->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [strtolower($last_name)]);

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
                $query->where('voters.voter', $voterId); // Changed from voters.voter to voters.id since voter ID should match the primary key
            }
    
            if (!empty($constituencyName)) {
                $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
            }
    
            if (!empty($constituencyId)) {
                $query->where('voters.const', $constituencyId);
            } 
    
            // Get paginated results with all surveys
            $voters = $query->paginate($perPage);
    
            
            return $voters;
    }

    private function getFirstTimeVoters($request, $surveyor, $perPage)
    {
        $existsInDatabase = $request->input('exists_in_database');
        
        $query = Voter::query()
            ->whereRaw("EXTRACT(YEAR FROM AGE(CURRENT_DATE, dob::date)) <= 25")
            ->whereIn('const', explode(',', $surveyor->constituency_id));

        // Apply exists_in_database filter
        if ($existsInDatabase === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false);
        }

        return $query->paginate($perPage);
    }
    public function getSystemSettings()
    {

        $get_login_user = Auth::user();
        $userConstituency = explode(',', $get_login_user->constituency_id);
        $getSystemSettings = ManagerSystemSetting::whereIn('constituency_id', $userConstituency)->orderBy('id','desc')->first();

        if(!is_null( $getSystemSettings)){
            // $settings = $getSystemSettings;
            $settings = $getSystemSettings;  
        }else{
            $settings = SystemSetting::first(); 
        }

     
        return response()->json($settings);
    }

    public function livesearch(Request $request)
    { 

        $query = Party::query();

        if ($request->has('party') && !empty($request->party)) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->party) . '%']);
        }

        return response()->json(['success' => true, 'data' => $query->get()]);
    }


  public function getQuestionStats()
  {
      try {
          // Get all questions with their answers
          $questions = Question::with('answers')->get();
          $stats = [];
  
          foreach ($questions as $question) {
              // Get count of each answer for the authenticated user only
              $answerCounts = SurveyAnswer::where('question_id', $question->id)
                  ->whereExists(function ($query) {
                      $query->select(DB::raw(1))
                          ->from('surveys')
                          ->whereColumn('surveys.id', 'survey_answers.survey_id')
                          ->where('surveys.user_id', Auth::id());
                  })
                  ->select('answer_id', DB::raw('count(*) as total'))
                  ->groupBy('answer_id')
                  ->get()
                  ->keyBy('answer_id');
  
              // Get total responses for this question
              $totalResponses = $answerCounts->sum('total');
   
              // Calculate stats for each answer
              $answerStats = [];
              foreach ($question->answers as $answer) {
                  $count = isset($answerCounts[$answer->id]) ? $answerCounts[$answer->id]->total : 0;
                  
                  // Calculate percentage based on total responses
                  $percentage = $totalResponses > 0 ? round(($count / $totalResponses) * 100, 2) : 0;

                  // Get gender breakdown for this answer
                  $maleCount = SurveyAnswer::where('question_id', $question->id)
                      ->where('answer_id', $answer->id)
                      ->whereExists(function ($query) {
                          $query->select(DB::raw(1))
                              ->from('surveys')
                              ->whereColumn('surveys.id', 'survey_answers.survey_id')
                              ->where('surveys.user_id', Auth::id())
                              ->where('surveys.sex', 'Male');
                      })
                      ->count();

                  $femaleCount = SurveyAnswer::where('question_id', $question->id)
                      ->where('answer_id', $answer->id)
                      ->whereExists(function ($query) {
                          $query->select(DB::raw(1))
                              ->from('surveys')
                              ->whereColumn('surveys.id', 'survey_answers.survey_id')
                              ->where('surveys.user_id', Auth::id())
                              ->where('surveys.sex', 'Female');
                      })
                      ->count();

                  $malePercentage = $count > 0 ? round(($maleCount / $count) * 100, 2) : 0;
                  $femalePercentage = $count > 0 ? round(($femaleCount / $count) * 100, 2) : 0;
  
                  $answerStats[] = [
                      'answer' => $answer->answer,
                      'count' => $count,
                      'percentage' => $percentage,
                      'gender_breakdown' => [
                          'male' => [
                              'count' => $maleCount,
                              'percentage' => $malePercentage
                          ],
                          'female' => [
                              'count' => $femaleCount,
                              'percentage' => $femalePercentage
                          ]
                      ]
                  ];
              }
  
              $stats[] = [
                  'question' => $question->question,
                  'total_responses' => $totalResponses,
                  'answers' => $answerStats
              ];
          }
  
          return response()->json([
              'success' => true,
              'data' => $stats
          ]);
  
      } catch (\Exception $e) {
          return response()->json([
              'success' => false,
              'message' => 'Error calculating statistics',
              'error' => $e->getMessage()
          ], 500);
      }
  }
    // ... existing code ...
        
    public function fatch_all_user_permissions()
    {
    
        $permissions = Page::orderBy('id','desc')->get();
        return response()->json(['success' => true, 'data' => $permissions]);
    } 


    

    public function upload_voter_card(Request $request)
    {
        try {
            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg|max:4096'
            ]);

            if (!$request->hasFile('image')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No image file provided',
                    'data' => []
                ], 400);
            }

            // Save image
            $imagePath = $request->file('image')->store('voter_cards_images', 'public');

            // Save voter card image record
            $voterCardImage = new VoterCardImage();
            $voterCardImage->user_id = auth()->id();
            $voterCardImage->image = $imagePath;
            $voterCardImage->reg_no = null;
            $voterCardImage->exit_poll = null;
            $voterCardImage->save();

            // Return success message
            return response()->json([
                'status' => 'success',
                'message' => 'Voting card successfully captured. We are processing it for results which will be displayed on the listing page.',
                'data' => [
                    'id' => $voterCardImage->id,
                    'image' => asset('storage/' . $imagePath),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'data' => ['exception' => $e->getMessage()]
            ], 500);
        }
    } 

    /**
     * Detect marked party by analyzing dark pixels (checkmarks are black/dark)
     */
    private function detectMarkedParty($fullPath)
    {
        $img = imagecreatefromstring(file_get_contents($fullPath));  
        $width = imagesx($img);
        $height = imagesy($img);
        
        // More precise EXIT POLL area targeting
        $y1 = intval($height * 0.81); 
        $y2 = intval($height * 0.93);
        $regionHeight = $y2 - $y1;

        // Define checkbox regions - adjusted based on actual layout
        // The checkboxes are roughly evenly spaced after "EXIT POLL:" text
        $regions = [
            'FNM' => [
                'x' => intval($width * 0.168), 
                'y' => $y1, 
                'width' => intval($width * 0.048), 
                'height' => $regionHeight
            ],
            'PLP' => [
                'x' => intval($width * 0.245), 
                'y' => $y1, 
                'width' => intval($width * 0.048), 
                'height' => $regionHeight
            ],
            'COI' => [
                'x' => intval($width * 0.314), 
                'y' => $y1, 
                'width' => intval($width * 0.048), 
                'height' => $regionHeight
            ],
            'UNK' => [
                'x' => intval($width * 0.390), 
                'y' => $y1, 
                'width' => intval($width * 0.048), 
                'height' => $regionHeight
            ],
        ];
    
        $detectionResults = [];
        $maxRedScore = 0;
        $marked = null;

        foreach ($regions as $name => $region) {
            $redPixels = 0;
            $totalPixels = 0;
            $edgeRedPixels = 0; // Count red pixels on edges (border detection)

            // Check entire region
            for ($i = $region['x']; $i < $region['x'] + $region['width']; $i++) {
                for ($j = $region['y']; $j < $region['y'] + $region['height']; $j++) {
                    if ($i >= $width || $j >= $height) continue;

                    $rgb = imagecolorat($img, $i, $j);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;
                    
                    // Detect RED color (for red box/border)
                    // Red should be dominant: R > 140, and R is significantly higher than G and B
                    $isRed = ($r > 140 && $g < 120 && $b < 120 && $r > ($g + 30));
                    
                    if ($isRed) {
                        $redPixels++;
                        
                        // Check if this is on the edge (border pixels are more important)
                        $isEdge = (
                            $i <= $region['x'] + 5 || 
                            $i >= $region['x'] + $region['width'] - 5 ||
                            $j <= $region['y'] + 5 || 
                            $j >= $region['y'] + $region['height'] - 5
                        );
                        
                        if ($isEdge) {
                            $edgeRedPixels++;
                        }
                    }
                    $totalPixels++;
                }
            }

            // Calculate red percentage
            $redPercentage = $totalPixels > 0 ? ($redPixels / $totalPixels) * 100 : 0;
            
            // Score combines total red pixels and edge emphasis
            $score = $redPixels + ($edgeRedPixels * 2); // Weight edge pixels more
            
            $detectionResults[$name] = [
                'red_pixels' => $redPixels,
                'edge_red_pixels' => $edgeRedPixels,
                'total_pixels' => $totalPixels,
                'red_percentage' => round($redPercentage, 2),
                'score' => $score
            ];

            // Track region with highest red score
            if ($score > $maxRedScore) {
                $maxRedScore = $score;
                $marked = $name;
            }
        }

        imagedestroy($img);

        // Log detection results for debugging
        \Log::info('Party Detection Results:', $detectionResults);
        \Log::info('Selected Party: ' . ($marked ?? 'NONE') . ' with score: ' . $maxRedScore);

        // Set minimum threshold - need at least 100 red pixels or score > 150
        if ($maxRedScore < 150 || ($detectionResults[$marked]['red_pixels'] ?? 0) < 80) {
            \Log::warning('No clear mark detected. Max score: ' . $maxRedScore);
            return null;
        }

        return $marked;
    }

   

    public function get_voter_card_images(Request $request)
    { 
        $query = VoterCardImage::query()
            ->leftJoin('voters', 'voters.voter', '=', 'voter_cards_images.reg_no') // fixed table name
            ->orderBy('voter_cards_images.id', 'desc') // fixed table name
            ->select('voter_cards_images.*', 'voters.surname as voter_surname', 'voters.first_name as voter_first_name', 'voters.second_name as voter_second_name');
    
        // Filter by reg_no if provided
        if ($request->has('reg_no') && !empty($request->get('reg_no'))) {
            $query->where('voter_cards_images.reg_no', (int)$request->get('reg_no')); // fixed table name
        }
    
        // Filter by party (exit_poll) if provided
        if ($request->has('party') && !empty($request->get('party'))) {
            $query->whereRaw('LOWER(voter_cards_images.exit_poll) = ?', [strtolower($request->get('party'))]); // fixed table name
        }
    
        $voterCardImages = $query->paginate($request->get('per_page', 10));
    
        return response()->json([
            'success' => true, 
            'data' => $voterCardImages
        ]);
    }
    

  


}
