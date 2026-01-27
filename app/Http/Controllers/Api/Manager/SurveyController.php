<?php

namespace App\Http\Controllers\Api\Manager;

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
use App\Models\Answer;
use App\Models\Question;
use App\Models\SurveyAnswer; 
use App\Models\ManagerSystemSetting; 
 
class SurveyController extends Controller
{

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

        // Include user ID and timestamp in cache key to avoid conflicts
        $cacheKey = "suggestions_{$type}_{$term}_{$user_id}_" . implode('_', $constituencyIds) . "_" . now()->timestamp;

        // Get suggestions from cache or generate new ones
        $suggestions = Cache::remember($cacheKey, now()->addMinutes(2), function() use ($type, $term, $constituencyIds) {
            $query = Voter::query() 
                ->select(
                    'voters.*', 
                    'constituencies.name as constituency_name'
                )
                ->join('constituencies', 'voters.const', '=', 'constituencies.id')
                ->whereIn('voters.const', $constituencyIds);

            switch ($type) {
                case 'voterId':
                    $query->where('voter', 'LIKE', '%' . $term . '%');
                    break; 
                case 'name':
                    $query->where(function($q) use ($term) {
                        $terms = explode(' ', $term);
                         
                        if (count($terms) > 1) {
                            // If name has two parts, search for both first name AND surname
                            $firstName = $terms[0];
                            $surname = $terms[1]; 
                            
                            $q->whereRaw('LOWER(first_name) LIKE ? AND LOWER(surname) LIKE ?', [
                                '%' . strtolower($firstName) . '%',
                                '%' . strtolower($surname) . '%'
                            ]);
                        } else {
                            // If single word, search in either first name OR surname
                            $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($term) . '%'])
                              ->orWhereRaw('LOWER(surname) LIKE ?', ['%' . strtolower($term) . '%']);
                        }
                    });
                    break; 
                case 'address':
                    $query->whereRaw('LOWER(address) LIKE ?', ['%' . strtolower($term) . '%']);
                    break;
            }

