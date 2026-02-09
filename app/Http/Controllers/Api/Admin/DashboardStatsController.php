<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country; 
use App\Models\User; 
use App\Models\Survey;
use App\Models\UnregisteredVoter;
use App\Models\Voter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\Question;
use App\Models\Answer;
use App\Models\SurveyAnswer;
use DB;
class DashboardStatsController extends Controller
{
    public function getUserActivities(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 10);
            $page = $request->get('page', 1);

            $query = \Spatie\Activitylog\Models\Activity::with('causer')
                ->orderBy('created_at', 'desc');

            if ($request->has('email')) {
                $query->whereHas('causer', function ($q) use ($request) {
                    $q->where('email', $request->get('email'));
                });
            }

            if ($request->has('name')) {
                $query->whereHas('causer', function ($q) use ($request) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->get('name')) . '%']);
                });
            } 

            $activities = $query->paginate($perPage);

            $formattedActivities = $activities->through(function ($activity) {
                return [
                    'id' => $activity->id,
                    'description' => $activity->description,
                    'causer' => $activity->causer ? [
                        'id' => $activity->causer->id,
                        'name' => $activity->causer->name,
                        'email' => $activity->causer->email
                    ] : null,
                    'properties' => $activity->properties,
                    'created_at' => $activity->created_at
                ];
            });
            return response()->json([
                'success' => true,
                'data' => [
                    'current_page' => $activities->currentPage(),
                    'data' => $formattedActivities->items(),
                    'total' => $activities->total(),
                    'per_page' => $activities->perPage(),
                    'last_page' => $activities->lastPage(),
                    'from' => $activities->firstItem(),
                    'to' => $activities->lastItem()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving user activities',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // public function getUserActivities(Request $request)
    // {
    //     try {
    //         $perPage = $request->get('per_page', 10);
    //         $page = $request->get('page', 1);

    //         $activities = \Spatie\Activitylog\Models\Activity::with('causer')
    //             ->orderBy('created_at', 'desc')
    //             ->paginate($perPage);
 
    //         $formattedActivities = $activities->through(function ($activity) {
    //             return [
    //                 'id' => $activity->id,
    //                 'description' => $activity->description,
    //                 'causer' => $activity->causer ? [
    //                     'id' => $activity->causer->id,
    //                     'name' => $activity->causer->name,
    //                     'email' => $activity->causer->email
    //                 ] : null,
    //                 'properties' => $activity->properties,
    //                 'created_at' => $activity->created_at
    //             ];
    //         });
    //         return response()->json([
    //             'success' => true,
    //             'data' => [
    //                 'current_page' => $activities->currentPage(),
    //                 'data' => $formattedActivities->items(),
    //                 'total' => $activities->total(),
    //                 'per_page' => $activities->perPage(),
    //                 'last_page' => $activities->lastPage(),
    //                 'from' => $activities->firstItem(),
    //                 'to' => $activities->lastItem()
    //             ]
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error retrieving user activities',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }



  
    // public function index( Request $request)
    // {


    //     $existsInDatabase = $request->input('exists_in_database');

    //     $fnm = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
    //         ->where(function($query) {
    //             $query->where('voting_for', 'FNM')
    //                   ->orWhere('voting_for', 'Free National Movement');
    //         });

    //     if ($existsInDatabase === 'true') {
    //         $fnm->where('voters.exists_in_database', true);
    //     } elseif ($existsInDatabase === 'false') {
    //         $fnm->where('voters.exists_in_database', false);
    //     }
    //     $fnm = $fnm->count();

    //     $plp = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
    //         ->where(function($query) {
    //             $query->where('voting_for', 'PLP')
    //                   ->orWhere('voting_for', 'Progressive Liberal Party');
    //         });

    //     if ($existsInDatabase === 'true') {
    //         $plp->where('voters.exists_in_database', true);
    //     } elseif ($existsInDatabase === 'false') {
    //         $plp->where('voters.exists_in_database', false);
    //     }
    //     $plp = $plp->count();

    //     $other = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
    //         ->where(function($query) {
    //             $query->whereNotIn('voting_for', ['FNM', 'Free National Movement', 'PLP', 'Progressive Liberal Party'])
    //                   ->whereNotNull('voting_for');
    //         });

    //     if ($existsInDatabase === 'true') {
    //         $other->where('voters.exists_in_database', true);
    //     } elseif ($existsInDatabase === 'false') {
    //         $other->where('voters.exists_in_database', false);
    //     }
    //     $other = $other->count();

    //     $surveyed_voter = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
    //         ->whereNull('voting_for')
    //         ->where('voting_decision', 'undecided');

    //     if ($existsInDatabase === 'true') {
    //         $surveyed_voter->where('voters.exists_in_database', true);
    //     } elseif ($existsInDatabase === 'false') {
    //         $surveyed_voter->where('voters.exists_in_database', false);
    //     }
    //     $surveyed_voter = $surveyed_voter->count();
        
    //     $unknown = $surveyed_voter;

    //    $surveyedVoter =  Voter::with('user')
    //    ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date','surveys.user_id','surveys.voting_for')
    //    ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
    //    ->join('surveys', 'voters.id', '=', 'surveys.voter_id')
    //    ->whereExists(function ($query) {
    //        $query->select('id')
    //            ->from('surveys')
    //            ->whereColumn('surveys.voter_id', 'voters.id');
    //    })
    //    ->whereNull('surveys.voting_for')
    //    ->where('surveys.voting_decision','no');
   
    //    if ($existsInDatabase === 'true') {
    //         $surveyedVoter->where('voters.exists_in_database', true);
    //     } elseif ($existsInDatabase === 'false') {
    //         $surveyedVoter->where('voters.exists_in_database', false);
    //     }

    //    $naver_vote = $surveyedVoter->count();

    //     $first_time_voters_query = Voter::whereRaw("EXTRACT(YEAR FROM AGE(CURRENT_DATE, dob::date)) <= 25");
        
    //     if ($existsInDatabase === 'true') {
    //         $first_time_voters_query->where('exists_in_database', true);
    //     } elseif ($existsInDatabase === 'false') {
    //         $first_time_voters_query->where('exists_in_database', false);
    //     }
        
    //     $first_time_voters = $first_time_voters_query->count();

    //     $fnm_unregistered = UnregisteredVoter::where(function($query) {
    //         $query->where('party', 'FNM')
    //               ->orWhere('party', 'Free National Movement');
    //     })->count();

    //     $unregistered = UnregisteredVoter::count();

    //     $total_voters_query = Voter::query();
    //     if ($existsInDatabase === 'true') {
    //         $total_voters_query->where('exists_in_database', true);
    //     } elseif ($existsInDatabase === 'false') {
    //         $total_voters_query->where('exists_in_database', false);
    //     }
    //     $total_voters = $total_voters_query->count();

    //     $data = [
    //         'fnm' => $fnm,
    //         'plp' => $plp, 
    //         'coi' => 0,
    //         'other' => $other,
    //         'unknown' => $unknown,
    //         'naver_vote' => $naver_vote, 
    //         'first_time_voters' => $first_time_voters,
    //         'unregistered_total' => $unregistered,
    //         'unregistered_fnm' => $fnm_unregistered,
    //         'total_registered_voters' => $total_voters
    //     ];

    //     return response()->json([
    //         'success' => true,
    //         'data' => $data
    //     ]);
    // }



    public function index( Request $request)
    {


            $existsInDatabase = $request->input('exists_in_database');

            $fnm = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
            ->join(DB::raw("(
                SELECT DISTINCT ON (voter_id) id
                FROM surveys
                ORDER BY voter_id, created_at DESC
            ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
            ->where(function ($query) {
                $query->where('surveys.voting_for', 'FNM')
                    ->orWhere('surveys.voting_for', 'Free National Movement');
            });
            if ($existsInDatabase === 'true') {
                $fnm->where('voters.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $fnm->where('voters.exists_in_database', false);
            }
            $fnm = $fnm->count();

            $plp = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
            ->join(DB::raw("(
                SELECT DISTINCT ON (voter_id) id
                FROM surveys
                ORDER BY voter_id, created_at DESC
            ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
            ->where(function($query) {
                $query->where('voting_for', 'PLP')
                    ->orWhere('voting_for', 'Progressive Liberal Party');
            }); 
            

            if ($existsInDatabase === 'true') {
                $plp->where('voters.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $plp->where('voters.exists_in_database', false);
            }
            $plp = $plp->count();

            $coi = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
            ->join(DB::raw("(
                SELECT DISTINCT ON (voter_id) id
                FROM surveys
                ORDER BY voter_id, created_at DESC
            ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
            ->where(function($query) {
                $query->where('voting_for', 'COI')
                    ->orWhere('voting_for', 'Coalition of Independents');
            });

            if ($existsInDatabase === 'true') {
                $coi->where('voters.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $coi->where('voters.exists_in_database', false);
            }
            $coi = $coi->count();

            $other_parties = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
            ->join(DB::raw("(
                SELECT DISTINCT ON (voter_id) id
                FROM surveys
                ORDER BY voter_id, created_at DESC
            ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
            ->where(function($query) {
                $query->whereNotIn('voting_for', ['FNM', 'Free National Movement', 'PLP', 'Progressive Liberal Party', 'COI', 'Coalition of Independents', 'Other'])
                    ->whereNotNull('voting_for');
            });
            
            

            if ($existsInDatabase === 'true') {
                $other_parties->where('voters.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $other_parties->where('voters.exists_in_database', false);
            }
            $other_parties = $other_parties->count();

            
            $other = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
            ->join(DB::raw("(
                SELECT DISTINCT ON (voter_id) id
                FROM surveys
                ORDER BY voter_id, created_at DESC
            ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
            ->where('voting_for', 'Other');
            
            

            if ($existsInDatabase === 'true') {
                $other->where('voters.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $other->where('voters.exists_in_database', false);
            }
            $other = $other->count();

        
    
            $unknown = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
            ->join(DB::raw("(
                SELECT DISTINCT ON (voter_id) id
                FROM surveys 
                ORDER BY voter_id, created_at DESC
            ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
            ->whereNull('voting_for')
            ->where('voting_decision', 'undecided');
            
            

            if ($existsInDatabase === 'true') {
                $unknown->where('voters.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $unknown->where('voters.exists_in_database', false);
            }
            $unknown = $unknown->count();

            $surveyed_voter = Voter::with('user')
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
            ->join(DB::raw("(
                SELECT DISTINCT ON (voter_id) * 
                FROM surveys 
                ORDER BY voter_id, created_at DESC
            ) as surveys"), 'voters.id', '=', 'surveys.voter_id');

        if ($existsInDatabase === 'true') {
            $surveyed_voter->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $surveyed_voter->where('voters.exists_in_database', false);
        }

        $surveyed_voter = $surveyed_voter->count();
            
    
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
        });
        
        if ($existsInDatabase === 'true') {
            $uncategorized->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $uncategorized->where('voters.exists_in_database', false);
        }
        $uncategorized = $uncategorized->count();

        

            $first_time_voters = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
            ->join(DB::raw("(
                SELECT DISTINCT ON (voter_id) id
                FROM surveys
                ORDER BY voter_id, created_at DESC
            ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
            ->whereRaw("EXTRACT(YEAR FROM AGE(CURRENT_DATE, dob::date)) <= 25");
            
            if ($existsInDatabase === 'true') {
                $first_time_voters->where('voters.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $first_time_voters->where('voters.exists_in_database', false);
            }
            
            $first_time_voters = $first_time_voters->count();

            $fnm_unregistered = UnregisteredVoter::where(function($query) {
                $query->where('party', 'FNM')
                    ->orWhere('party', 'Free National Movement');
            })->count();

            $unregistered = UnregisteredVoter::count();

            $total_voters_query = Voter::query();
            if ($existsInDatabase === 'true') {
                $total_voters_query->where('exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $total_voters_query->where('exists_in_database', false);
            }
            $total_voters = $total_voters_query->count();

            $plus_all = $fnm + $plp + $coi + $other + $other_parties + $unknown + $naver_vote + $uncategorized;
            $data = [
                'fnm' => $fnm,
                'plp' => $plp,
                'coi' => $coi, 
                'other' => $other,
                'other_parties' => $other_parties,
                'unknown' => $unknown,
                'naver_vote' => $naver_vote,
                'uncategorized' => $uncategorized, 
                'first_time_voters' => $first_time_voters,
                'unregistered_total' => $unregistered,
                'unregistered_fnm' => $fnm_unregistered,
                'total_registered_voters' => $total_voters ,
                'surveyed_voters' => $surveyed_voter,
                'plus_all' => $plus_all,
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
    }

     

    protected function applyFilters($query, Request $request)
    {
        $searchableFields = $request->all();
 
        foreach ($searchableFields as $field => $value) {
            if (!empty($value)) {
                if ($field === 'voter_id') {
                    $query->whereHas('voter', function($q) use ($value) {
                        $q->where('id', $value);
                    });
                }

                if ($field === 'voter') {
                    $query->whereHas('voter', function($q) use ($value) {
                        $q->where('id', $value);
                    });
                }
                else if ($field === 'constituency_id') {
                    $query->whereHas('voter', function($q) use ($value) {
                        $q->where('constituency_id', $value);
                    });
                }
                else if ($field === 'voter_first_name') {
                    $query->whereHas('voter', function($q) use ($value) {
                        $q->where('first_name', 'LIKE', "%{$value}%");
                    });
                }
                else if (in_array($field, ['employed', 'children', 'voted_in_2017'])) {
                    $query->where($field, filter_var($value, FILTER_VALIDATE_BOOLEAN));
                }
                else if (in_array($field, ['sex', 'marital_status', 'employment_type', 'religion'])) {
                    $query->where($field, $value);
                }
                
            }
        }

        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', $request->start_date . ' 00:00:00');
        }

        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', $request->end_date . ' 23:59:59');
        }
 
        return $query;
    }

    public function getFnmList(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        
        $query = Survey::with('voter')->where('voting_for', 'FNM')
        ->orWhere('voting_for', 'Free National Movement');

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
            'start_date' => $request->get('start_date'),
            'end_date' => $request->get('end_date')
        ]; 


        $existsInDatabase = $request->input('exists_in_database');
        if ($existsInDatabase === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false);
        }

      
        if (isset($request->start_date) && !empty($request->start_date)) {
            $query->where('surveys.created_at', '>=', $request->start_date . ' 00:00:00');
        }

        if (isset($request->end_date) && !empty($request->end_date)) {
            $query->where('surveys.created_at', '<=', $request->end_date . ' 23:59:59');
        } 
        
          
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

       

        $underAge25 = $request->input('under_age_25');
        if ($underAge25 === 'yes') {
            $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
        }

        // Add search by voter first name
        if ($request->filled('voter_first_name')) {
            $query->whereHas('voter', function($q) use ($request) {
                $q->where('first_name', 'LIKE', '%' . $request->get('voter_first_name') . '%');
            });
        }     

        

        $data = $query->paginate($perPage);
 
        return response()->json([
            'success' => true,
            'data' => $data,
            'searchable_fields' => $searchableFields
        ]);
    }

    public function getPlpList(Request $request) 
    {
        $perPage = $request->get('per_page', 10);

        $query = Survey::with('voter')
            ->where(function($query) {
                $query->where('voting_for', 'PLP')
                      ->orWhere('voting_for', 'Progressive Liberal Party');
            });

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
                'under_age_25' => $request->get('under_age_25')
            ]; 
    
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

        if (isset($request->start_date) && !empty($request->start_date)) {
            $query->where('surveys.created_at', '>=', $request->start_date . ' 00:00:00');
        }

        if (isset($request->end_date) && !empty($request->end_date)) {
            $query->where('surveys.created_at', '<=', $request->end_date . ' 23:59:59');
        } 

        $underAge25 = $request->input('under_age_25');
        if ($underAge25 === 'yes') {
            $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
        }

        // Add search by voter first name
        if ($request->filled('voter_first_name')) {
            $query->whereHas('voter', function($q) use ($request) {
                $q->where('first_name', 'LIKE', '%' . $request->get('voter_first_name') . '%');
            });
        }     

        $data = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $data,
            'searchable_fields' => $searchableFields
        ]);
    } 

    public function getOtherList(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = Survey::with('voter')
            ->where(function($query) {
                $query->whereNotIn('voting_for', ['FNM', 'Free National Movement', 'PLP', 'Progressive Liberal Party'])
                      ->whereNotNull('voting_for');
            });

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
                'under_age_25' => $request->get('under_age_25')
            ]; 
    
            // Apply search filters
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
                $query->where('surveys.created_at', '>=', $request->start_date . ' 00:00:00');
            }
    
            if ($request->has('end_date')) {
                $query->where('surveys.created_at', '<=', $request->end_date . ' 23:59:59');
            } 
    
            $underAge25 = $request->input('under_age_25');
        if ($underAge25 === 'yes') {
            $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
        }
    
            // Add search by voter first name
            if ($request->filled('voter_first_name')) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->where('first_name', 'LIKE', '%' . $request->get('voter_first_name') . '%');
                });
            }     
    

        $data = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $data,
            'searchable_fields' => $searchableFields
        ]);
    }

    public function getUnknownList(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        // Get IDs of voters who have taken the survey
        $surveyed_voter_ids = Survey::pluck('voter_id');
 
        
        $searchableFields = [
            'first_name' => 'First Name',
            'second_name' => 'Second Name',
            'surname' => 'Surname', 
            'address' => 'Address',
            'voter' => 'Voter ID',
            'const' => 'Constituency ID',
            'constituency_name' => 'Constituency Name',
            'polling' => 'Polling Station'
        ];  

        // Get search parameters
        $const = $request->input('const');
        $surname = $request->input('surname');
        $firstName = $request->input('first_name');
        $secondName = $request->input('second_name');
        $address = $request->input('address');
        $voterId = $request->input('voter');
        $constituencyName = $request->input('constituency_name');
        $constituencyId = $request->input('const');
        $underAge25 = $request->input('under_age_25');
        $polling = $request->input('polling');
        $houseNumber = $request->input('house_number');
        $pobse = $request->input('pobse');
        $pobis = $request->input('pobis');
        $pobcn = $request->input('pobcn');
        $existsInDatabase = $request->input('exists_in_database');


        // Apply filters

        
        // Get voters who haven't taken the survey
        $query = Voter::select('voters.*', 'constituencies.name as constituency_name')
                    ->join('constituencies', 'voters.const', '=', 'constituencies.id')
                    ->whereNotIn('voters.voter', $surveyed_voter_ids);

                    
     
        $query = Survey::with('user')->join('voters', 'surveys.voter_id', '=', 'voters.id')
        ->join(DB::raw("(
            SELECT DISTINCT ON (voter_id) id
            FROM surveys
            ORDER BY voter_id, created_at DESC
        ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
        ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date', 'surveys.user_id', 'surveys.voting_for')
        ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
        ->where('surveys.voting_for','undecided')->orderBy('surveys.id', 'desc');

        // Apply exists_in_database filter
        if ($existsInDatabase === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false);
        }

        if (!empty($polling) && is_numeric($polling)) {
            $query->where('voters.polling', $polling);
        }

        if ($underAge25 === 'yes') {
            $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
        }
        if (!empty($const)) {
            $query->where('voters.const', $const);
        }
        if (!empty($surname)) {
            $query->whereRaw('LOWER(voters.surname) LIKE ?', ['%' . strtolower($surname) . '%']);
        }

        if (!empty($firstName)) {
            $query->whereRaw('LOWER(voters.first_name) LIKE ?', ['%' . strtolower($firstName) . '%']);
        }

        if (!empty($secondName)) {
            $query->whereRaw('LOWER(voters.second_name) LIKE ?', ['%' . strtolower($secondName) . '%']);
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
            $query->where('voters.voter', $voterId);
        }

        if (!empty($constituencyName)) {
            $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
        }

        if (!empty($constituencyId)) {
            $query->where('voters.const', $constituencyId);
        }

        $data = $query->paginate($perPage);
        return response()->json([
            'success' => true,
            'data' => $data,
            'searchable_fields' => $searchableFields
        ]);
    }

    public function getFirstTimeVotersList(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $searchableFields = [
            'first_name' => 'First Name',
            'second_name' => 'Second Name',
            'surname' => 'Surname', 
            'address' => 'Address',
            'voter' => 'Voter ID',
            'const' => 'Constituency ID',
            'constituency_name' => 'Constituency Name',
            'polling' => 'Polling Station'
        ];  

        // Get search parameters
        $const = $request->input('const');
        $surname = $request->input('surname');
        $firstName = $request->input('first_name');
        $secondName = $request->input('second_name');
        $address = $request->input('address');
        $voterId = $request->input('voter');
        $constituencyName = $request->input('constituency_name');
        $constituencyId = $request->input('const');
        $underAge25 = $request->input('under_age_25');
        $polling = $request->input('polling');
        $houseNumber = $request->input('house_number');
        $pobse = $request->input('pobse');
        $pobis = $request->input('pobis');
        $pobcn = $request->input('pobcn');
        $existsInDatabase = $request->input('exists_in_database');

        $sortBy = $request->input('sort_by'); // voter, const, or polling
        $sortOrder = $request->input('sort_order', 'asc'); // asc or desc


        $query = Voter::select('voters.*', 'constituencies.name as constituency_name')
            ->join('constituencies', 'voters.const', '=', 'constituencies.id')
            ->whereRaw("EXTRACT(YEAR FROM AGE(CURRENT_DATE, dob::date)) <= 25");

        // Apply exists_in_database filter
        if ($existsInDatabase === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false);
        }
 
        // For first time voters, we'll only apply relevant filters
        if (!empty($polling) && is_numeric($polling)) {
            $query->where('voters.polling', $polling);
        }

        if ($underAge25 === 'yes') {
            $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
        }
        if (!empty($const)) {
            $query->where('voters.const', $const);
        }
        if (!empty($surname)) {
            $query->whereRaw('LOWER(voters.surname) LIKE ?', ['%' . strtolower($surname) . '%']);
        }

        if (!empty($firstName)) {
            $query->whereRaw('LOWER(voters.first_name) LIKE ?', ['%' . strtolower($firstName) . '%']);
        }

        if (!empty($secondName)) {
            $query->whereRaw('LOWER(voters.second_name) LIKE ?', ['%' . strtolower($secondName) . '%']);
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
            $query->where('voters.voter', $voterId);
        }

        if (!empty($constituencyName)) {
            $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
        }

        if (!empty($constituencyId)) {
            $query->where('voters.const', $constituencyId);
        }


        if (!empty($sortBy)) {
            switch ($sortBy) {
                case 'voter':
                    $query->orderBy('voters.voter', $sortOrder);
                    break;
                case 'const':
                    $query->orderBy('voters.const', $sortOrder);
                    break;
                case 'polling':
                    $query->orderBy('voters.polling', $sortOrder);
                    break;
                case 'first_name':
                    $query->orderByRaw('LOWER(voters.first_name) ' . strtoupper($sortOrder));
                    break;
                case 'last_name':
                    $query->orderByRaw('LOWER(TRIM(voters.surname)) ' . strtoupper($sortOrder)); 
                    break;
                default:
                $query->orderBy('voters.id', 'desc');
                    break;
            }
        } else {
            // Default sorting
            $query->orderBy('voters.id', 'desc');
        }

        $data = $query->paginate($perPage);
        return response()->json([
            'success' => true, 
            'data' => $data,
            'searchable_fields' => $searchableFields
        ]);
    }

    public function getUnregisteredList(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $data = UnregisteredVoter::with('voter')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    } 
    public function statsList(Request $request,$type)
    { 
        $surveyor = User::where('id', Auth::user()->id)->first();
        $type = $type;
        $perPage = $request->get('per_page', 20);
      
        $results = match($type) {
            'registered' => $this->getRegisteredVoters($request,$surveyor, $perPage),
            'fnm' => $this->getFNMVoters($request,$surveyor, $perPage),
            'coi' => $this->getCOIVoters($request,$surveyor, $perPage),
            'plp' => $this->getPLPVoters($request,$surveyor, $perPage), 
            'other' => $this->getOtherPartyVoters($request,$surveyor, $perPage),
            'total_others' => $this->getOtherVoters($request,$surveyor, $perPage),
            'total_other_parties' => $this->getOtherPartyVoters($request,$surveyor, $perPage),
            'total_naver_vote' => $this->getTotalnaverVote($request,$surveyor, $perPage),
            'unknown' => $this->getTotalUnknown($request,$surveyor, $perPage),
            'first_time' => $this->getFirstTimeVoters($request,$surveyor, $perPage), 
            'unregistered_fnm' => $this->getunregistered_fnmVoters($request,$surveyor, $perPage), 
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

    private function getunregistered_fnmVoters($request,$surveyor, $perPage){

        $query = UnregisteredVoter::with(['voter' => function($query) {
            $query->select('id', 'voter', 'first_name', 'second_name', 'address', 'pobse', 'const');
        }])->where('party', 'FNM')->orwhere('party', 'Free National Movement');


        $sortBy = $request->input('sort_by'); // voter, const, or polling
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
            $query->where('first_name', 'LIKE', '%' . $request->first_name . '%');
             
        } 
       
        if (isset($request->last_name) && !empty($request->last_name)) {
            $query->where('last_name', 'LIKE', '%' . $request->last_name . '%');
        }
        
        // if (isset($request->phone_number) && !empty($request->phone_number)) {
        //     $query->where('phone_number', 'LIKE', '%' . $request->phone_number . '%');
        // }

        // if (isset($request->new_email) && !empty($request->new_email)) {
        //     $query->whereRaw('LOWER(new_email) LIKE ?', ['%' . strtolower($request->new_email) . '%']);
        // }

        // if (isset($request->new_address) && !empty($request->new_address)) {
        //     $query->whereRaw('LOWER(new_address) LIKE ?', ['%' . strtolower($request->new_address) . '%']);
        // }

        if (isset($request->survey_id) && !empty($request->survey_id)) {
            $query->where('survey_id', 'LIKE', '%' . $request->survey_id . '%');
        }

        // if (isset($request->user_id) && !empty($request->user_id)) {
        //     $query->where('user_id', 'LIKE', '%' . $request->user_id . '%');
        // }

        if (isset($request->voter_id) && !empty($request->voter_id)) {
            $query->where('voter_id', 'LIKE', '%' . $request->voter_id . '%');
        }

        // // Search in related voter fields
        // if (isset($request->voter_first_name) && !empty($request->voter_first_name)) {
        //     $query->whereHas('voter', function($q) use ($request) {
        //         $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->voter_first_name) . '%']);
        //     });
        // }

        // if (isset($request->voter_second_name) && !empty($request->voter_second_name)) {
        //     $query->whereHas('voter', function($q) use ($request) {
        //         $q->whereRaw('LOWER(second_name) LIKE ?', ['%' . strtolower($request->voter_second_name) . '%']);
        //     });
        // }

        // if (isset($request->voter_id) && !empty($request->voter_id)) {
        //     $query->whereHas('voter', function($q) use ($request) {
        //         $q->where('voter', 'LIKE', '%' . $request->voter_id . '%');
        //     });
        // }

        // if (isset($request->voter_address) && !empty($request->voter_address)) {
        //     $query->whereHas('voter', function($q) use ($request) {
        //         $q->whereRaw('LOWER(address) LIKE ?', ['%' . strtolower($request->voter_address) . '%']);
        //     });
        // }

        // // Add filters
        // if (isset($request->gender) && !empty($request->gender)) {
        //     $query->whereRaw('LOWER(gender) = ?', [strtolower($request->gender)]);
        // }

        // if (isset($request->date_from) && !empty($request->date_from) && isset($request->date_to) && !empty($request->date_to)) {
        //     $query->whereBetween('date_of_birth', [$request->date_from, $request->date_to]);
        // } 

        // Add sorting
        $query->orderBy('id', 'desc'); 

        // Get paginated results
        $unregisteredVoters = $query->paginate($perPage);
        return $unregisteredVoters;
        // Build search parameters object
         
    }
    private function getRegisteredVoters($request,$surveyor, $perPage)
    {
        $query = Voter::query()
            ->whereIn('const', explode(',', $surveyor->constituency_id));

        if ($request->exists_in_database === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($request->exists_in_database === 'false') {
            $query->where('voters.exists_in_database', false);
        }

        return $query->paginate($perPage); 
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
        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');
        $existsInDatabase = $request->input('exists_in_database');
       // Get sorting parameters
       $sortBy = $request->input('sort_by'); // voter, const, or polling
       $sortOrder = $request->input('sort_order', 'asc'); // asc or desc

    // $query = Voter::with('user')
    //     ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date','surveys.user_id','surveys.voting_for')
    //     ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
    //     ->join('surveys', 'voters.id', '=', 'surveys.voter_id')
    //     ->whereExists(function ($query) {
    //         $query->select('id')
    //             ->from('surveys')
    //             ->whereColumn('surveys.voter_id', 'voters.id');
    //     })
    //     ->where(function($query) {
    //         $query->where('voting_for', 'FNM')
    //               ->orWhere('voting_for', 'Free National Movement');
    //     })
    //     ->orderBy('surveys.id', 'desc');


        $query =Survey::with('user')->join('voters', 'surveys.voter_id', '=', 'voters.id')
        ->join(DB::raw("(
            SELECT DISTINCT ON (voter_id) id
            FROM surveys
            ORDER BY voter_id, created_at DESC
        ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
        ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date', 'surveys.user_id', 'surveys.voting_for','surveys.cell_phone','surveys.cell_phone_code')
        ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
        ->where(function($query) {
            $query->where('surveys.voting_for', 'FNM')
                    ->orWhere('surveys.voting_for', 'Free National Movement');
        });


        if ($existsInDatabase === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false);
        }
        // Apply exists_in_database filter

        if (!empty($start_date)) {
            $query->where('surveys.created_at', '>=', $start_date . ' 00:00:00');
        }

        if (!empty($end_date)) {
            $query->where('surveys.created_at', '<=', $end_date . ' 23:59:59');
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
            $query->whereRaw('LOWER(voters.first_name) LIKE ?', ['%' . strtolower($firstName) . '%']);
        }

        if (!empty($secondName)) {
            $query->whereRaw('LOWER(voters.second_name) LIKE ?', ['%' . strtolower($secondName) . '%']);
        }

        if (!empty($last_name)) {
            $query->whereRaw('LOWER(voters.surname) LIKE ?', ['%' . strtolower($last_name) . '%']);
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



       
        if (!empty($sortBy)) {
            switch ($sortBy) {
                case 'voter':
                    $query->orderBy('voters.voter', $sortOrder);
                    break;
                case 'const':
                    $query->orderBy('voters.const', $sortOrder);
                    break;
                case 'polling':
                    $query->orderBy('voters.polling', $sortOrder);
                    break;
                case 'first_name':
                    $query->orderByRaw('LOWER(voters.first_name) ' . strtoupper($sortOrder));
                    break;
                case 'last_name':
                    $query->orderByRaw('LOWER(TRIM(voters.surname)) ' . strtoupper($sortOrder)); 
                    break;
                default:
                $query->orderBy('surveys.id', 'desc');
                    break;
            }
        } else {
            // Default sorting
            $query->orderBy('surveys.id', 'desc');
        }

        // Get paginated results with all surveys
        $voters = $query->paginate($perPage);

        
        return $voters; 

    }
    private function getCOIVoters($request,$surveyor, $perPage) 
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
        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');
        $existsInDatabase = $request->input('exists_in_database');
        // Get sorting parameters
        $sortBy = $request->input('sort_by'); // voter, const, or polling
        $sortOrder = $request->input('sort_order', 'asc'); // asc or desc


    // $query = Voter::with('user')
    //     ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date','surveys.user_id','surveys.voting_for')
    //     ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
    //     ->join('surveys', 'voters.id', '=', 'surveys.voter_id')
    //     ->whereExists(function ($query) {
    //         $query->select('id')
    //             ->from('surveys')
    //             ->whereColumn('surveys.voter_id', 'voters.id');
    //     })
    //     ->where(function($query) {
    //         $query->where('voting_for', 'FNM')
    //               ->orWhere('voting_for', 'Free National Movement');
    //     })
    //     ->orderBy('surveys.id', 'desc');


        $query =Survey::with('user')->join('voters', 'surveys.voter_id', '=', 'voters.id')
        ->join(DB::raw("(
            SELECT DISTINCT ON (voter_id) id
            FROM surveys
            ORDER BY voter_id, created_at DESC
        ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
        ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date', 'surveys.user_id', 'surveys.voting_for', 'surveys.voting_for','surveys.cell_phone','surveys.cell_phone_code')
        ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
        ->where(function($query) {
            $query->where('surveys.voting_for', 'COI')
                    ->orWhere('surveys.voting_for', 'Coalition of Independents'); 
        });


        if ($existsInDatabase === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false);
        }
        // Apply exists_in_database filter

        if (!empty($start_date)) {
            $query->where('surveys.created_at', '>=', $start_date . ' 00:00:00');
        }

        if (!empty($end_date)) {
            $query->where('surveys.created_at', '<=', $end_date . ' 23:59:59');
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
            $query->whereRaw('LOWER(voters.first_name) LIKE ?', ['%' . strtolower($firstName) . '%']);
        }

        if (!empty($secondName)) {
            $query->whereRaw('LOWER(voters.second_name) LIKE ?', ['%' . strtolower($secondName) . '%']);
        }

        if (!empty($last_name)) {
            $query->whereRaw('LOWER(voters.surname) LIKE ?', ['%' . strtolower($last_name) . '%']);
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


        if (!empty($sortBy)) {
            switch ($sortBy) {
                case 'voter':
                    $query->orderBy('voters.voter', $sortOrder);
                    break;
                case 'const':
                    $query->orderBy('voters.const', $sortOrder);
                    break;
                case 'polling':
                    $query->orderBy('voters.polling', $sortOrder);
                    break;
                case 'first_name':
                    $query->orderByRaw('LOWER(TRIM(voters.first_name)) ' . strtoupper($sortOrder));
                    break;
                case 'last_name':
                    $query->orderByRaw('LOWER(TRIM(voters.surname)) ' . strtoupper($sortOrder));
                    break;
                default:
                     $query->orderBy('surveys.id', 'desc');
                    break;
            }
        } else {
            $query->orderBy('surveys.id', 'desc');
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
        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');
        $existsInDatabase = $request->input('exists_in_database');
        $sortBy = $request->input('sort_by'); // voter, const, or polling
        $sortOrder = $request->input('sort_order', 'asc'); // asc or desc
    
    
        // $query = Voter::with('user')
        //     ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date','surveys.user_id','surveys.voting_for')
        //     ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
        //     ->join('surveys', 'voters.id', '=', 'surveys.voter_id')
        //     ->whereExists(function ($query) {
        //         $query->select('id')
        //             ->from('surveys')
        //             ->whereColumn('surveys.voter_id', 'voters.id');
        //     })
        //     ->where(function($query) {
        //         $query->where('voting_for', 'PLP')
        //               ->orWhere('voting_for', 'Progressive Liberal Party');
        //     })
        //     ->orderBy('surveys.id', 'desc');


            $query = Survey::with('user')->join('voters', 'surveys.voter_id', '=', 'voters.id')
            ->join(DB::raw("(
                SELECT DISTINCT ON (voter_id) id
                FROM surveys
                ORDER BY voter_id, created_at DESC
            ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
            ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date', 'surveys.user_id', 'surveys.voting_for', 'surveys.voting_for','surveys.cell_phone','surveys.cell_phone_code')
            ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
            ->where(function($query) {
                $query->where('voting_for', 'PLP')
                ->orWhere('voting_for', 'Progressive Liberal Party');
            }); 

            // Apply exists_in_database filter
            if ($existsInDatabase === 'true') {
                $query->where('voters.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $query->where('voters.exists_in_database', false);
            }

            if (!empty($start_date)) {
                $query->where('surveys.created_at', '>=', $start_date . ' 00:00:00');
            }

            if (!empty($end_date)) {
                $query->where('surveys.created_at', '<=', $end_date . ' 23:59:59');
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
                $query->whereRaw('LOWER(voters.first_name) LIKE ?', ['%' . strtolower($firstName) . '%']);
            }
    
            if (!empty($secondName)) {
                $query->whereRaw('LOWER(voters.second_name) LIKE ?', ['%' . strtolower($secondName) . '%']);
            }
            
            if (!empty($last_name)) {
                $query->whereRaw('LOWER(voters.surname) LIKE ?', ['%' . strtolower($last_name) . '%']);
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
            


            if (!empty($sortBy)) {
                switch ($sortBy) {
                    case 'voter':
                        $query->orderBy('voters.voter', $sortOrder);
                        break;
                    case 'const':
                        $query->orderBy('voters.const', $sortOrder);
                        break;
                    case 'polling':
                        $query->orderBy('voters.polling', $sortOrder);
                        break;
                    case 'first_name':
                        $query->orderByRaw('LOWER(voters.first_name) ' . strtoupper($sortOrder));
                        break;
                    case 'last_name':
                        $query->orderByRaw('LOWER(TRIM(voters.surname)) ' . strtoupper($sortOrder)); 
                        break;
                    default:
                    $query->orderBy('surveys.id', 'desc');
                        break;
                }
            } else {
                // Default sorting
                $query->orderBy('surveys.id', 'desc');
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
        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');
        $existsInDatabase = $request->input('exists_in_database');
        $sortBy = $request->input('sort_by'); // voter, const, or polling
        $sortOrder = $request->input('sort_order', 'asc'); // asc or desc
    
    
        // $query = Voter::with('user')
        //     ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date','surveys.user_id','surveys.voting_for')
        //     ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
        //     ->join('surveys', 'voters.id', '=', 'surveys.voter_id')
        //     ->whereExists(function ($query) {
        //         $query->select('id')
        //             ->from('surveys')
        //             ->whereColumn('surveys.voter_id', 'voters.id');
        //     })
        //     ->where(function($query) {
        //         $query->whereNotIn('voting_for', ['FNM', 'Free National Movement', 'PLP', 'Progressive Liberal Party',])
        //               ->whereNotNull('voting_for');
        //     })
        //     ->orderBy('surveys.id', 'desc');


        $query = Survey::with('user')->join('voters', 'surveys.voter_id', '=', 'voters.id')
            ->join(DB::raw("(
                SELECT DISTINCT ON (voter_id) id
                FROM surveys
                ORDER BY voter_id, created_at DESC
            ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
            ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date', 'surveys.user_id', 'surveys.voting_for', 'surveys.voting_for','surveys.cell_phone','surveys.cell_phone_code')
            ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
              ->where(function($query) {
                $query->whereNotIn('voting_for', ['FNM', 'Free National Movement', 'PLP', 'Progressive Liberal Party', 'COI', 'Coalition of Independents', 'Other'])
                     ->whereNotNull('voting_for');
             }); 



     
            // Apply exists_in_database filter
            if ($existsInDatabase === 'true') {
                $query->where('voters.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $query->where('voters.exists_in_database', false);
            }
     
            if (!empty($start_date)) {
                $query->where('surveys.created_at', '>=', $start_date . ' 00:00:00');
            }

            if (!empty($end_date)) {
                $query->where('surveys.created_at', '<=', $end_date . ' 23:59:59');
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
                $query->whereRaw('LOWER(voters.first_name) LIKE ?', ['%' . strtolower($firstName) . '%']);
            }
    
            if (!empty($secondName)) {
                $query->whereRaw('LOWER(voters.second_name) LIKE ?', ['%' . strtolower($secondName) . '%']);
            }
            
            if (!empty($last_name)) {
                $query->whereRaw('LOWER(voters.surname) LIKE ?', ['%' . strtolower($last_name) . '%']);
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
            

            if (!empty($sortBy)) {
                switch ($sortBy) {
                    case 'voter':
                        $query->orderBy('voters.voter', $sortOrder);
                        break;
                    case 'const':
                        $query->orderBy('voters.const', $sortOrder);
                        break;
                    case 'polling':
                        $query->orderBy('voters.polling', $sortOrder);
                        break;
                    case 'first_name':
                        $query->orderByRaw('LOWER(voters.first_name) ' . strtoupper($sortOrder));
                        break;
                    case 'last_name':
                        $query->orderByRaw('LOWER(TRIM(voters.surname)) ' . strtoupper($sortOrder)); 
                        break;
                    default:
                    $query->orderBy('surveys.id', 'desc');
                        break;
                }
            } else {
                // Default sorting
                $query->orderBy('surveys.id', 'desc');
            }

            // Get paginated results with all surveys
            $voters = $query->paginate($perPage);
    
            
            return $voters; 

    }
    private function getOtherVoters($request,$surveyor, $perPage)
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
        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');
        $existsInDatabase = $request->input('exists_in_database');
        $sortBy = $request->input('sort_by'); // voter, const, or polling
        $sortOrder = $request->input('sort_order', 'asc'); // asc or desc

    
    
        // $query = Voter::with('user')
        //     ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date','surveys.user_id','surveys.voting_for')
        //     ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
        //     ->join('surveys', 'voters.id', '=', 'surveys.voter_id')
        //     ->whereExists(function ($query) {
        //         $query->select('id')
        //             ->from('surveys')
        //             ->whereColumn('surveys.voter_id', 'voters.id');
        //     })
        //     ->where(function($query) {
        //         $query->whereNotIn('voting_for', ['FNM', 'Free National Movement', 'PLP', 'Progressive Liberal Party',])
        //               ->whereNotNull('voting_for');
        //     })
        //     ->orderBy('surveys.id', 'desc');


        $query = Survey::with('user')->join('voters', 'surveys.voter_id', '=', 'voters.id')
            ->join(DB::raw("(
                SELECT DISTINCT ON (voter_id) id
                FROM surveys
                ORDER BY voter_id, created_at DESC
            ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
            ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date', 'surveys.user_id', 'surveys.voting_for')
            ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
              ->where('voting_for','Other'); 



     
            // Apply exists_in_database filter
            if ($existsInDatabase === 'true') {
                $query->where('voters.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $query->where('voters.exists_in_database', false);
            }
     
            if (!empty($start_date)) {
                $query->where('surveys.created_at', '>=', $start_date . ' 00:00:00');
            }

            if (!empty($end_date)) {
                $query->where('surveys.created_at', '<=', $end_date . ' 23:59:59');
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
                $query->whereRaw('LOWER(voters.first_name) LIKE ?', ['%' . strtolower($firstName) . '%']);
            }
    
            if (!empty($secondName)) {
                $query->whereRaw('LOWER(voters.second_name) LIKE ?', ['%' . strtolower($secondName) . '%']);
            }
            
            if (!empty($last_name)) {
                $query->whereRaw('LOWER(voters.surname) LIKE ?', ['%' . strtolower($last_name) . '%']);
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
            

            if (!empty($sortBy)) {
                switch ($sortBy) {
                    case 'voter':
                        $query->orderBy('voters.voter', $sortOrder);
                        break;
                    case 'const':
                        $query->orderBy('voters.const', $sortOrder);
                        break;
                    case 'polling':
                        $query->orderBy('voters.polling', $sortOrder);
                        break;
                    case 'first_name':
                        $query->orderByRaw('LOWER(voters.first_name) ' . strtoupper($sortOrder));
                        break;
                    case 'last_name':
                        $query->orderByRaw('LOWER(TRIM(voters.surname)) ' . strtoupper($sortOrder)); 
                        break;
                    default:
                    $query->orderBy('surveys.id', 'desc');
                        break;
                }
            } else {
                // Default sorting
                $query->orderBy('surveys.id', 'desc');
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
        if ($request->exists_in_database === 'true') { 
            $query->where('voters.exists_in_database', true);
        } elseif ($request->exists_in_database === 'false') {
            $query->where('voters.exists_in_database', false);
        } 
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
        $surveys = $query->orderBy('id', 'desc')->paginate($perPage);

        return $surveys;
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
            $sortBy = $request->input('sort_by'); // voter, const, or polling
            $sortOrder = $request->input('sort_order', 'asc'); // asc or desc
    
    
        // $query = Voter::with('user')
        // ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date','surveys.user_id','surveys.voting_for')
        // ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
        // ->join('surveys', 'voters.id', '=', 'surveys.voter_id')
        // ->whereExists(function ($query) {
        //     $query->select('id')
        //         ->from('surveys')
        //         ->whereColumn('surveys.voter_id', 'voters.id');
        // })
        // ->whereNull('surveys.voting_for')
        // ->where('surveys.voting_decision','no')
        // ->orderBy('surveys.id', 'desc');


     

            $query =Survey::with('user')->join('voters', 'surveys.voter_id', '=', 'voters.id')
            ->join(DB::raw("(
                SELECT DISTINCT ON (voter_id) id
                FROM surveys
                ORDER BY voter_id, created_at DESC
            ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
            ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date', 'surveys.user_id', 'surveys.voting_for','surveys.cell_phone','surveys.cell_phone_code')
            ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
            ->where('voting_decision', 'no')->whereNull('voting_for');
    

           
            // Apply exists_in_database filter
            if ($existsInDatabase === 'true') {
                $query->where('voters.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $query->where('voters.exists_in_database', false);
            }
           
            if (!empty($polling) && is_numeric($polling)) {
                $query->where('voters.polling', $polling);
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
                $query->whereRaw('LOWER(voters.first_name) LIKE ?', ['%' . strtolower($firstName) . '%']);
            }
    
            if (!empty($secondName)) {
                $query->whereRaw('LOWER(voters.second_name) LIKE ?', ['%' . strtolower($secondName) . '%']);
            }
            
            if (!empty($last_name)) {
                $query->whereRaw('LOWER(voters.surname) LIKE ?', ['%' . strtolower($last_name) . '%']);
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
            
           

            if (!empty($sortBy)) {
                switch ($sortBy) {
                    case 'voter':
                        $query->orderBy('voters.voter', $sortOrder);
                        break;
                    case 'const':
                        $query->orderBy('voters.const', $sortOrder);
                        break;
                    case 'polling':
                        $query->orderBy('voters.polling', $sortOrder);
                        break;
                    case 'first_name':
                        $query->orderByRaw('LOWER(TRIM(voters.first_name)) ' . strtoupper($sortOrder));
                        break;
                    case 'last_name':
                        $query->orderByRaw('LOWER(TRIM(voters.surname)) ' . strtoupper($sortOrder)); 
                        break;
                    default:
                    $query->orderBy('surveys.id', 'desc');
                        break;
                }
            } else {
                // Default sorting
                $query->orderBy('surveys.id', 'desc');
            }

            $voters = $query->paginate($perPage);
            return $voters;
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
            $phoneNumber = $request->input('phone_number');
            $sortBy = $request->input('sort_by'); // voter, const, or polling
            $sortOrder = $request->input('sort_order', 'asc'); // asc or desc
    
    
        // $query = Voter::with('user')
        //     ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date','surveys.user_id','surveys.voting_for')
        //     ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
        //     ->join('surveys', 'voters.id', '=', 'surveys.voter_id')
        //     ->whereExists(function ($query) {
        //         $query->select('id')
        //             ->from('surveys')
        //             ->whereColumn('surveys.voter_id', 'voters.id');
        //     })
        //     ->whereNull('surveys.voting_for')
        //     ->where('surveys.voting_decision','undecided')
        //     ->orderBy('surveys.id', 'desc');


        $query = Survey::with('user')->join('voters', 'surveys.voter_id', '=', 'voters.id')
            ->join(DB::raw("(
                SELECT DISTINCT ON (voter_id) id
                FROM surveys 
                ORDER BY voter_id, created_at DESC
            ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
            ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 
            'surveys.created_at as survey_date', 'surveys.user_id', 'surveys.voting_for'
            ,'surveys.cell_phone','surveys.cell_phone_code')
            ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
            ->whereNull('voting_for')
            ->where('voting_decision', 'undecided');
           
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
            if (!empty($phoneNumber) && is_numeric($phoneNumber)) {
                $query->where('surveys.cell_phone_code', 'like', '%' . $phoneNumber . '%')
                ->orWhere('surveys.cell_phone', 'like', '%' . $phoneNumber . '%');
          
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
                $query->whereRaw('LOWER(voters.first_name) LIKE ?', ['%' . strtolower($firstName) . '%']);
            }
    
            if (!empty($secondName)) {
                $query->whereRaw('LOWER(voters.second_name) LIKE ?', ['%' . strtolower($secondName) . '%']);
            }
            
            if (!empty($last_name)) {
                $query->whereRaw('LOWER(voters.surname) LIKE ?', ['%' . strtolower($last_name) . '%']);
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
            

            if (!empty($sortBy)) {
                switch ($sortBy) {
                    case 'voter':
                        $query->orderBy('voters.voter', $sortOrder);
                        break;
                    case 'const':
                        $query->orderBy('voters.const', $sortOrder);
                        break;
                    case 'polling':
                        $query->orderBy('voters.polling', $sortOrder);
                        break;
                    case 'first_name':
                        $query->orderByRaw('LOWER(voters.first_name) ' . strtoupper($sortOrder));
                        break;
                    case 'last_name':
                        $query->orderByRaw('LOWER(TRIM(voters.surname)) ' . strtoupper($sortOrder)); 
                        break;
                    default:
                    $query->orderBy('surveys.id', 'desc');
                        break;
                }
            } else {
                // Default sorting
                $query->orderBy('surveys.id', 'desc');
            }

            // Get paginated results with all surveys
            $voters = $query->paginate($perPage);
           
            
            return $voters;
    }

    private function getFirstTimeVoters($request,$surveyor, $perPage)
    {
        $sortBy = $request->input('sort_by'); // voter, const, or polling
        $sortOrder = $request->input('sort_order', 'asc'); // asc or desc


        // $query = Voter::query()
        // ->select('voters.*', 'constituencies.name as constituency_name')
        // ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
        // ->whereRaw("EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob::date)) <= 25");
        $query = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
        ->join(DB::raw("(
            SELECT DISTINCT ON (voter_id) id
            FROM surveys
            ORDER BY voter_id, created_at DESC
        ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
        ->select('voters.*', 'constituencies.name as constituency_name')
        ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
        ->whereRaw("EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob::date)) <= 25");
  
        $searchableFields = [
            'first_name' => 'First Name',
            'second_name' => 'Second Name',
            'surname' => 'Surname', 
            'address' => 'Address',
            'voter' => 'Voter ID',
            'const' => 'Constituency ID',
            'constituency_name' => 'Constituency Name',
            'polling' => 'Polling Station'
        ];  

        // Get search parameters
        $const = $request->input('const');
        $surname = $request->input('surname');
        $firstName = $request->input('first_name');
        $secondName = $request->input('second_name');
        $address = $request->input('address');
        $voterId = $request->input('voter_id'); 
        $constituencyName = $request->input('constituency_name');
        $constituencyId = $request->input('constituency_id'); 
        $underAge25 = $request->input('under_age_25');
        $polling = $request->input('polling');
        $houseNumber = $request->input('house_number');
        $pobse = $request->input('pobse');
        $pobis = $request->input('pobis');
        $pobcn = $request->input('pobcn');
        $existsInDatabase = $request->input('exists_in_database');


        // Apply filters

        // Apply exists_in_database filter
        if ($existsInDatabase === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false);
        }

        if (!empty($polling) && is_numeric($polling)) {
            $query->where('voters.polling', $polling);
        }

        if ($underAge25 === 'yes') {
            $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
        }
        if (!empty($const)) {
            $query->where('voters.const', $const);
        }
        if (!empty($surname)) {
            $query->whereRaw('LOWER(voters.surname) LIKE ?', ['%' . strtolower($surname) . '%']);
        }

        if (!empty($firstName)) {
            $query->whereRaw('LOWER(voters.first_name) LIKE ?', ['%' . strtolower($firstName) . '%']);
        }

        if (!empty($secondName)) {
            $query->whereRaw('LOWER(voters.second_name) LIKE ?', ['%' . strtolower($secondName) . '%']);
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
            $query->where('voters.voter', $voterId);
        }

        if (!empty($constituencyName)) {
            $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
        }

        if (!empty($constituencyId)) {
            $query->where('voters.const', $constituencyId);
        }


        if (!empty($sortBy)) {
            switch ($sortBy) {
                case 'voter':
                    $query->orderBy('voters.voter', $sortOrder);
                    break;
                case 'const':
                    $query->orderBy('voters.const', $sortOrder);
                    break;
                case 'polling':
                    $query->orderBy('voters.polling', $sortOrder);
                    break;
                case 'first_name':
                    $query->orderByRaw('LOWER(voters.first_name) ' . strtoupper($sortOrder));
                    break;
                case 'last_name':
                    $query->orderByRaw('LOWER(TRIM(voters.surname)) ' . strtoupper($sortOrder)); 
                    break;
                default:
                     $query->orderBy('surveys.id', 'desc');
                    break;
            }
        } else {
            // Default sorting
            $query->orderBy('surveys.id', 'desc');
        }

        // Get paginated results
        $voters = $query->paginate($request->get('per_page', 20)); // Default to 20 items per page if not specified
        
        return $voters;
        
    }

    public function getQuestionStats()
    {
        try {
            // Get all questions with their answers
            $questions = Question::with('answers')->get();
            $stats = [];
     
            foreach ($questions as $question) {
                // Get count of each answer
                $answerCounts = SurveyAnswer::where('question_id', $question->id)
                    ->select('answer_id', DB::raw('count(*) as total'))
                    ->groupBy('answer_id')
                    ->get()
                    ->keyBy('answer_id');
               
                // Get total responses for this question
                $totalResponses = SurveyAnswer::where('question_id', $question->id)->count();
               
                // Calculate stats for each answer
                $answerStats = []; 
                foreach ($question->answers as $answer) {
                    $count = isset($answerCounts[$answer->id]) ? $answerCounts[$answer->id]->total : 0;
                  
                    // Calculate percentage based on total responses
                    $percentage = $totalResponses > 0 ? round(($count / $totalResponses) * 100, 2) : 0;
    
                    // Get gender breakdown for this answer
                    $maleCount = SurveyAnswer::where('question_id', $question->id)
                        ->where('answer_id', $answer->id)
                        ->join('surveys', 'survey_answers.survey_id', '=', 'surveys.id')
                        ->where('surveys.sex', 'Male')
                        ->count();
                        
                    $femaleCount = SurveyAnswer::where('question_id', $question->id)
                        ->where('answer_id', $answer->id)
                        ->join('surveys', 'survey_answers.survey_id', '=', 'surveys.id')
                        ->where('surveys.sex', 'Female')
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

 
}
 