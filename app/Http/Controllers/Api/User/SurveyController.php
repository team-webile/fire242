<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Survey;
use App\Models\Voter;
use Illuminate\Http\Request;
use App\Models\DropdownType;
use App\Models\Constituency;
use Illuminate\Support\Facades\Cache;
use App\Models\Party;
use App\Models\UnregisteredVoter;
use Illuminate\Support\Facades\Validator;
use App\Models\Country;
use App\Models\User;
use App\Models\DailySurveyTrack;
use App\Models\SystemSetting;
use App\Models\Question;
use App\Models\SurveyAnswer;
use App\Models\Answer;
use App\Models\CallCenter;
use App\Models\CallCenterAnswer;
use Illuminate\Support\Facades\Log;
use DB;

class SurveyController extends Controller
{
 

    public function getQuestionsAnswers() 
    {
        $questions = Question::with(['answers:id,question_id,answer,position'])->orderBy('position', 'asc')->get();
        return response()->json(['success' => true, 'questions' => $questions]); 
    }

  
    public function getSuggestions(Request $request)
    {
        $term = trim(preg_replace('/\s+/', ' ', $request->query('term')));
        $type = $request->query('type', 'voterId');
        $user = auth()->user();
    
        if (empty($term) || strlen($term) < 3) {
            return response()->json([
                'success' => true,
                'suggestions' => [],
                'message' => 'No suggestions: search term too short.'
            ]);
        }
    
        // Get user's assigned constituencies
        $constituencyIds = explode(',', $user->constituency_id);
        if (empty($constituencyIds) || (count($constituencyIds) === 1 && empty($constituencyIds[0]))) {
            return response()->json([
                'success' => true,
                'suggestions' => [],
                'message' => 'No suggestions: user has no assigned constituencies.'
            ]);
        }
    
        $query = Voter::query()
            ->select('voters.*', 'constituencies.name as constituency_name')
            ->join('constituencies', 'voters.const', '=', 'constituencies.id');

            if($request->type !== 'national'){
                $query->whereIn('voters.const', $constituencyIds);
            } 


            
            if($request->type == 'national'){

                $nationalSearchType = $request->nationalSearchType;
                $firstName = trim($request->query('firstName'));
                $lastName = trim($request->query('lastName'));
                switch ($nationalSearchType) { 

                    case 'nationalVoterId':
                        $query->where('voter', 'ILIKE', $term . '%');
                        break;

                    case 'nationalVoterName':
                        // If firstName or lastName provided, use them for search
                        if (!empty($firstName) || !empty($lastName)) {
                            // Both first name and last name
                            if (!empty($firstName) && !empty($lastName)) {
                                $query->whereRaw('LOWER(voters.first_name) LIKE ?', ['%' . strtolower($firstName) . '%'])
                                      ->whereRaw('LOWER(voters.surname) LIKE ?', ['%' . strtolower($lastName) . '%']);
                            } 
                            // Only first name
                            else if (!empty($firstName)) {
                                $query->whereRaw('LOWER(voters.first_name) LIKE ?', ['%' . strtolower($firstName) . '%']);
                            }
                            // Only last name
                            else if (!empty($lastName)) {
                                $query->whereRaw('LOWER(voters.surname) LIKE ?', ['%' . strtolower($lastName) . '%']);
                            }
                        } else {
                            // fallback to search_vector if no first/last name provided
                            $query->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [$term])
                                  ->orderByRaw("ts_rank(search_vector, plainto_tsquery('simple', ?)) DESC", [$term]);
                        }
                        break;

                    case 'nationalVoterAddress':
                        // Fallback: address full-text using inline vector
                        $tsQuery = implode(' & ', explode(' ', $term));
                        $query->whereRaw("
                            to_tsvector('simple',
                                coalesce(house_number, '') || ' ' ||
                                coalesce(aptno, '') || ' ' ||
                                coalesce(blkno, '') || ' ' ||
                                coalesce(address, '') || ' ' ||
                                coalesce(pobse, '') || ' ' ||
                                coalesce(pobis, '')
                            ) @@ to_tsquery('simple', ?)
                        ", [$tsQuery])
                        ->orderByRaw("ts_rank(to_tsvector('simple',
                            coalesce(house_number, '') || ' ' ||
                            coalesce(aptno, '') || ' ' ||
                            coalesce(blkno, '') || ' ' ||
                            coalesce(address, '') || ' ' ||
                            coalesce(pobse, '') || ' ' ||
                            coalesce(pobis, '')
                        ), to_tsquery('simple', ?)) DESC", [$tsQuery]);
                        break;
                }

            }else{
       
                switch ($type) {
                    
                    case 'voterId':
                         
                        $query->where('voters.voter', 'ILIKE', $term . '%'); 
                        break;

                    case 'name':
                        $firstName = trim($request->query('firstName'));
                        $lastName = trim($request->query('lastName'));
                      
                        if (!empty($firstName) || !empty($lastName)) {
                            // Both first name and last name
                            if (!empty($firstName) && !empty($lastName)) {
                                $query->whereRaw('LOWER(voters.first_name) LIKE ?', ['%' . strtolower($firstName) . '%'])
                                      ->whereRaw('LOWER(voters.surname) LIKE ?', ['%' . strtolower($lastName) . '%']);
                            }
                            // Only first name
                            else if (!empty($firstName)) {
                                $query->whereRaw('LOWER(voters.first_name) LIKE ?', ['%' . strtolower($firstName) . '%']);
                            }
                            // Only last name
                            else if (!empty($lastName)) {
                                $query->whereRaw('LOWER(voters.surname) LIKE ?', ['%' . strtolower($lastName) . '%']);
                            }
                        } else {
                            // fallback to search_vector if no first/last name provided
                            $query->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [$term])
                                  ->orderByRaw("ts_rank(search_vector, plainto_tsquery('simple', ?)) DESC", [$term]);
                        }
                        break;

                    case 'address':
                        // Fallback: address full-text using inline vector
                        $tsQuery = implode(' & ', explode(' ', $term));
                        $query->whereRaw("
                            to_tsvector('simple',
                                coalesce(house_number, '') || ' ' ||
                                coalesce(aptno, '') || ' ' ||
                                coalesce(blkno, '') || ' ' ||
                                coalesce(address, '') || ' ' ||
                                coalesce(pobse, '') || ' ' ||
                                coalesce(pobis, '')
                            ) @@ to_tsquery('simple', ?)
                        ", [$tsQuery])
                        ->orderByRaw("ts_rank(to_tsvector('simple',
                            coalesce(house_number, '') || ' ' ||
                            coalesce(aptno, '') || ' ' ||
                            coalesce(blkno, '') || ' ' ||
                            coalesce(address, '') || ' ' ||
                            coalesce(pobse, '') || ' ' ||
                            coalesce(pobis, '')
                        ), to_tsquery('simple', ?)) DESC", [$tsQuery]);
                        break;
                }

            }
            

    
       
    
        $voters = $query->limit(150)->get();
    
        // Eager load survey data
        $voterIds = $voters->pluck('id')->toArray();
        $surveys = Survey::whereIn('voter_id', $voterIds)
            ->with(['user:id,name,email,constituency_id'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('voter_id');
             
        $suggestions = $voters->map(function ($voter) use ($surveys, $type, $constituencyIds) {
            // If type is national, and the voter's constituency is in user's constituencies, return nothing for this voter
            if ($type === 'national' && in_array($voter->const, $constituencyIds)) {
                return null;
            }

            $is_national = $type === 'national' && !in_array($voter->const, $constituencyIds);

            $voterSurveys = $surveys->get($voter->id, collect([]));
            $latestSurvey = $voterSurveys->first();

            $questionAnswerArray = [];
            if ($latestSurvey) {
                $surveyAnswers = \DB::table('survey_answers')
                    ->where('survey_id', $latestSurvey->id)
                    ->get();

                $questionIds = $surveyAnswers->pluck('question_id')->unique();
                $answerIds = $surveyAnswers->pluck('answer_id')->unique();

                $questions = \DB::table('questions')->whereIn('id', $questionIds)->get(['id', 'question']);
                $answers = \DB::table('answers')->whereIn('id', $answerIds)->get(['id', 'answer']);

                foreach ($surveyAnswers as $answer) {
                    $question = $questions->where('id', $answer->question_id)->first();
                    $answerObj = $answers->where('id', $answer->answer_id)->first();

                    if ($question && $answerObj) {
                        $questionAnswerArray[] = [
                            'question' => $question->question,
                            'answer' => $answerObj->answer
                        ];
                    }
                }
            }

            return [
                'voterId' => $voter->voter,
                'is_national' => $is_national,
                'dob' => $voter->dob,
                'name' => trim($voter->first_name . ' ' . ($voter->second_name ?? '') . ' ' . $voter->surname),
                'address' => $voter->address,
                'constituency_name' => $voter->constituency_name,
                'latest_survey' => $latestSurvey ? [
                    'user' => optional($latestSurvey->user)->only(['id', 'name', 'email', 'constituency_id']),
                    'survey_data' => [
                        'latestSurvey' => $latestSurvey,
                        'survey_count' => $voterSurveys->count(),
                        'question_answers' => $questionAnswerArray
                    ]
                ] : null
            ];
        })->filter()->values(); // remove null results
    
        return response()->json([
            'success' => true,
            'suggestions' => $suggestions
        ]);
    }
    
    // public function getSuggestions(Request $request)
    // {  
    //     $term = $request->query('term');
    //     $type = $request->query('type', 'voterId');
    //     $user_id = auth()->id();

    //     if (empty($term) || strlen($term) < 3) {
    //         return response()->json([
    //             'success' => true,
    //             'suggestions' => []
    //         ]); 
    //     }

    //     // Get user's assigned constituencies
    //     $constituencyIds = explode(',', auth()->user()->constituency_id);

    //     // Check if constituency IDs are valid
    //     if (empty($constituencyIds) || (count($constituencyIds) === 1 && empty($constituencyIds[0]))) {
    //         return response()->json([
    //             'success' => true,
    //             'suggestions' => []
    //         ]);
    //     }

    //     // Include user ID and timestamp in cache key to avoid conflicts
    //     $cacheKey = "suggestions_{$type}_{$term}_{$user_id}_" . implode('_', $constituencyIds) . "_" . now()->timestamp;

    //     // Get suggestions from cache or generate new ones
    //     $suggestions = Cache::remember($cacheKey, now()->addMinutes(2), function() use ($type, $term, $constituencyIds) {
    //         $query = Voter::query()
    //             ->select([
    //                 'voters.*',
    //                 'constituencies.name as constituency_name'
    //             ])
    //             ->join('constituencies', 'voters.const', '=', 'constituencies.id')
    //             ->whereIn('voters.const', $constituencyIds);

    //         switch ($type) {
    //             case 'voterId':
    //                 $query->where('voter', 'LIKE', '%' . $term . '%'); // Changed to prefix match for index usage
    //                 break;
    //             case 'name':
    //                 $terms = explode(' ', $term);
    //                 if (count($terms) > 1) {
    //                     $firstName = strtolower($terms[0]);
    //                     $surname = strtolower($terms[1]);
                        
    //                     $query->whereRaw('LOWER(first_name) LIKE ? AND LOWER(surname) LIKE ?', [
    //                         $firstName . '%', // Changed to prefix match
    //                         $surname . '%'
    //                     ])
    //                     ->orderBy('first_name')
    //                     ->orderBy('surname');
    //                 } else {
    //                     $searchTerm = strtolower($term);
    //                     $query->where(function($q) use ($searchTerm) {
    //                         $q->whereRaw('LOWER(first_name) LIKE ?', [$searchTerm . '%'])
    //                           ->orWhereRaw('LOWER(surname) LIKE ?', [$searchTerm . '%']);
    //                     })
    //                     ->orderBy('first_name')
    //                     ->orderBy('surname');
    //                 }
    //                 break;
    //             case 'address':
    //                 $searchTerm = strtolower($term);
    //                 $query->where(function($q) use ($searchTerm) {
    //                     $q->whereRaw('LOWER(house_number) LIKE ? OR
    //                                 LOWER(aptno) LIKE ? OR 
    //                                 LOWER(blkno) LIKE ? OR
    //                                 LOWER(address) LIKE ? OR
    //                                 LOWER(pobse) LIKE ? OR 
    //                                 LOWER(pobis) LIKE ?', 
    //                         array_fill(0, 6, $searchTerm . '%'))
    //                       ->orderBy('address')
    //                       ->orderBy('house_number');
    //                 });
    //                 break;
    //         }

    //         // Limit query results for better performance
    //         $voters = $query->limit(150)->get();

    //         // Eager load survey data in a single query
    //         $voterIds = $voters->pluck('id')->toArray();
    //         $surveys = Survey::whereIn('voter_id', $voterIds)
    //             ->with(['user:id,name,email,constituency_id'])
    //             ->orderBy('created_at', 'desc')
    //             ->get()
    //             ->groupBy('voter_id');

    //         return $voters->map(function($voter) use ($surveys) {
    //             $voterSurveys = $surveys->get($voter->id, collect([]));
    //             $latestSurvey = $voterSurveys->first();



                
    //             $surveyData = null;
    //             if ($latestSurvey) {
    //                 $surveyData = [
    //                     'user' => [
    //                         'id' => $latestSurvey->user->id,
    //                         'name' => $latestSurvey->user->name,
    //                         'email' => $latestSurvey->user->email,
    //                         'constituency_id' => $latestSurvey->user->constituency_id,
    //                     ],
    //                     'survey_data' => [
    //                         'latestSurvey' => $latestSurvey,
    //                         'survey_count' => $voterSurveys->count()
    //                     ]
    //                 ];
    //             }

    //             return [
    //                 'voterId' => $voter->voter,
    //                 'name' => $voter->first_name . ' ' . $voter->surname,
    //                 'address' => $voter->address,
    //                 'constituency_name' => $voter->constituency_name,
    //                 'latest_survey' => $surveyData
    //             ]; 
    //         });
    //     });

    //     return response()->json([
    //         'success' => true,
    //         'suggestions' => $suggestions
    //     ]);
    // }

 
 
 


    public function getUserConstituency()
    {
        // Get constituency_id as string and convert to array of integers
        $constituencyIds = explode(',', auth()->user()->constituency_id);
         
        $constituency = Constituency::select('id', 'name')
            ->whereIn('id', $constituencyIds)
            ->orderBy('position', 'asc')
            ->get();


        $myConstituency = Constituency::select('id', 'name')
            ->whereIn('id', $constituencyIds)
            ->orderBy('position', 'asc')
            ->get();

        if ($constituency->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No constituency found for this user'
            ], 404);
        }
 
        return response()->json([
            'success' => true,
            'data' => $constituency,
             
        ]);
    }
    public function index(Request $request)
    {
        // Check if user is authenticated and has admin role
        if (!auth()->check() || auth()->user()->role->name !== 'User') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - User access required'
            ], 403);
        }

        $existsInDatabase = $request->input('exists_in_database');
        $challenge = $request->input('challenge');
        $voting_for = $request->input('voting_for');
        $query = Survey::with('voter');

        if ($challenge === 'true') {
            $query->whereRaw('challenge IS TRUE');
        } elseif ($challenge === 'false') {
            $query->whereRaw('challenge IS FALSE');
        }

        // Fix: Correct query syntax for Postgres boolean fields
        if ($existsInDatabase === 'true') {
            $query->whereHas('voter', function($q) {
                $q->where('exists_in_database', true);
            });
        } elseif ($existsInDatabase === 'false') {
            $query->whereHas('voter', function($q) {
                $q->where('exists_in_database', false);
            });
        }

        if ($request->has('voting_decision')) {
            $query->where('voting_decision', $request->voting_decision);
        }

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
                $query->where('voting_for', $voting_for);
            }
        }

        if($request->has('is_died')){
            $query->where('is_died',$request->is_died);
        }
        if($request->has('died_date')){
            $query->where('died_date', $request->died_date);
        }

        if ($request->has('employed')) {
            $query->where('employed', filter_var($request->employed, FILTER_VALIDATE_BOOLEAN));
        }

        // if ($request->has('children')) {
        //     $query->where('children', filter_var($request->children, FILTER_VALIDATE_BOOLEAN));
        // }

        // if ($request->has('employment_type')) {
        //     $query->whereRaw('LOWER(employment_type) = ?', [strtolower($request->employment_type)]);
        // }

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
                $q->where('voter', $request->voter_id);
            });
        }

        if ($request->has('constituency_id')) {
            $query->whereHas('voter', function($q) use ($request) {
                $q->where('const', $request->constituency_id);
            });
        }

        if ($request->has('first_name')) {
            $query->whereHas('voter', function($q) use ($request) {
                $q->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [strtolower($request->first_name)]);
            });
        }

        if ($request->has('last_name')) {
            $query->whereHas('voter', function($q) use ($request) {
                $q->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [strtolower($request->last_name)]);
            });
        }

        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->start_date . ' 00:00:00');
        }

        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->end_date . ' 23:59:59');
        }

        // Get paginated results
        $surveys = $query->where('user_id', auth()->user()->id)->orderBy('id', 'desc')->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $surveys,
        ]);
    }

    
    
    public function store(Request $request)
    { 
        try {
            if (!auth()->check() || auth()->user()->role->name !== 'User') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - User access required'
                ], 403);
            }

            try {
                if($request->is_died){
                    try {
                        $surveyData = [
                            'voter_id' => $request->voter_id,
                            'user_id' => auth()->user()->id,
                            'is_died' => 1,
                            'died_date' => $request->died_date ?? null, 
                        ]; 

                        // Validate data types before saving
                        if (!is_numeric($surveyData['voter_id'])) {
                            throw new \Exception('Invalid voter_id format - must be numeric');
                        }
                        if (!is_numeric($surveyData['user_id'])) {
                            throw new \Exception('Invalid user_id format - must be numeric');
                        }
                        if (!is_bool($surveyData['is_died']) && !is_numeric($surveyData['is_died'])) {
                            throw new \Exception('Invalid is_died format - must be boolean or numeric');
                        }
                        // if (!strtotime($surveyData['died_date'])) {
                        //     throw new \Exception('Invalid died_date format');
                        // }

                        $survey = Survey::create($surveyData);

                    } catch (\PDOException $e) {
                        \Log::error('Database error while creating died survey: ' . $e->getMessage());
                        return response()->json([
                            'success' => false,
                            'message' => 'Database error occurred',
                            'error' => $e->getMessage()
                        ], 500);
                    } catch (\Exception $e) {
                        \Log::error('Error while creating died survey: ' . $e->getMessage());
                        return response()->json([
                            'success' => false,
                            'message' => 'Error processing data',
                            'error' => $e->getMessage() 
                        ], 500);
                    }

                }elseif($request->call_center){

                    $validator = Validator::make($request->all(), [
                        'voter_id' => 'required|exists:voters,id',
                        'call_center_caller_id' => 'nullable',
                        'call_center_caller_name' => 'nullable|string',
                        'call_center_voter_name' => 'nullable|string',
                        'call_center_date_time' => 'nullable|string',
                        'call_center_email' => 'nullable|email',
                        'call_center_phone' => 'nullable|string',
                        'call_center_follow_up' => 'nullable|string',
                        'call_center_list_voter_contacts' => 'nullable|string',
                        'call_center_number_called' => 'nullable',
                        'call_center_number_calls_made' => 'nullable',
                        'call_center_soliciting_volunteers' => 'nullable|string',
                        'call_center_address_special_concerns' => 'nullable|string',
                        'call_center_voting_decisions' => 'nullable|string',
                        'call_center_other_comments' => 'nullable|string'
                    ]);

                    if ($validator->fails()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Validation failed',
                            'errors' => $validator->errors()
                        ], 422);
                    }

                    try {
                        $payload = [
                            'call_center_caller_id' => $request->input('call_center_caller_id'),
                            'call_center_caller_name' => $request->input('call_center_caller_name'),
                            'call_center_voter_name' => $request->input('call_center_voter_name'),
                            'call_center_date_time' => $request->input('call_center_date_time'),
                            'call_center_email' => $request->input('call_center_email'),
                            'call_center_phone' => $request->input('call_center_phone'),
                            'call_center_follow_up' => $request->input('call_center_follow_up'),
                            'call_center_list_voter_contacts' => $request->input('call_center_list_voter_contacts'),
                            'call_center_number_called' => $request->input('call_center_number_called'), 
                            'call_center_voting_for' => $request->input('call_center_voting_for'),
                            'call_center_number_calls_made' => $request->input('call_center_number_calls_made'),
                            'call_center_soliciting_volunteers' => $request->input('call_center_soliciting_volunteers'),
                            'call_center_address_special_concerns' => $request->input('call_center_address_special_concerns'),
                            'call_center_voting_decisions' => $request->input('call_center_voting_decisions'),
                            'user_id' => auth()->user()->id, 
                            'call_center_other_comments' => $request->input('call_center_other_comments'),
                        ];

                        $callCenter = CallCenter::updateOrCreate(
                            ['voter_id' => $request->voter_id],
                            $payload
                        );


                        $this->storeCallCenterAnswers($request, $callCenter);

                        $message = $callCenter->wasRecentlyCreated
                            ? 'Call center record created successfully'
                            : 'Call center record updated successfully';

                        return response()->json([
                            'success' => true,
                            'message' => $message,
                            'data' => ['call_center' => $callCenter]
                        ], 200);
                    } catch (\Exception $e) {
                        \Log::error('Error saving call center record: ' . $e->getMessage());
                        return response()->json([
                            'success' => false,
                            'message' => 'Error saving call center data',
                            'error' => $e->getMessage()
                        ], 500);
                    }
                }else { 

                    \Log::info('Survey store request:', [
                        'user_id' => auth()->id(),
                        'request_data' => $request->all()
                    ]); 
    
                    $createdVoters = [];
                    
                    // Validate survey data and user data
                    $validator = \Validator::make($request->all(), [
                        'voter_id' => 'required|exists:voters,id',
                        'sex' => 'required|string',
                        // 'marital_status' => 'required|string',
                        // 'employed' => 'required|string',
                        // 'children' => 'required|string', 
                        //'employment_type' => 'required_if:employed,Yes|string',
                        //'employment_sector' => 'required_if:employment_type,Private Sector,Government|string', 
     
    
                        // 'religion' => 'required|string',
                        'located' => 'required|string|in:Main Island,Off Island,Outside Country',
                        'island' => 'required_if:located,Off Island|prohibited_if:located,Main Island,Outside Country',
                        'country' => 'required_if:located,Outside Country|prohibited_if:located,Main Island,Off Island',
                        'country_location' => 'required_if:located,Outside Country|prohibited_if:located,Main Island,Off Island',
    
                        'home_phone_code' => 'nullable|string',
                        'home_phone' => 'nullable|string',
                        'work_phone_code' => 'nullable|string',
                        'note' => 'nullable|string',
                        'work_phone' => 'nullable|string',
                        'cell_phone_code' => 'nullable|string',
                        'cell_phone' => 'nullable|string', 
                        //'voting_for' => 'required|string',
                        // 'last_voted' => 'required|string',
                        // 'voted_for_party' => 'required_if:last_voted,Yes|string',
                        // 'voted_where' => 'required_if:last_voted,Yes|string',
                        // 'voted_in_house' => 'required|string', 
                        'email' => 'nullable|email', 
                        'special_comments' => 'nullable|string',
                        'other_comments' => 'nullable|string',
                        'voter_image' => 'nullable|string', 
                        'house_image' => 'nullable|string',
                        'voters_in_house' => 'nullable|string',
                        'unregistered_voters' => 'nullable|array',
                        'unregistered_voters.*.first_name' => 'required_with:unregistered_voters|string',
                        'unregistered_voters.*.last_name' => 'required_with:unregistered_voters|string',
                        'unregistered_voters.*.dob' => 'required_with:unregistered_voters|date',
                        'unregistered_voters.*.gender' => 'required_with:unregistered_voters|string',
                        'unregistered_voters.*.address' => 'required_with:unregistered_voters|string',
                        'unregistered_voters.*.email' => 'required_with:unregistered_voters|email',
                        'unregistered_voters.*.phone' => 'required_with:unregistered_voters|string',
                        'unregistered_voters.*.residentType' => 'required_with:unregistered_voters|string',
                        'unregistered_voters.*.registrationStatus' => 'required_with:unregistered_voters|string'
                    ]); 
     
        
                    if ($validator->fails()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Validation failed',
                            'errors' => $validator->errors()
                        ], 422);
                    } 
        
                    try {
                        // First create the survey record
                        $surveyData = [ 
                            'voter_id' => $request->voter_id,
                            'user_id' => auth()->user()->id,
                            'sex' => $request->sex,
                            'marital_status' => $request->marital_status,
                            'employed' => $request->employed,
                            'children' => $request->children,
                            'employment_type' => $request->employment_type,
                            'employment_sector' => $request->employment_sector,
                            'religion' => $request->religion,
                            'located' => $request->located,
                            'island' => $request->located === 'Off Island' ? $request->island : null,
                            'country' => $request->located === 'Outside Country' ? $request->country : null,
                            'country_location' => $request->located === 'Outside Country' ? $request->country_location : null,
                            'home_phone_code' => $request->home_phone_code,
                            'home_phone' => $request->home_phone,
                            'work_phone_code' => $request->work_phone_code,
                            'work_phone' => $request->work_phone,
                            'cell_phone_code' => $request->cell_phone_code,
                            'cell_phone' => $request->cell_phone,
                            'email' => $request->email,
                            'special_comments' => $request->special_comments,
                            'other_comments' => $request->other_comments,
                            'voting_for' => $request->voting_for,
                            'last_voted' => $request->last_voted,
                            'voted_for_party' => $request->voted_for_party,
                            'voted_where' => $request->voted_where,
                            'voted_in_house' => $request->voted_in_house,
                            'voters_in_house' => $request->voters_in_house, 
                            'note' => $request->note,
                            'voting_decision' => $request->voting_decision   
                        ]; 
            
                        // Validate data types
                        if (!is_numeric($surveyData['voter_id'])) {
                            throw new \Exception('Invalid voter_id format');
                        }
                        // if (!is_string($surveyData['sex'])) {
                        //     throw new \Exception('Invalid sex format');
                        // }
                        // if (!is_string($surveyData['marital_status'])) {
                        //     throw new \Exception('Invalid marital_status format');
                        // }
                        // if (!is_string($surveyData['employed'])) {
                        //     throw new \Exception('Invalid employed format');
                        // }

                        // Handle images if present
                        if ($request->hasFile('voter_image')) {
                            try {
                                $voterImagePath = $request->file('voter_image')->store('surveys/voter_images', 'public');
                                $surveyData['voter_image'] = $voterImagePath;
                            } catch (\Exception $e) {
                                \Log::error('Error uploading voter image: ' . $e->getMessage());
                                throw new \Exception('Error uploading voter image');
                            }
                        } 
            
                        if ($request->hasFile('home_image')) {
                            try {
                                $homeImagePath = $request->file('home_image')->store('surveys/home_images', 'public');
                                $surveyData['home_image'] = $homeImagePath;
                            } catch (\Exception $e) {
                                \Log::error('Error uploading home image: ' . $e->getMessage());
                                throw new \Exception('Error uploading home image');
                            }
                        }
             
                        if ($request->survey_id) {
                            try {
                                $existingSurvey = Survey::find($request->survey_id);
                            
                                if ($existingSurvey) {
                                    $hasChanges = false;
                                    $changedKeys = [];
        
                                    // Compare survey data fields
                                    foreach ($surveyData as $key => $value) {
                                        // Skip fields that don't need comparison
                                        if (in_array($key, ['created_at', 'updated_at','voter_image','home_image','unregistered_voters','user_id','survey_id','note'])) {
                                            continue;
                                        }
        
                                        // Compare field values
                                        if ($existingSurvey->$key !== $value) { 
                                            $hasChanges = true;
                                            $changedKeys[] = [
                                                'key' => $key,
                                                'old_value' => $existingSurvey->$key,
                                                'new_value' => $value
                                            ];
                                        }
                                    }
        
                                    // Compare survey answers
                                    $existingAnswers = SurveyAnswer::where('survey_id', $existingSurvey->id)
                                        ->select('question_id', 'answer_id')
                                        ->get();
        
                                    if ($request->has('questions') && is_array($request->questions)) {
                                        foreach ($request->questions as $questionData) {
                                            if (isset($questionData['question_id']) && isset($questionData['answer_id'])) {
                                                $matchingAnswer = $existingAnswers->first(function($answer) use ($questionData) {
                                                    return $answer->question_id == $questionData['question_id'] && 
                                                           $answer->answer_id == $questionData['answer_id']; 
                                                });
        
                                                if (!$matchingAnswer) {
                                                    $hasChanges = true;
                                                    $changedKeys[] = [
                                                        'key' => 'survey_answer',
                                                        'old_value' => null,
                                                        'new_value' => "Question ID: {$questionData['question_id']}, Answer ID: {$questionData['answer_id']}"
                                                    ];
                                                }
                                            }
                                        }
                                    }
        
                                    if (!$hasChanges) {
                                        // No changes detected, use existing survey
                                        $survey = $existingSurvey;
                                    } else {
                                        // Changes detected, create new survey
                                        $survey = Survey::create($surveyData);
                                        $this->storeSurveyAnswers($request, $survey);
                                        $this->trackDailySurvey();
                                    }
                                } else {
                                    // Survey ID not found, create new survey
                                    $survey = Survey::create($surveyData);
                                    $this->storeSurveyAnswers($request, $survey);
                                    $this->trackDailySurvey();
                                }
                            } catch (\PDOException $e) {
                                \Log::error('Database error while processing survey: ' . $e->getMessage());
                                throw new \Exception('Database error occurred');
                            }
                        } else {
                            // No survey ID provided, create new survey
                            try {
                                $survey = Survey::create($surveyData);
                                $this->storeSurveyAnswers($request, $survey);
                                $this->trackDailySurvey();
                            } catch (\PDOException $e) {
                                \Log::error('Database error while creating new survey: ' . $e->getMessage());
                                throw new \Exception('Database error occurred');
                            }
                        }
                    } catch (\Exception $e) {
                        \Log::error('Error processing survey data: ' . $e->getMessage());
                        throw new \Exception('Error processing survey data: ' . $e->getMessage());
                    }
                }
               
                try {
                    $createdVoters = []; 
                    if ($request->unregistered_voters) {
                        // No need for json_decode since data is already an array
                        $unregistered_voters = $request->unregistered_voters;
                        foreach ($unregistered_voters as $key => $userData) {
                            
                            // Validate each user's data with updated field names
                            $validator = Validator::make($userData, [
                             
                                'first_name' => 'required|string|max:255',
                                'last_name' => 'required|string|max:255',
                                'dob' => 'required|date',
                                'gender' => 'required|string',
                                'phone' => 'required|string|max:20',
                                'email' => 'nullable|email|max:255',
                                'address' => 'required|string'
                            ]);  

                            if ($validator->fails()) {
                                return response()->json([
                                    'success' => false,
                                    'message' => 'Validation failed for user at index ' . $key,
                                    'errors' => $validator->errors()
                                ], 422); 
                            }

                            try {
                                // Create unregistered voter record with voter_id from survey
                                $unregisteredVoter = UnregisteredVoter::create([
                                    'voter_id' => $survey->voter_id,
                                    'survey_id' => $survey->id,
                                    'first_name' => $userData['first_name'],
                                    'last_name' => $userData['last_name'],
                                    'date_of_birth' => $userData['dob'],
                                    'gender' => $userData['gender'] ?? null,
                                    'phone_number' => $userData['phone'],
                                    'new_email' => $userData['email'] ?? null,
                                    'new_address' => $userData['address'],
                                    'user_id' => auth()->user()->id 
                                ]);

                                $createdVoters[] = $unregisteredVoter;
                            } catch (\PDOException $e) {
                                \Log::error('Database error while creating unregistered voter: ' . $e->getMessage());
                                throw new \Exception('Database error occurred while creating unregistered voter');
                            }
                        } 
                    } 
                } catch (\Exception $e) {
                    \Log::error('Error processing unregistered voters: ' . $e->getMessage());
                    throw new \Exception('Error processing unregistered voters: ' . $e->getMessage());
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Survey and unregistered voters created successfully',
                    'data' => [
                        'survey' => $survey,
                        'unregistered_voters' => $createdVoters
                    ]
                ], 201); 
      
            } catch (\Exception $e) {
                \Log::error('Error in store method: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Error processing data',
                    'error' => $e->getMessage()
                ], 500);
            }

        } catch (\Exception $e) {
            \Log::error('Authentication error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Authentication error',
                'error' => $e->getMessage()
            ], 401);
        }
    }
    
    // 20/06/2025 code comment
    // public function store(Request $request)
    // {  

    //     if (!auth()->check() || auth()->user()->role->name !== 'User') {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Unauthorized - User access required'
    //         ], 403);
    //     }
    
    //     // First handle unregistered voters if is_register is 1
      
    //         try {

    //             if($request->is_died){


    //                 $surveyData = [
    //                     'voter_id' => $request->voter_id,
    //                     'user_id' => auth()->user()->id,
    //                     'is_died' => 1,
    //                     'died_date' => $request->died_date,
    //                 ]; 
    //                 $survey = Survey::create($surveyData);


    //             }else{ 


    //                 \Log::info('Survey store request:', [
    //                     'user_id' => auth()->id(),
    //                     'request_data' => $request->all()
    //                 ]); 
    
                    
        
    //                 $createdVoters = [];
                    
    //                 // Validate survey data and user data
    //                 $validator = \Validator::make($request->all(), [
    //                     'voter_id' => 'required|exists:voters,id',
    //                     'sex' => 'required|string',
    //                     'marital_status' => 'required|string',
    //                     'employed' => 'required|string',
    //                     'children' => 'required|string',
    //                     //'employment_type' => 'required_if:employed,Yes|string',
    //                     //'employment_sector' => 'required_if:employment_type,Private Sector,Government|string',
     
    
    //                     // 'religion' => 'required|string',
    //                     'located' => 'required|string|in:Main Island,Off Island,Outside Country',
    //                     'island' => 'required_if:located,Off Island|prohibited_if:located,Main Island,Outside Country',
    //                     'country' => 'required_if:located,Outside Country|prohibited_if:located,Main Island,Off Island',
    //                     'country_location' => 'required_if:located,Outside Country|prohibited_if:located,Main Island,Off Island',
    
    //                     'home_phone_code' => 'nullable|string',
    //                     'home_phone' => 'nullable|string',
    //                     'work_phone_code' => 'nullable|string',
    //                     'note' => 'nullable|string',
    //                     'work_phone' => 'nullable|string',
    //                     'cell_phone_code' => 'nullable|string',
    //                     'cell_phone' => 'nullable|string', 
    //                     //'voting_for' => 'required|string',
    //                     // 'last_voted' => 'required|string',
    //                     // 'voted_for_party' => 'required_if:last_voted,Yes|string',
    //                     // 'voted_where' => 'required_if:last_voted,Yes|string',
    //                     // 'voted_in_house' => 'required|string', 
    //                     'email' => 'nullable|email', 
    //                     'special_comments' => 'nullable|string',
    //                     'other_comments' => 'nullable|string',
    //                     'voter_image' => 'nullable|string', 
    //                     'house_image' => 'nullable|string',
    //                     'voters_in_house' => 'nullable|string',
    //                     'unregistered_voters' => 'nullable|array',
    //                     'unregistered_voters.*.first_name' => 'required_with:unregistered_voters|string',
    //                     'unregistered_voters.*.last_name' => 'required_with:unregistered_voters|string',
    //                     'unregistered_voters.*.dob' => 'required_with:unregistered_voters|date',
    //                     'unregistered_voters.*.gender' => 'required_with:unregistered_voters|string',
    //                     'unregistered_voters.*.address' => 'required_with:unregistered_voters|string',
    //                     'unregistered_voters.*.email' => 'required_with:unregistered_voters|email',
    //                     'unregistered_voters.*.phone' => 'required_with:unregistered_voters|string',
    //                     'unregistered_voters.*.residentType' => 'required_with:unregistered_voters|string',
    //                     'unregistered_voters.*.registrationStatus' => 'required_with:unregistered_voters|string'
    //                 ]); 
     
        
    //                 if ($validator->fails()) {
    //                     return response()->json([
    //                         'success' => false,
    //                         'message' => 'Validation failed',
    //                         'errors' => $validator->errors()
    //                     ], 422);
    //                 }
        
    //                 // First create the survey record
    //                 $surveyData = [
    //                     'voter_id' => $request->voter_id,
    //                     'user_id' => auth()->user()->id,
    //                     'sex' => $request->sex,
    //                     'marital_status' => $request->marital_status,
    //                     'employed' => $request->employed,
    //                     'children' => $request->children,
    //                     'employment_type' => $request->employment_type,
    //                     'employment_sector' => $request->employment_sector,
    //                     'religion' => $request->religion,
    //                     'located' => $request->located,
    //                     'island' => $request->located === 'Off Island' ? $request->island : null,
    //                     'country' => $request->located === 'Outside Country' ? $request->country : null,
    //                     'country_location' => $request->located === 'Outside Country' ? $request->country_location : null,
    //                     'home_phone_code' => $request->home_phone_code,
    //                     'home_phone' => $request->home_phone,
    //                     'work_phone_code' => $request->work_phone_code,
    //                     'work_phone' => $request->work_phone,
    //                     'cell_phone_code' => $request->cell_phone_code,
    //                     'cell_phone' => $request->cell_phone,
    //                     'email' => $request->email,
    //                     'special_comments' => $request->special_comments,
    //                     'other_comments' => $request->other_comments,
    //                     'voting_for' => $request->voting_for,
    //                     'last_voted' => $request->last_voted,
    //                     'voted_for_party' => $request->voted_for_party,
    //                     'voted_where' => $request->voted_where,
    //                     'voted_in_house' => $request->voted_in_house,
    //                     'voters_in_house' => $request->voters_in_house,
    //                     'note' => $request->note,
    //                     'voting_decision' => $request->voting_decision   
    //                 ]; 
        
    //                 // Handle images if present
    //                 if ($request->hasFile('voter_image')) {
    //                     $voterImagePath = $request->file('voter_image')->store('surveys/voter_images', 'public');
    //                     $surveyData['voter_image'] = $voterImagePath;
    //                 } 
        
    //                 if ($request->hasFile('home_image')) {
    //                     $homeImagePath = $request->file('home_image')->store('surveys/home_images', 'public');
    //                     $surveyData['home_image'] = $homeImagePath;
    //                 }
         
                   
    
    //                 if ($request->survey_id) {
    //                     $existingSurvey = Survey::find($request->survey_id);
                    
    //                     if ($existingSurvey) {
    //                         $hasChanges = false;
    //                         $changedKeys = [];
    
    //                         // Compare survey data fields
    //                         foreach ($surveyData as $key => $value) {
    //                             // Skip fields that don't need comparison
    //                             if (in_array($key, ['children','employed','created_at', 'updated_at','voter_image','home_image','unregistered_voters','user_id','survey_id','voters_in_house','note'])) {
    //                                 continue;
    //                             }
    
    //                             // Compare field values
    //                             if ($existingSurvey->$key !== $value) {
    //                                 $hasChanges = true;
    //                                 $changedKeys[] = [
    //                                     'key' => $key,
    //                                     'old_value' => $existingSurvey->$key,
    //                                     'new_value' => $value
    //                                 ];
    //                             }
    //                         }
    
    //                         // Compare survey answers
    //                         $existingAnswers = SurveyAnswer::where('survey_id', $existingSurvey->id)
    //                             ->select('question_id', 'answer_id')
    //                             ->get();
    
    //                         if ($request->has('questions') && is_array($request->questions)) {
    //                             foreach ($request->questions as $questionData) {
    //                                 if (isset($questionData['question_id']) && isset($questionData['answer_id'])) {
    //                                     $matchingAnswer = $existingAnswers->first(function($answer) use ($questionData) {
    //                                         return $answer->question_id == $questionData['question_id'] && 
    //                                                $answer->answer_id == $questionData['answer_id']; 
    //                                     });
    
    //                                     if (!$matchingAnswer) {
    //                                         $hasChanges = true;
    //                                         $changedKeys[] = [
    //                                             'key' => 'survey_answer',
    //                                             'old_value' => null,
    //                                             'new_value' => "Question ID: {$questionData['question_id']}, Answer ID: {$questionData['answer_id']}"
    //                                         ];
    //                                     }
    //                                 }
    //                             }
    //                         }
    
    //                         if (!$hasChanges) {
    //                             // No changes detected, use existing survey
    //                             $survey = $existingSurvey;
    //                         } else {
    //                             // Changes detected, create new survey
    //                             $survey = Survey::create($surveyData);
    //                             $this->storeSurveyAnswers($request, $survey);
    //                             $this->trackDailySurvey();
    //                         }
    //                     } else {
    //                         // Survey ID not found, create new survey
    //                         $survey = Survey::create($surveyData);
    //                         $this->storeSurveyAnswers($request, $survey);
    //                         $this->trackDailySurvey();
    //                     }
    //                 } else {
    //                     // No survey ID provided, create new survey
    //                     $survey = Survey::create($surveyData);
    //                     $this->storeSurveyAnswers($request, $survey);
    //                     $this->trackDailySurvey();
    //                 }
                    
                
    

    //             }
               
                
    //             $createdVoters = []; 
    //             if ($request->unregistered_voters) {
    //                 // No need for json_decode since data is already an array
    //                 $unregistered_voters = $request->unregistered_voters;
    //                 foreach ($unregistered_voters as $key => $userData) {
                        
    //                     // Validate each user's data with updated field names
    //                     $validator = Validator::make($userData, [
                         
    //                         'first_name' => 'required|string|max:255',
    //                         'last_name' => 'required|string|max:255',
    //                         'dob' => 'required|date',
    //                         'gender' => 'required|string',
    //                         'phone' => 'required|string|max:20',
    //                         'email' => 'nullable|email|max:255',
    //                         'address' => 'required|string'
    //                     ]);

    //                     if ($validator->fails()) {
    //                         return response()->json([
    //                             'success' => false,
    //                             'message' => 'Validation failed for user at index ' . $key,
    //                             'errors' => $validator->errors()
    //                         ], 422); 
    //                     }

    //                     // Create unregistered voter record with voter_id from survey
    //                     $unregisteredVoter = UnregisteredVoter::create([
    //                         'voter_id' => $survey->voter_id,
    //                         'survey_id' => $survey->id,
    //                         'first_name' => $userData['first_name'],
    //                         'last_name' => $userData['last_name'],
    //                         'date_of_birth' => $userData['dob'],
    //                         'gender' => $userData['gender'],
    //                         'phone_number' => $userData['phone'],
    //                         'new_email' => $userData['email'] ?? null,
    //                         'new_address' => $userData['address'],
    //                         'user_id' => auth()->user()->id 
    //                     ]);

    //                     $createdVoters[] = $unregisteredVoter;
    //                 } 
    //             } 
    //             return response()->json([
    //                 'success' => true,
    //                 'message' => 'Survey and unregistered voters created successfully',
    //                 'data' => [
    //                     'survey' => $survey,
    //                     'unregistered_voters' => $createdVoters
    //                 ]
    //             ], 201); 
      
    //         } catch (\Exception $e) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Error processing data',
    //                 'error' => $e->getMessage()
    //             ], 500);
    //         }
        
    
    //     // ... rest of the code for regular survey creation ...
    // }
    // 20/06/2025 code comment

    public function storeSurveyAnswers(Request $request,$survey)
    {
        if ($request->has('questions') && is_array($request->questions)) {
            foreach ($request->questions as $questionData) {
                if (isset($questionData['question_id']) && isset($questionData['answer_id'])) {
                    SurveyAnswer::create([
                        'survey_id' => $survey->id,
                        'question_id' => $questionData['question_id'],
                        'answer_id' => $questionData['answer_id']
                    ]);
                }
            }
        }
    }
    public function storeCallCenterAnswers(Request $request,$callCenter)
    {
        if ($request->has('questions') && is_array($request->questions)) {
            foreach ($request->questions as $questionData) {
                if (isset($questionData['question_id']) && isset($questionData['answer_id'])) {
                    CallCenterAnswer::create([
                        'call_center_id' => $callCenter->id,
                        'question_id' => $questionData['question_id'],
                        'answer_id' => $questionData['answer_id']
                    ]);
                }
            }
        }
    }
    
    private function trackDailySurvey()
    {
        $userId = auth()->id();
        $today = now()->format('Y-m-d');

        // Try to find existing record for today
        $dailyTrack = DailySurveyTrack::where('user_id', $userId)
            ->where('date', $today)
            ->first();

        $settings = SystemSetting::first();

        if ($dailyTrack) {
            // Increment total if record exists
            $dailyTrack->total_surveys = $dailyTrack->total_surveys + 1;
            $dailyTrack->completion_percentage = ($dailyTrack->total_surveys * $settings->daily_target) / 100;
            $dailyTrack->save();
        } else {
            // Create new record if none exists
            $dailyTrack = DailySurveyTrack::create([
                'user_id' => $userId,
                'date' => $today,
                'total_surveys' => 1,
                'completion_percentage' => (1 * $settings->daily_target) / 100
            ]);
        }

        return $dailyTrack;  
    } 

 
    public function show($id)
    {
        if (!auth()->check() || auth()->user()->role->name !== 'User') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - User access required'
            ], 403);
        }

        $survey = Survey::with('voter')->where('user_id', auth()->id())->find($id);

        if (!$survey) {
            return response()->json([
                'success' => false,
                'message' => 'Survey not found'
            ], 404);
        }

        // Get survey answers with questions and answers
        $surveyAnswers = SurveyAnswer::where('survey_id', $survey->id)->get();
        
        $questionsAndAnswers = [];
        foreach ($surveyAnswers as $surveyAnswer) {
            $question = Question::find($surveyAnswer->question_id);
            $answer = Answer::find($surveyAnswer->answer_id);  
            
            if ($question && $answer) {
                $questionsAndAnswers[]= [
                    'question' => $question->question,
                    'answer' => $answer->answer
                ];
            }
        }

        // Add questions and answers to survey data
        $surveyData = $survey->toArray();
        $surveyData['questions_and_answers'] = $questionsAndAnswers;

        return response()->json([
            'success' => true,
            'data' => $surveyData
        ]);  
    }

    public function update(Request $request, $id)
    {
        if (!auth()->check() || auth()->user()->role->name !== 'User') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - User access required'
            ], 403);
        }

        $validatedData = $request->validate([
            'sex' => 'sometimes|string',
            'marital_status' => 'sometimes|string',
            'employed' => 'sometimes|string',
            'children' => 'sometimes|string',
            'employment_type' => 'sometimes|string',
            'religion' => 'sometimes|string',
            'located' => 'sometimes|string',
            'home_phone' => 'nullable|string',
            'work_phone' => 'nullable|string',
            'cell_phone' => 'nullable|string',
            'email' => 'nullable|email',
            'special_comments' => 'nullable|string',
            'other_comments' => 'nullable|string',
            'voting_for' => 'sometimes|string',
            'voted_in_2017' => 'sometimes|boolean',
            'where_voted_in_2017' => 'nullable|string',
            'voted_in_house' => 'sometimes|string'
        ]);

        $survey = Survey::findOrFail($id);
        $survey->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Survey updated successfully',
            'data' => $survey
        ]);
    }

    public function destroy($id)
    {
        if (!auth()->check() || auth()->user()->role->name !== 'User') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - User access required' 
            ], 403);
        }

        $survey = Survey::findOrFail($id); 
        $survey->delete();

        return response()->json([
            'success' => true,
            'message' => 'Survey deleted successfully'
        ]);
    }
    public function getVoter(Request $request)
    { 
        if (!$request->voterId) {
            return response()->json([
                'success' => false,
                'message' => 'Voter ID is required'
            ], 400);
        }
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Get authenticated user's constituency IDs and handle empty case
        $constituencyId = auth()->user()->constituency_id;
        
        if (empty($constituencyId)) {
            return response()->json([
                'success' => false,
                'message' => 'No constituencies assigned to user'
            ], 400);
        }
        $userConstituencyIds = array_filter(explode(',', $constituencyId)); 
        
        if (empty($userConstituencyIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid constituency IDs'
            ], 400);
        }

        // Get constituencies names
        $constituencies = Constituency::whereIn('id', $userConstituencyIds) 
            ->pluck('id')
            ->toArray();
            
             
        // Get voter and check if pobse matches any constituency name
        $query = Voter::select('voters.*', 'constituencies.name as constituency_name')
            ->join('constituencies', 'voters.const', '=', 'constituencies.id');

            if(isset($request->national) && $request->national  !== 'true' ){
                $query->whereIn('voters.const', $constituencies);
            }

             
     
        if ($request->voterId) {
            $query->where('voter', $request->voterId);
        }
        $underAge25 = $request->input('under_age_25');
        $existsInDatabase = $request->input('exists_in_database');

        // Apply exists_in_database filter
        if ($existsInDatabase === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false);
        }

        

        // if ($request->firstName) {
        //     $query->where('first_name', 'like', '%' . $request->firstName . '%');
        // }
        // if ($request->lastName) {
        //     $query->where('last_name', 'like', '%' . $request->lastName . '%'); 
        // }
        // if ($request->email) {
        //     $query->where('email', 'like', '%' . $request->email . '%');
        // }
        // if ($request->phone) {
        //     $query->where('phone', 'like', '%' . $request->phone . '%');
        // } 

        $voter = $query->first();
       
        // Get all voters at same address from voter_address_mapping
        $addressVoters = [];
        if ($voter) {
            // Find address mapping using voter ID pattern matching
            // Cast voter ID to string to avoid PostgreSQL type mismatch
            $voterId = (string) $voter->voter;
            $addressMapping = DB::table('voter_address_mapping')
                ->where(function($query) use ($voterId) {
                    $query->where('voter_ids', 'LIKE', $voterId)
                          ->orWhere('voter_ids', 'LIKE', $voterId . ',%')
                          ->orWhere('voter_ids', 'LIKE', '%,' . $voterId . ',%')
                          ->orWhere('voter_ids', 'LIKE', '%,' . $voterId);
                })
                ->first();

            if ($addressMapping) {
                // Get array of voter IDs from comma separated string
                $voterIds = array_filter(explode(',', $addressMapping->voter_ids));
                
                // Remove the main voter ID from the list
                $voterIds = array_diff($voterIds, [$voter->voter]);

                if (!empty($voterIds)) {
                    // Get voters data and check if they exist in surveys table
                    $addressVoters = Voter::select(
                            'voters.*', 
                            'constituencies.name as constituency_name',
                            DB::raw('false as has_survey')
                        ) 
                        ->join('constituencies', 'voters.const', '=', 'constituencies.id')
                        ->whereIn('voters.voter', $voterIds)
                        ->whereIn('voters.const', $constituencies)
                        ->get();

                        

                        // $addressVoters = Voter::select(
                        //     'voters.*', 
                        //     'constituencies.name as constituency_name',
                        //     DB::raw('EXISTS (SELECT 1 FROM surveys WHERE surveys.voter_id = voters.id) as has_survey')
                        // ) 
                        // ->join('constituencies', 'voters.const', '=', 'constituencies.id')
                        // ->whereIn('voters.voter', $voterIds)
                        // ->whereIn('voters.const', $constituencies)
                        // ->get();    
                }
            }
        }

        // Add address voters and call center data to voter object
        if ($voter) {
            $voter->address_voters = $addressVoters;
            // Load call center record for this voter (voters.id)
            $voter->call_center = CallCenter::where('voter_id', $voter->id)->first(); 
            $voter->voter_phone_code = $voter->phone_code ?? '';
            $voter->voter_phone = $voter->phone_number ?? '';
        }
 

        if (!$voter) {
            return response()->json([
                'success' => false,
                'message' => 'Voter not found or not in your assigned constituencies'
            ], 404);
        }
         
        $dropdowns = Cache::remember('active_dropdowns', 60 * 24, function() {
            $dropdownTypes = DropdownType::where('status', 'active')
                ->select('id', 'value', 'type')
                ->orderBy('position', 'asc')
                ->get()
                ->groupBy('type')
                ->map(function($items) {
                    return $items->map(function($item) {
                        return [
                            'id' => $item->id,
                            'value' => $item->value
                        ];
                    });
                }) 
                ->toArray();
 
            return $dropdownTypes;
        });  

        $parties = Party::where('status', 'active')
            ->orderBy('position', 'asc')
            ->get(); 
            
        $constituencies = Constituency::orderBy('name', 'asc')
            ->get();

        $countries = Country::whereRaw('is_active = true')
            ->orderBy('name', 'asc')
            ->with('locations')
            ->get();
  
        // Add survey data if voter exists
        if ($voter) {
            // Get all surveys for this voter
            $surveys = Survey::where('voter_id', $voter->id)
                ->with(['user:id,name,email,constituency_id'])
                ->orderBy('id', 'desc')
                ->get();
           
            $latestSurvey = $surveys->first();
            
            $surveyData = null;
            if ($latestSurvey) {
                // Get survey answers for the latest survey
                $surveyAnswers = DB::table('survey_answers')
                    ->where('survey_answers.survey_id', $latestSurvey->id) 
                    ->get();
                 
                    $questionAnswerArray = [];
                if(!is_null($surveyAnswers) && count($surveyAnswers) > 0){  
                    $questionIds = collect($surveyAnswers)->pluck('question_id')->unique();
                    $answerIds = collect($surveyAnswers)->pluck('answer_id')->unique();
                
                    $questions = DB::table('questions')
                        ->whereIn('id', $questionIds)
                        ->get(['id', 'question']);
                        
                    $answers = DB::table('answers')
                        ->whereIn('id', $answerIds)
                        ->get(['id', 'answer']);

                    foreach($surveyAnswers as $answer) {
                        $question = $questions->where('id', $answer->question_id)->first();
                        $answerObj = $answers->where('id', $answer->answer_id)->first();
                        
                        // Only add to array if both question and answer exist
                        if ($question && $answerObj) {
                            $questionAnswerArray[] = [
                                'question' => $question->question,
                                'answer' => $answerObj->answer
                            ];
                        }
                    }
                }

                $surveyData = [
                    'user' => [
                        'id' => isset($latestSurvey->user) ? $latestSurvey->user->id : null,
                        'name' => isset($latestSurvey->user) ? $latestSurvey->user->name : null,
                        'email' => isset($latestSurvey->user) ? $latestSurvey->user->email : null,
                        'constituency_id' => isset($latestSurvey->user) ? $latestSurvey->user->constituency_id : null,
                    ],
                    'survey_data' => [
                        'latestSurvey' => $latestSurvey,
                        'survey_count' => $surveys->count(),
                        'question_answers' => $questionAnswerArray
                    ]
                ];
            } 

            // Add survey data to voter object
            $voter->latest_survey = $surveyData;
        }

        return response()->json([
            'success' => true,
            'data' => $voter,
            'dropdowns' => $dropdowns,
            'parties' => $parties,
            'constituencies' => $constituencies,
            'countries' => $countries
        ]); 
    }

 
    // public function getVoter($voterId)
    // {
    //     if (!auth()->check() || auth()->user()->role->name !== 'User') {
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => 'Unauthorized'
    //                 ], 403);
    //             }
        
    //             // Get authenticated user's constituency IDs and handle empty case
    //             $constituencyId = auth()->user()->constituency_id;
    //             if (empty($constituencyId)) {
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => 'No constituencies assigned to user'
    //                 ], 400);
    //             }
    //             $userConstituencyIds = array_filter(explode(',', $constituencyId));
        
    //             if (empty($userConstituencyIds)) {
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => 'Invalid constituency IDs'
    //                 ], 400);
    //             }
        
    //             // Get constituencies names
    //             $constituencies = Constituency::whereIn('id', $userConstituencyIds) 
    //                 ->pluck('name')
    //                 ->toArray();
        
    //             // Get voter and check if pobse matches any constituency name
    //             $voter = Voter::where('voter', $voterId)
    //                 ->whereIn('pobse', $constituencies) 
    //                 ->first();
        
    //             if (!$voter) {
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => 'Voter not found or not in your assigned constituencies'
    //                 ], 404);
    //             }

    //     // Cache dropdown values for 24 hours
    //     $dropdowns = Cache::remember('active_dropdowns', 60 * 24, function() {
    //         return [
    //             'sex' => DropdownType::where('type', 'sex')
    //                 ->where('status', 'active')
    //                 ->select('id', 'value')
    //                 ->get(),
    //             'marital_status' => DropdownType::where('type', 'marital_status')
    //                 ->where('status', 'active')
    //                 ->select('id', 'value')
    //                 ->get(),
    //             'employment_type' => DropdownType::where('type', 'employment_type')
    //                 ->where('status', 'active')
    //                 ->select('id', 'value')
    //                 ->get(),
    //             'religion' => DropdownType::where('type', 'religion')
    //                 ->where('status', 'active')
    //                 ->select('id', 'value')
    //                 ->get(),
    //         ];
    //     });

    //     return response()->json([
    //         'success' => true,
    //         'data' => $voter,
    //         'dropdowns' => $dropdowns
    //     ]); 
         
    // } 


    public function getUnregisteredVoters(Request $request)
    { 
        if (!auth()->check() || auth()->user()->role->name !== 'User') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - User access required'
            ], 403);
        }

        $query = UnregisteredVoter::with(['voter' => function($query) {
            $query->select('id', 'voter', 'first_name','second_name', 'address', 'pobse', 'const');
        }])
        ->where('user_id', auth()->user()->id);

        // Add search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                ->orWhere('last_name', 'LIKE', "%{$search}%")
                ->orWhere('phone_number', 'LIKE', "%{$search}%")
                ->orWhere('new_email', 'LIKE', "%{$search}%")
                ->orWhere('new_address', 'LIKE', "%{$search}%")
                ->orWhereHas('voter', function($q) use ($search) {
                    $q->where('first_name', 'LIKE', "%{$search}%")
                        ->orWhere('address', 'LIKE', "%{$search}%");
                });
            });
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
        $allowedSortFields = ['first_name', 'last_name', 'date_of_birth', 'gender', 'created_at'];
        
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        }

        // Paginate results
        $perPage = $request->get('per_page', 20);
        $unregisteredVoters = $query->paginate($perPage);

        // Build search parameters object
        $searchParams = [
            'search' => $request->search ?? null,
            'gender' => $request->gender ?? null,
            'date_from' => $request->date_from ?? null,
            'date_to' => $request->date_to ?? null,
            'sort_by' => $sortField,
            'sort_direction' => $sortDirection,
            'per_page' => $perPage,
             'first_name' => $request->first_name ?? null,
             'last_name' => $request->last_name ?? null,
            'phone_number' => $request->phone_number ?? null,
            'new_email' => $request->new_email ?? null,
            'new_address' => $request->new_address ?? null,
            'first_name' => $request->first_name ?? null,
            'address' => $request->address ?? null
        ];

        return response()->json([
            'success' => true,
            'data' => $unregisteredVoters,
            'search_params' => $searchParams
        ]);
    }

    public function surveyer_search(Request $request)
    {
        $query = User::query()
            ->select('id', 'name');
        
        if ($request->has('name') && !empty($request->name)) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->name) . '%']);
        }
        
        $users = $query->get();
        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    public function updateDiffAddress(Request $request)  
    {
    
        if ($request->filled('voter') && ($request->filled('living_constituency') || $request->filled('diff_address'))) {
            $voter = Voter::find($request->voter);

            if(!$voter){
                return response()->json([
                    'success' => false,
                    'message' => 'Voter not found'
                ]);
            }
           
            $voter->update([
                'living_constituency' => $request->living_constituency,
                'diff_address' => $request->diff_address
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Voter updated successfully'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No data provided for update'
            ]);
        } 
    }  
    public function allConstituency(Request $request)  
    {   
        
        $constituencies = Constituency::orderBy('name', 'asc')->get();



        $constituencyIds = explode(',', auth()->user()->constituency_id);
         
        // $my_constituencies = Constituency::select('id', 'name')
        //     ->whereIn('id', $constituencyIds)
        //     ->orderBy('position', 'asc')
        //     ->get();


        $query = Constituency::select('id', 'name');
        if ($request->boolean('voterNational')) {
            // National → no filter
        } else {
            $query->whereIn('id', $constituencyIds);
        }
        
        $query->orderBy('position', 'asc');
        $my_constituencies = $query->get();



 
        if ($my_constituencies->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No constituency found for this user'
            ], 404);
        }

          
        return response()->json([
            'success' => true,
            'data' => $constituencies,
            'my_constituencies' => $my_constituencies
             
        ]);
    }  



    public function callCenterList(Request $request)
    {
        $query = CallCenter::with([
            'voter:id,first_name,second_name,surname,voter,address,phone_number,email,const,polling,living_constituency,surveyer_constituency,is_national,voter_voting_for,user_id',
            'voter.constituency:id,name'
        ])
            ->where('user_id', auth()->user()->id);

        // Gather all search parameters (query + input), trim and lowercase for string comparison
        $voting_for = $request->query('voting_for') ?? $request->input('voting_for');
        $call_center_email = $request->query('call_center_email') ?? $request->input('call_center_email');
        $call_center_caller_name = $request->query('call_center_caller_name') ?? $request->input('call_center_caller_name');
        $call_center_phone = $request->query('call_center_phone') ?? $request->input('call_center_phone');
        $call_center_voter_name = $request->query('call_center_voter_name') ?? $request->input('call_center_voter_name');
        $call_center_follow_up = $request->query('call_center_follow_up') ?? $request->input('call_center_follow_up');
        $call_center_date_time = $request->query('call_center_date_time') ?? $request->input('call_center_date_time');
        $voter_id = $request->query('voter') ?? $request->input('voter');
        $surname = $request->query('surname') ?? $request->input('surname');
        $firstName = $request->query('first_name') ?? $request->input('first_name');
        $secondName = $request->query('second_name') ?? $request->input('second_name');
        $constituencyName = $request->query('constituency_name') ?? $request->input('constituency_name');
        $constituencyId = $request->query('const') ?? $request->input('const');

        // Voter relation filters (use whereHas; voters table is not joined on call_center)
        if (trim((string) $surname) !== '') {
            $term = '%' . strtolower(trim($surname)) . '%';
            $query->whereHas('voter', function ($q) use ($term) {
                $q->whereRaw('LOWER(surname) LIKE ?', [$term]);
            });
        }
        if (trim((string) $firstName) !== '') {
            $term = '%' . strtolower(trim($firstName)) . '%';
            $query->whereHas('voter', function ($q) use ($term) {
                $q->whereRaw('LOWER(first_name) LIKE ?', [$term]);
            });
        }
        if (trim((string) $secondName) !== '') {
            $term = '%' . strtolower(trim($secondName)) . '%';
            $query->whereHas('voter', function ($q) use ($term) {
                $q->whereRaw('LOWER(second_name) LIKE ?', [$term]);
            });
        }
        if (trim((string) $constituencyName) !== '') {
            $term = '%' . strtolower(trim($constituencyName)) . '%';
            $query->whereHas('voter.constituency', function ($q) use ($term) {
                $q->whereRaw('LOWER(name) LIKE ?', [$term]);
            });
        }
        if (!empty($constituencyId) && is_numeric($constituencyId)) {
            $query->whereHas('voter', function ($q) use ($constituencyId) {
                $q->where('const', (int) $constituencyId);
            });
        }
        if (trim((string) $voter_id) !== '') {
            $query->whereHas('voter', function ($q) use ($voter_id) {
                $q->where('voter', $voter_id);
            });
        }
        if (trim((string) $voting_for) !== '') {
            $term = '%' . strtolower(trim($voting_for)) . '%';
            $query->whereHas('voter', function ($q) use ($term) {
                $q->whereRaw('LOWER(voter_voting_for) LIKE ?', [$term]);
            });
        }

        // Call center table fields (case-insensitive LIKE)
        if (trim((string) $call_center_email) !== '') {
            $term = '%' . strtolower(trim($call_center_email)) . '%';
            $query->whereRaw('LOWER(call_center_email) LIKE ?', [$term]);
        }
        if (trim((string) $call_center_caller_name) !== '') {
            $term = '%' . strtolower(trim($call_center_caller_name)) . '%';
            $query->whereRaw('LOWER(call_center_caller_name) LIKE ?', [$term]);
        }
        if (trim((string) $call_center_phone) !== '') {
            $term = '%' . strtolower(trim($call_center_phone)) . '%';
            $query->whereRaw('LOWER(call_center_phone) LIKE ?', [$term]);
        }
        if (trim((string) $call_center_voter_name) !== '') {
            $term = '%' . strtolower(trim($call_center_voter_name)) . '%';
            $query->whereRaw('LOWER(call_center_voter_name) LIKE ?', [$term]);
        }
        if (trim((string) $call_center_follow_up) !== '') {
            $term = '%' . strtolower(trim($call_center_follow_up)) . '%';
            $query->whereRaw('LOWER(call_center_follow_up) LIKE ?', [$term]);
        }
        if (trim((string) $call_center_date_time) !== '') {
            $term = '%' . strtolower(trim($call_center_date_time)) . '%';
            $query->whereRaw('LOWER(call_center_date_time::text) LIKE ?', [$term]);
        }

        $callCenters = $query->orderBy('id', 'desc')->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $callCenters
        ]);
    } 


    


}