            return $query->limit(10)
                ->get()
                ->map(function($voter) {
                    // Get latest survey for this voter with user details
                    $latestSurvey = Survey::where('voter_id', $voter->id)
                        ->with(['user:id,name,email,constituency_id'])
                        ->latest()
                        ->first();
                    $SurveyCount = Survey::where('voter_id', $voter->id)
                        ->count();

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
                                'survey_count' => $SurveyCount
                                
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
   
    public function getUserConstituency()
    {
        // Get constituency_id as string and convert to array of integers
        $constituencyIds = explode(',', auth()->user()->constituency_id);
         
        $query = Constituency::select('id', 'name')
            ->whereIn('id', $constituencyIds);
        if (request()->has('search')) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower(request()->input('search')) . '%']);
        }
        $constituency =$query->orderBy('position', 'asc')
        ->get(); 
        $constituency->each(function ($constituency) {
            $settings = ManagerSystemSetting::where('manager_id', auth()->user()->id)
                //->where('constituency_id', $constituency->id)
                ->where('constituency_id', (string)$constituency->id)

                ->first(['start_time', 'end_time']);
            
            $constituency->start_time = $settings ? $settings->start_time : null;
            $constituency->end_time = $settings ? $settings->end_time : null;
        }); 
        

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
    public function getUserConstituency_get(Request $request)
    { 
        // Get constituency_id as string and convert to array of integers
        $constituencyIds = explode(',', auth()->user()->constituency_id);
         
        $query = Constituency::select('id', 'name')
            ->whereIn('id', $constituencyIds);
        if (request()->has('search')) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower(request()->input('search')) . '%']);
        }
        $constituency =$query->orderBy('position', 'asc')
        ->paginate($request->get('per_page', 20)); 
        $constituency->each(function ($constituency) {
            $settings = ManagerSystemSetting::where('manager_id', auth()->user()->id)
                ->where('constituency_id', $constituency->id)
                ->first(['start_time', 'end_time']);
            
            $constituency->start_time = $settings ? $settings->start_time : null;
            $constituency->end_time = $settings ? $settings->end_time : null;
        }); 
        

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
        
        $query = Survey::with('voter');

        // Search fields based on Survey model's fillable columns
        // Apply all search filters directly from URL parameters with case-insensitive search
        if ($request->has('sex')) {
            $query->whereRaw('LOWER(sex) = ?', [strtolower($request->sex)]);
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
        $surveys = $query->where('user_id', auth()->user()->id)->orderBy('id', 'desc')->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $surveys,
             
        ]);
    }


    public function store(Request $request)
    { 
        
    
        // First handle unregistered voters if is_register is 1
      
            try {
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
 

                    'religion' => 'required|string',
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
                    'voting_for' => 'required|string',
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
                    'note' => $request->note
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
     
                //   $survey = Survey::updateOrCreate(
                //       ['voter_id' => $request->voter_id],
                //       $surveyData
                //   );

                // ... existing code ...

                if ($request->survey_id) {
                    $existingSurvey = Survey::find($request->survey_id);
                
                    if ($existingSurvey) {
                        $hasChanges = false;
                
                        $changedKeys = [];
                        foreach ($surveyData as $key => $value) {
                            // Skip timestamp fields
                            if (in_array($key, ['children','employed','created_at', 'updated_at','voter_image','home_image','unregistered_voters','user_id','survey_id','voters_in_house','note'])) {
                                continue;
                            }

                            // General comparison for all other fields
                            if ($existingSurvey->$key !== $value) {
                                $hasChanges = true;
                                $changedKeys[] = [
                                    'key' => $key,
                                    'old_value' => $existingSurvey->$key,
                                    'new_value' => $value
                                ];
                            }
                        }
                
                        if (!$hasChanges) {
                            $survey = $existingSurvey;
                            // return response()->json([
                            //     'success' => true,
                            //     'survey_data' => $existingSurvey,
                            //     'request_data' => $request->all(),
                            //     'created_survey' => 'no',
                            //     'changed_keys' => []
                            // ]);
                        } else {
                            $survey = Survey::create($surveyData);
                            // return response()->json([
                            //     'success' => true,
                            //     'survey_data' => $existingSurvey,
                            //     'request_data' => $request->all(),
                            //     'created_survey' => 'yes',
                            //     'changed_keys' => $changedKeys
                            // ]);
                        }
                    } else {
                        $survey = Survey::create($surveyData);
                    }
                } else {
                    $survey = Survey::create($surveyData);
                }
                

// ... existing code ...
                // Process each unregistered voter
                
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
    

    
    public function show($id)
    {
      

        $survey = Survey::with('voter')->find($id);

        if (!$survey) {
            return response()->json([
                'success' => false,
                'message' => 'Survey not found'
            ], 404);
        }

        // Get survey answers with questions and answers
        $surveyAnswers = SurveyAnswer::where('survey_id', $survey->id)->get();
        
        $questionsAndAnswers = [];
        $questionsForForm = [];
        foreach ($surveyAnswers as $surveyAnswer) {
            $question = Question::find($surveyAnswer->question_id);
            $answer = Answer::find($surveyAnswer->answer_id);  
            
            if ($question && $answer) {
                $questionsAndAnswers[] = [
                    'question' => $question->question,
                    'answer' => $answer->answer
                ];
                
                // Add format for form editing
                $questionsForForm[] = [
                    'question_id' => $surveyAnswer->question_id,
                    'answer_id' => $surveyAnswer->answer_id
                ];
            }
        }

        // Add questions and answers to survey data
        $surveyData = $survey->toArray();
        $surveyData['questions_and_answers'] = $questionsAndAnswers;
        $surveyData['questions'] = $questionsForForm; // For form editing

        return response()->json([
            'success' => true,
            'data' => $surveyData
        ]);  
    }

    public function update(Request $request, $id)
    {
        try {
            // Check Manager authorization
            if (!auth()->check() || auth()->user()->role->name !== 'Manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - Manager access required'
                ], 403);
            }

            // Find the survey
            $survey = Survey::find($id);
            
            if (!$survey) {
                return response()->json([
                    'success' => false,
                    'message' => 'Survey not found'
                ], 404);
            }

            \Log::info('Survey update request:', [
                'user_id' => auth()->id(),
                'survey_id' => $id,
                'request_data' => $request->all()
            ]);

            // Validate survey data
            $validator = \Validator::make($request->all(), [
                'voter_id' => 'sometimes|exists:voters,id',
                'sex' => 'sometimes|string',
                'marital_status' => 'sometimes|string',
                'employed' => 'sometimes|string',
                'children' => 'sometimes|string',
                'employment_type' => 'nullable|string',
                'employment_sector' => 'nullable|string',
                'religion' => 'sometimes|string',
                'located' => 'sometimes|string|in:Main Island,Off Island,Outside Country',
                'island' => 'required_if:located,Off Island|prohibited_if:located,Main Island,Outside Country',
                'country' => 'required_if:located,Outside Country|prohibited_if:located,Main Island,Off Island',
                'country_location' => 'required_if:located,Outside Country|prohibited_if:located,Main Island,Off Island',
                'home_phone_code' => 'nullable|string',
                'home_phone' => 'nullable|string',
                'work_phone_code' => 'nullable|string',
                'work_phone' => 'nullable|string',
                'cell_phone_code' => 'nullable|string',
                'cell_phone' => 'nullable|string',
                'email' => 'nullable|email',
                'special_comments' => 'nullable|string',
                'other_comments' => 'nullable|string',
                'voting_for' => 'sometimes|string',
                'last_voted' => 'nullable|string',
                'voted_for_party' => 'nullable|string',
                'voted_where' => 'nullable|string',
                'voted_in_house' => 'nullable|string',
                'voters_in_house' => 'nullable|string',
                'note' => 'nullable|string',
                'voting_decision' => 'nullable|string',
                'voter_image' => 'nullable|string',
                'house_image' => 'nullable|string',
                'questions' => 'nullable|array',
                'questions.*.question_id' => 'required_with:questions|integer',
                'questions.*.answer_id' => 'required_with:questions|integer',
                'unregistered_voters' => 'nullable|array',
                'unregistered_voters.*.first_name' => 'required_with:unregistered_voters|string',
                'unregistered_voters.*.last_name' => 'required_with:unregistered_voters|string',
                'unregistered_voters.*.dob' => 'required_with:unregistered_voters|date',
                'unregistered_voters.*.gender' => 'required_with:unregistered_voters|string',
                'unregistered_voters.*.address' => 'required_with:unregistered_voters|string',
                'unregistered_voters.*.email' => 'nullable|email',
                'unregistered_voters.*.phone' => 'required_with:unregistered_voters|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Prepare survey data for update
            $surveyData = [];
            
            $fieldsToUpdate = [
                'voter_id', 'sex', 'marital_status', 'employed', 'children',
                'employment_type', 'employment_sector', 'religion', 'located',
                'island', 'country', 'country_location', 'home_phone_code',
                'home_phone', 'work_phone_code', 'work_phone', 'cell_phone_code',
                'cell_phone', 'email', 'special_comments', 'other_comments',
                'voting_for', 'last_voted', 'voted_for_party', 'voted_where',
                'voted_in_house', 'voters_in_house', 'note', 'voting_decision'
            ];

            // Get the located value (from request or current survey)
            $located = $request->has('located') ? $request->located : $survey->located;

            foreach ($fieldsToUpdate as $field) {
                if ($request->has($field)) {
                    // Handle special cases for conditional fields
                    if ($field === 'island') {
                        if ($located === 'Off Island') {
                            $surveyData[$field] = $request->$field;
                        } else {
                            $surveyData[$field] = null;
                        }
                    } elseif ($field === 'country' || $field === 'country_location') {
                        if ($located === 'Outside Country') {
                            $surveyData[$field] = $request->$field;
                        } else {
                            $surveyData[$field] = null;
                        }
                    } else {
                        $surveyData[$field] = $request->$field;
                    }
                }
            }

            // Handle images if present
            if ($request->hasFile('voter_image')) {
                $voterImagePath = $request->file('voter_image')->store('surveys/voter_images', 'public');
                $surveyData['voter_image'] = $voterImagePath;
            }

            if ($request->hasFile('home_image')) {
                $homeImagePath = $request->file('home_image')->store('surveys/home_images', 'public');
                $surveyData['home_image'] = $homeImagePath;
            }

            // Update the survey
            $survey->update($surveyData);

            // Update survey answers if provided
            if ($request->has('questions') && is_array($request->questions)) {
                // Delete existing survey answers
                SurveyAnswer::where('survey_id', $survey->id)->delete();
                
                // Create new survey answers
                $this->storeSurveyAnswers($request, $survey);
            }

            // Handle unregistered voters if provided
            $updatedVoters = [];
            if ($request->has('unregistered_voters') && is_array($request->unregistered_voters)) {
                // Delete existing unregistered voters for this survey
                UnregisteredVoter::where('survey_id', $survey->id)->delete();
                
                // Create new unregistered voters
                foreach ($request->unregistered_voters as $userData) {
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
                        continue; // Skip invalid entries
                    }

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

                    $updatedVoters[] = $unregisteredVoter;
                }
            }

            // Reload survey with relationships
            $survey->refresh();
            $survey->load('voter');

            return response()->json([
                'success' => true,
                'message' => 'Survey updated successfully',
                'data' => [
                    'survey' => $survey,
                    'unregistered_voters' => $updatedVoters
                ]
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error updating survey: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating survey',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store survey answers
     */
    private function storeSurveyAnswers(Request $request, $survey)
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
            ->orderBy('name', 'asc')
            ->get();
            
        $constituencies = Constituency::orderBy('name', 'asc')
            ->get();

        $countries = Country::where('is_active', true)
            ->orderBy('name', 'asc')
            ->with('locations')
            ->get();
  
        return response()->json([
            'success' => true,
            'data' => $voter,
            'dropdowns' => $dropdowns,
            'parties' => $parties,
            'constituencies' => $constituencies,
            'countries' => $countries
        ]); 
    }


   


    public function getUnregisteredVoters(Request $request)
    { 
       $constituency_ids = explode(',', auth()->user()->constituency_id);

        $query = UnregisteredVoter::with(['voter' => function($query) {
            $query->select('id', 'voter', 'first_name','second_name', 'address', 'pobse', 'const');
        }])
        ->whereIn('voter_id', $constituency_ids);

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


} 