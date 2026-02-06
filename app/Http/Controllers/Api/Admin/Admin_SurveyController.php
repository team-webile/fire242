<?php

namespace App\Http\Controllers\Api\Admin;

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
use App\Models\User;
use App\Models\Question;
use App\Models\SurveyAnswer;
use App\Models\Answer;
use App\Models\Country;
use Illuminate\Support\Facades\DB;

class Admin_SurveyController extends Controller
{
    public function index(Request $request)
    {
        // Check if user is authenticated and has admin role
       
        if (!auth()->check() || auth()->user()->role->name !== 'Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }

        $query = Survey::with('voter');

        // Search fields based on Survey model's fillable columns
        // Search fields based on Survey model's fillable columns
        $searchableFields = [
            'sex' => $request->get('sex'),
            'marital_status' => $request->get('marital_status'),
            'employed' => $request->get('employed'),
            'children' => $request->get('children'), 
            'employment_type' => $request->get('employment_type'),
            'religion' => $request->get('religion'),
            'located' => $request->get('located'),
            'home_phone' => $request->get('home_phone'),
            'work_phone' => $request->get('work_phone'),
            'cell_phone' => $request->get('cell_phone'),
            'email' => $request->get('email'),
            'special_comments' => $request->get('special_comments'),
            'other_comments' => $request->get('other_comments'),
            'voting_for' => $request->get('voting_for'),
            'voted_in_2017' => $request->get('voted_in_2017'),
            'where_voted_in_2017' => $request->get('where_voted_in_2017'),
            'voted_in_house' => $request->get('voted_in_house'),
            'voter_id' => $request->get('voter_id'),
            'constituency_id' => $request->get('constituency_id'),
            'voter_first_name' => $request->get('voter_first_name'),
            'under_age_25' => $request->get('under_age_25'),
            'is_died' => $request->get('is_died'),
            'died_date' => $request->get('died_date'),
            'voting_decision' => $request->get('voting_decision')
        ]; 

        // Apply search filters
        foreach ($searchableFields as $field => $value) {
            if (!empty($value)) {
                if ($field === 'voter_id') {
                    $query->whereHas('voter', function($q) use ($value) {
                        $q->where('id', $value);
                    });
                }
                else if ($field === 'constituency_id') {
                    $query->whereHas('voter', function($q) use ($value) {
                        $q->where('constituency_id', $value);
                    });
                }
                else if (isset($request->start_date) && !empty($request->start_date)) {
                    $query->where('created_at', '>=', $request->start_date . ' 00:00:00');
                }

                if (isset($request->end_date) && !empty($request->end_date)) {
                    $query->where('created_at', '<=', $request->end_date . ' 23:59:59');
                }
                else if (in_array($field, ['employed', 'children', 'voted_in_2017'])) {
                    $query->where($field, filter_var($value, FILTER_VALIDATE_BOOLEAN));
                }
                else if (in_array($field, ['sex', 'marital_status', 'employment_type', 'religion'])) {
                    $query->where($field, $value);
                }
                else {
                    $query->where($field, 'LIKE', "%{$value}%");
                }
            }
        }
        
        $challenge = $request->input('challenge');
        if ($challenge === 'true') {
            $query->where('challenge', true);
        }
        else if ($challenge === 'false') {
            $query->where('challenge', false);
        }

        $underAge25 = $request->input('under_age_25');
        if ($underAge25 === 'yes') {
            $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
        }


        if($request->input('is_died') !== null && $request->input('is_died') !== '' ){ 
                $query->where('is_died', $request->input('is_died') );
          }


        // Add search by voter first name
        if ($request->filled('voter_first_name')) {
            $query->whereHas('voter', function($q) use ($request) {
                $q->where('first_name', 'LIKE', '%' . $request->get('voter_first_name') . '%');
            });
        } 
        // Get paginated results
        $surveys = $query->orderBy('id', 'desc')->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $surveys,
            'searchable_fields' => $searchableFields
        ]);
    }


    public function make_challange(Request $request, $id)
    {
        // Validate and cast to strict boolean -- challenge must be "true"/"false" string or boolean
        $challengeInput = $request->challenge;
        if ($challengeInput === 'true' || $challengeInput === true || $challengeInput === 1 || $challengeInput === '1') {
            $challenge = true;
        } else if ($challengeInput === 'false' || $challengeInput === false || $challengeInput === 0 || $challengeInput === '0') {
            $challenge = false;
        } else {
            return response()->json([
                'success' => false,
                'message' => "'challenge' parameter must be boolean ('true' or 'false')"
            ], 400);
        }

        // Now $challenge is guaranteed to be PHP boolean true/false
        Survey::where('id', $id)->update(['challenge' => DB::raw($challenge ? 'true' : 'false')]);

        return response()->json([
            'success' => true,
            'message' => 'Challenge status updated successfully'
        ]);
    }

 
    // public function show($id)
    // {
    //     if (!auth()->check() || auth()->user()->role->name !== 'Admin') {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Unauthorized - Admin access required'
    //         ], 403);
    //     }

    //     $survey = Survey::with('voter')->where('voter_id', $id)->first();

    //     return response()->json([
    //         'success' => true,
    //         'data' => $survey
    //     ]);
    // }

    public function show($id)
    {
      

        // $survey = Survey::with('voter')->where('voter_id', $id)
        //     ->first();
        
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
        if (!auth()->check() || auth()->user()->role->name !== 'Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }

        try {
            // Check Manager authorization
            if (!auth()->check() || auth()->user()->role->name !== 'Admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - Admin access required'
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
                'voting_for' => 'nullable|string',
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


        // Get constituencies names
        // No constituency restriction for admin, so fetch from all constituencies
        $query = Voter::select('voters.*', 'constituencies.name as constituency_name')
            ->join('constituencies', 'voters.const', '=', 'constituencies.id');
            
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

        $countries = Country::where('is_active', 'true')
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

    public function getQuestionsAnswers() 
    {
        $questions = Question::with(['answers:id,question_id,answer,position'])->orderBy('position', 'asc')->get();
        return response()->json(['success' => true, 'questions' => $questions]); 
    }
 

}