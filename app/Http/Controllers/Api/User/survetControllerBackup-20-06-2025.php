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
        $term = $request->query('term');
        $type = $request->query('type', 'voterId');
        $user_id = auth()->id();

        if (empty($term) || strlen($term) < 3) {
            return response()->json([
                'success' => true,
                'suggestions' => []
            ]); 
        }

        // Get user's assigned constituencies
        $constituencyIds = explode(',', auth()->user()->constituency_id);

        // Check if constituency IDs are valid
        if (empty($constituencyIds) || (count($constituencyIds) === 1 && empty($constituencyIds[0]))) {
            return response()->json([
                'success' => true,
                'suggestions' => []
            ]);
        }

        // Include user ID and timestamp in cache key to avoid conflicts
        $cacheKey = "suggestions_{$type}_{$term}_{$user_id}_" . implode('_', $constituencyIds) . "_" . now()->timestamp;

        // Get suggestions from cache or generate new ones
        $suggestions = Cache::remember($cacheKey, now()->addMinutes(2), function() use ($type, $term, $constituencyIds) {
            $query = Voter::query()
                ->select([
                    'voters.*',
                    'constituencies.name as constituency_name'
                ])
                ->join('constituencies', 'voters.const', '=', 'constituencies.id')
                ->whereIn('voters.const', $constituencyIds);

            switch ($type) {
                case 'voterId':
                    $query->where('voter', 'LIKE', '%' . $term . '%'); // Changed to prefix match for index usage
                    break;
                case 'name':
                    $terms = explode(' ', $term);
                    if (count($terms) > 1) {
                        $firstName = strtolower($terms[0]);
                        $surname = strtolower($terms[1]);
                        
                        $query->whereRaw('LOWER(first_name) LIKE ? AND LOWER(surname) LIKE ?', [
                            $firstName . '%', // Changed to prefix match
                            $surname . '%'
                        ])
                        ->orderBy('first_name')
                        ->orderBy('surname');
                    } else {
                        $searchTerm = strtolower($term);
                        $query->where(function($q) use ($searchTerm) {
                            $q->whereRaw('LOWER(first_name) LIKE ?', [$searchTerm . '%'])
                              ->orWhereRaw('LOWER(surname) LIKE ?', [$searchTerm . '%']);
                        })
                        ->orderBy('first_name')
                        ->orderBy('surname');
                    }
                    break;
                case 'address':
                    $searchTerm = strtolower($term);
                    $query->where(function($q) use ($searchTerm) {
                        $q->whereRaw('LOWER(house_number) LIKE ? OR
                                    LOWER(aptno) LIKE ? OR 
                                    LOWER(blkno) LIKE ? OR
                                    LOWER(address) LIKE ? OR
                                    LOWER(pobse) LIKE ? OR 
                                    LOWER(pobis) LIKE ?', 
                            array_fill(0, 6, $searchTerm . '%'))
                          ->orderBy('address')
                          ->orderBy('house_number');
                    });
                    break;
            }

            // Limit query results for better performance
            $voters = $query->limit(150)->get();

            // Eager load survey data in a single query
            $voterIds = $voters->pluck('id')->toArray();
            $surveys = Survey::whereIn('voter_id', $voterIds)
                ->with(['user:id,name,email,constituency_id'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('voter_id');

            return $voters->map(function($voter) use ($surveys) {
                $voterSurveys = $surveys->get($voter->id, collect([]));
                $latestSurvey = $voterSurveys->first();
                
                $surveyAnswers = null;
                if ($latestSurvey) {
                    $surveyAnswers = \DB::table('survey_answers')
                        ->where('survey_answers.survey_id', $latestSurvey->id)
                        ->get();

                    $questionIds = collect($surveyAnswers)->pluck('question_id')->unique();
                    $answerIds = collect($surveyAnswers)->pluck('answer_id')->unique();
                    
                    $questions = \DB::table('questions')->whereIn('id', $questionIds)
                        ->get(['id', 'question']);
                    $answers = \DB::table('answers')->whereIn('id', $answerIds)
                        ->get(['id', 'answer']);

                    $questionAnswerArray = [];
                    foreach($surveyAnswers as $answer) {
                        $question = $questions->where('id', $answer->question_id)->first();
                        $answerObj = $answers->where('id', $answer->answer_id)->first();
                        
                        $questionAnswerArray[] = [
                            'question' => $question->question,
                            'answer' => $answerObj->answer
                        ];
                    }
                }

                $surveyData = null;
                if ($latestSurvey) {
                    $surveyData = [
                        'user' => [
                            'id' => $latestSurvey->user->id,
                            'name' => $latestSurvey->user->name,
                            'email' => $latestSurvey->user->email,
                            'constituency_id' => $latestSurvey->user->constituency_id,
                        ],
                        'survey_data' => [
                            'latestSurvey' => $latestSurvey,
                            'survey_count' => $voterSurveys->count(),
                            'question_answers' => $questionAnswerArray
                        ]
                    ];
                }

                return [
                    'voterId' => $voter->voter,
                    'name' => $voter->first_name . ' ' . $voter->surname,
                    'address' => $voter->address,
                    'constituency_name' => $voter->constituency_name,
                    'latest_survey' => $surveyData
                ]; 
            });
        });

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

        if ($constituency->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No constituency found for this user'
            ], 404);
        }
 
        return response()->json([
            'success' => true,
            'data' => $constituency
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

        $query = Survey::with('voter');

        // Search fields based on Survey model's fillable columns
        // Apply all search filters directly from URL parameters with case-insensitive search
        if ($request->has('voting_decision')) {
            $query->where('voting_decision',$request->voting_decision);
        }

        if($request->has('voting_for') && $request->has('voting_for') !== ''){
           
            $get_party = Party::where('id', $request->voting_for)->first();
            
            $party_name = $get_party->name;
            
            $query->where('voting_for', $party_name); 
             
       }

         
        if($request->has('is_died')){
            $query->where('is_died',$request->is_died); 
        }
        if( $request->has('died_date')){
            $query->where('died_date', $request->died_date);
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
        $surveys = $query->where('user_id', auth()->user()->id)->orderBy('id', 'desc')->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $surveys,
             
        ]);
    }

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
    //             \Log::info('Survey store request:', [
    //                 'user_id' => auth()->id(),
    //                 'request_data' => $request->all()
    //             ]); 

                
    
    //             $createdVoters = [];
                
    //             // Validate survey data and user data
    //             $validator = \Validator::make($request->all(), [
    //                 'voter_id' => 'required|exists:voters,id',
    //                 'sex' => 'required|string',
    //                 'marital_status' => 'required|string',
    //                 'employed' => 'required|string',
    //                 'children' => 'required|string',
    //                 //'employment_type' => 'required_if:employed,Yes|string',
    //                 //'employment_sector' => 'required_if:employment_type,Private Sector,Government|string',
 

    //                 // 'religion' => 'required|string',
    //                 'located' => 'required|string|in:Main Island,Off Island,Outside Country',
    //                 'island' => 'required_if:located,Off Island|prohibited_if:located,Main Island,Outside Country',
    //                 'country' => 'required_if:located,Outside Country|prohibited_if:located,Main Island,Off Island',
    //                 'country_location' => 'required_if:located,Outside Country|prohibited_if:located,Main Island,Off Island',

    //                 'home_phone_code' => 'nullable|string',
    //                 'home_phone' => 'nullable|string',
    //                 'work_phone_code' => 'nullable|string',
    //                 'note' => 'nullable|string',
    //                 'work_phone' => 'nullable|string',
    //                 'cell_phone_code' => 'nullable|string',
    //                 'cell_phone' => 'nullable|string', 
    //                 //'voting_for' => 'required|string',
    //                 // 'last_voted' => 'required|string',
    //                 // 'voted_for_party' => 'required_if:last_voted,Yes|string',
    //                 // 'voted_where' => 'required_if:last_voted,Yes|string',
    //                 // 'voted_in_house' => 'required|string', 
    //                 'email' => 'nullable|email', 
    //                 'special_comments' => 'nullable|string',
    //                 'other_comments' => 'nullable|string',
    //                 'voter_image' => 'nullable|string', 
    //                 'house_image' => 'nullable|string',
    //                 'voters_in_house' => 'nullable|string',
    //                 'unregistered_voters' => 'nullable|array',
    //                 'unregistered_voters.*.first_name' => 'required_with:unregistered_voters|string',
    //                 'unregistered_voters.*.last_name' => 'required_with:unregistered_voters|string',
    //                 'unregistered_voters.*.dob' => 'required_with:unregistered_voters|date',
    //                 'unregistered_voters.*.gender' => 'required_with:unregistered_voters|string',
    //                 'unregistered_voters.*.address' => 'required_with:unregistered_voters|string',
    //                 'unregistered_voters.*.email' => 'required_with:unregistered_voters|email',
    //                 'unregistered_voters.*.phone' => 'required_with:unregistered_voters|string',
    //                 'unregistered_voters.*.residentType' => 'required_with:unregistered_voters|string',
    //                 'unregistered_voters.*.registrationStatus' => 'required_with:unregistered_voters|string'
    //             ]); 
 
    
    //             if ($validator->fails()) {
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => 'Validation failed',
    //                     'errors' => $validator->errors()
    //                 ], 422);
    //             }
    
    //             // First create the survey record
    //             $surveyData = [
    //                 'voter_id' => $request->voter_id,
    //                 'user_id' => auth()->user()->id,
    //                 'sex' => $request->sex,
    //                 'marital_status' => $request->marital_status,
    //                 'employed' => $request->employed,
    //                 'children' => $request->children,
    //                 'employment_type' => $request->employment_type,
    //                 'employment_sector' => $request->employment_sector,
    //                 'religion' => $request->religion,
    //                 'located' => $request->located,
    //                 'island' => $request->located === 'Off Island' ? $request->island : null,
    //                 'country' => $request->located === 'Outside Country' ? $request->country : null,
    //                 'country_location' => $request->located === 'Outside Country' ? $request->country_location : null,
    //                 'home_phone_code' => $request->home_phone_code,
    //                 'home_phone' => $request->home_phone,
    //                 'work_phone_code' => $request->work_phone_code,
    //                 'work_phone' => $request->work_phone,
    //                 'cell_phone_code' => $request->cell_phone_code,
    //                 'cell_phone' => $request->cell_phone,
    //                 'email' => $request->email,
    //                 'special_comments' => $request->special_comments,
    //                 'other_comments' => $request->other_comments,
    //                 'voting_for' => $request->voting_for,
    //                 'last_voted' => $request->last_voted,
    //                 'voted_for_party' => $request->voted_for_party,
    //                 'voted_where' => $request->voted_where,
    //                 'voted_in_house' => $request->voted_in_house,
    //                 'voters_in_house' => $request->voters_in_house,
    //                 'note' => $request->note,
    //                 'voting_decision' => $request->voting_decision   
    //             ]; 
    
    //             // Handle images if present
    //             if ($request->hasFile('voter_image')) {
    //                 $voterImagePath = $request->file('voter_image')->store('surveys/voter_images', 'public');
    //                 $surveyData['voter_image'] = $voterImagePath;
    //             } 
    
    //             if ($request->hasFile('home_image')) {
    //                 $homeImagePath = $request->file('home_image')->store('surveys/home_images', 'public');
    //                 $surveyData['home_image'] = $homeImagePath;
    //             }
     
               

    //             if ($request->survey_id) {
    //                 $existingSurvey = Survey::find($request->survey_id);
                
    //                 if ($existingSurvey) {
    //                     $hasChanges = false;
                
    //                     $changedKeys = [];
    //                     foreach ($surveyData as $key => $value) {
    //                         // Skip timestamp fields
    //                         if (in_array($key, ['children','employed','created_at', 'updated_at','voter_image','home_image','unregistered_voters','user_id','survey_id','voters_in_house','note'])) {
    //                             continue;
    //                         }

    //                         // General comparison for all other fields
    //                         if ($existingSurvey->$key !== $value) {
    //                             $hasChanges = true;
    //                             $changedKeys[] = [
    //                                 'key' => $key,
    //                                 'old_value' => $existingSurvey->$key,
    //                                 'new_value' => $value
    //                             ];
    //                         }
    //                     }
                
    //                     if (!$hasChanges) {
    //                         $survey = $existingSurvey;
                           
    //                     } else {
                            
    //                         $survey = Survey::create($surveyData);
    //                         $this->storeSurveyAnswers($request,$survey);
    //                         $this->trackDailySurvey();
                          
    //                     }
    //                 } else {
                        
    //                     $survey = Survey::create($surveyData);
    //                     $this->storeSurveyAnswers($request,$survey);
    //                     $this->trackDailySurvey();
    //                 }
    //             } else {
                   
    //                 $survey = Survey::create($surveyData);
    //                 $this->storeSurveyAnswers($request,$survey);
    //                 $this->trackDailySurvey();
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
    public function store(Request $request)
    { 

        if (!auth()->check() || auth()->user()->role->name !== 'User') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - User access required'
            ], 403);
        }
    
        // First handle unregistered voters if is_register is 1
      
            try {

                if($request->is_died){


                    $surveyData = [
                        'voter_id' => $request->voter_id,
                        'user_id' => auth()->user()->id,
                        'is_died' => 1,
                        'died_date' => $request->died_date,
                    ]; 
                    $survey = Survey::create($surveyData);


                }else{ 


                    \Log::info('Survey store request:', [
                        'user_id' => auth()->id(),
                        'request_data' => $request->all()
                    ]); 
    
                    
        
                    $createdVoters = [];
                    
                    // Validate survey data and user data
                    $validator = \Validator::make($request->all(), [
                        'voter_id' => 'required|exists:voters,id',
                        'sex' => 'required|string',
                        'marital_status' => 'required|string',
                        'employed' => 'required|string',
                        'children' => 'required|string',
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
        
                    // Handle images if present
                    if ($request->hasFile('voter_image')) {
                        $voterImagePath = $request->file('voter_image')->store('surveys/voter_images', 'public');
                        $surveyData['voter_image'] = $voterImagePath;
                    } 
        
                    if ($request->hasFile('home_image')) {
                        $homeImagePath = $request->file('home_image')->store('surveys/home_images', 'public');
                        $surveyData['home_image'] = $homeImagePath;
                    }
         
                   
    
                    if ($request->survey_id) {
                        $existingSurvey = Survey::find($request->survey_id);
                    
                        if ($existingSurvey) {
                            $hasChanges = false;
                            $changedKeys = [];
    
                            // Compare survey data fields
                            foreach ($surveyData as $key => $value) {
                                // Skip fields that don't need comparison
                                if (in_array($key, ['children','employed','created_at', 'updated_at','voter_image','home_image','unregistered_voters','user_id','survey_id','voters_in_house','note'])) {
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
                    } else {
                        // No survey ID provided, create new survey
                        $survey = Survey::create($surveyData);
                        $this->storeSurveyAnswers($request, $survey);
                        $this->trackDailySurvey();
                    }
                    
                
    

                }
               
                
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

                        // Create unregistered voter record with voter_id from survey
                        $unregisteredVoter = UnregisteredVoter::create([
                            'voter_id' => $survey->voter_id,
                            'survey_id' => $survey->id,
                            'first_name' => $userData['first_name'],
                            'last_name' => $userData['last_name'],
                            'date_of_birth' => $userData['dob'],
                            'gender' => $userData['gender'],
                            'phone_number' => $userData['phone'],
                            'new_email' => $userData['email'] ?? null,
                            'new_address' => $userData['address'],
                            'user_id' => auth()->user()->id 
                        ]);

                        $createdVoters[] = $unregisteredVoter;
                    } 
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
                return response()->json([
                    'success' => false,
                    'message' => 'Error processing data',
                    'error' => $e->getMessage()
                ], 500);
            }
        
    
        // ... rest of the code for regular survey creation ...
    }

    //   today survey updated code 
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
    //end today survey store code 

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
            'employed' => 'sometimes|boolean',
            'children' => 'sometimes|boolean',
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
            ->join('constituencies', 'voters.const', '=', 'constituencies.id')
            ->whereIn('voters.const', $constituencies);
     
        if ($request->voterId) {
            $query->where('voter', $request->voterId);
        }
        $underAge25 = $request->input('under_age_25');

        

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
            $addressMapping = DB::table('voter_address_mapping')
                ->where(function($query) use ($voter) {
                    $query->where('voter_ids', 'LIKE', $voter->voter)
                          ->orWhere('voter_ids', 'LIKE', $voter->voter . ',%')
                          ->orWhere('voter_ids', 'LIKE', '%,' . $voter->voter . ',%')
                          ->orWhere('voter_ids', 'LIKE', '%,' . $voter->voter);
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

        // Add address voters to voter object
        if ($voter) {
            $voter->address_voters = $addressVoters;
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

        $countries = Country::where('is_active', true)
            ->orderBy('name', 'asc')
            ->with('locations')
            ->get();
  
        // Add survey data if voter exists
        if ($voter) {
            // Get all surveys for this voter
            $surveys = Survey::where('voter_id', $voter->id)
                ->with(['user:id,name,email,constituency_id'])
                ->orderBy('created_at', 'desc')
                ->get();
                
            $latestSurvey = $surveys->first();
            
            $surveyData = null;
            if ($latestSurvey) {
                // Get survey answers for the latest survey
                $surveyAnswers = DB::table('survey_answers')
                    ->where('survey_answers.survey_id', $latestSurvey->id)
                    ->get();

                $questionIds = collect($surveyAnswers)->pluck('question_id')->unique();
                $answerIds = collect($surveyAnswers)->pluck('answer_id')->unique();
                
                $questions = DB::table('questions')
                    ->whereIn('id', $questionIds)
                    ->get(['id', 'question']);
                    
                $answers = DB::table('answers')
                    ->whereIn('id', $answerIds)
                    ->get(['id', 'answer']);

                $questionAnswerArray = [];
                foreach($surveyAnswers as $answer) {
                    $question = $questions->where('id', $answer->question_id)->first();
                    $answerObj = $answers->where('id', $answer->answer_id)->first();
                    
                    $questionAnswerArray[] = [
                        'question' => $question->question,
                        'answer' => $answerObj->answer
                    ];
                }

                $surveyData = [
                    'user' => [
                        'id' => $latestSurvey->user->id,
                        'name' => $latestSurvey->user->name,
                        'email' => $latestSurvey->user->email,
                        'constituency_id' => $latestSurvey->user->constituency_id,
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
    public function allConstituency()  
    {
        $constituencies = Constituency::all();
        return response()->json([
            'success' => true,
            'data' => $constituencies
        ]);
    }  


}