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