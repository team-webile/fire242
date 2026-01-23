<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Survey;
use App\Models\UnregisteredVoter;
use App\Models\Voter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
class DashboardStatsController extends Controller
{

    public function index()
    {
        $fnm = Survey::where(function($query) {
            $query->where('voting_for', 'FNM')
                  ->orWhere('voting_for', 'Free National Movement');
        })->count();

        $plp = Survey::where(function($query) {
            $query->where('voting_for', 'PLP')
                  ->orWhere('voting_for', 'Progressive Liberal Party');
        })->count();

      
        $other = Survey::where(function($query) {
            $query->whereNotIn('voting_for', ['FNM', 'Free National Movement', 'PLP', 'Progressive Liberal Party'])
                  ->whereNotNull('voting_for');
        })->count();
 
       // Get IDs of voters who have taken the survey
       $surveyed_voter_ids = Survey::pluck('voter_id');

      
    
       // Get voters who haven't taken the survey
       $data = Voter::whereNotIn('voter', $surveyed_voter_ids)->get();
        
       $unknown = $data->count();

   
        $first_time_voters = Voter::whereRaw("EXTRACT(YEAR FROM AGE(CURRENT_DATE, dob::date)) <= 25")->count();        

        $fnm_unregistered = UnregisteredVoter::where(function($query) {
            $query->where('party', 'FNM')
                  ->orWhere('party', 'Free National Movement');
        })->count();

        $unregistered = UnregisteredVoter::count();

        $total_voters = Voter::count();

        $data = [
            'fnm' => $fnm,
            'plp' => $plp, 
            'coi' => 0,
            'other' => $other,
            'unknown' => $unknown,
            'first_time_voters' => $first_time_voters,
            'unregistered_total' => $unregistered,
            'unregistered_fnm' => $fnm_unregistered,
            'total_registered_voters' => $total_voters
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


        // Apply filters

        
        // Get voters who haven't taken the survey
        $query = Voter::select('voters.*', 'constituencies.name as constituency_name')
                    ->join('constituencies', 'voters.const', '=', 'constituencies.id')
                    ->whereNotIn('voters.voter', $surveyed_voter_ids);

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


        $query = Voter::select('voters.*', 'constituencies.name as constituency_name')
            ->join('constituencies', 'voters.const', '=', 'constituencies.id')
            ->whereRaw("EXTRACT(YEAR FROM AGE(CURRENT_DATE, dob::date)) <= 25");
 
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

    public function getListByType(Request $request, $type)
    {  
        switch($type) {
            case 'fnm':
                return $this->getFnmList($request);
            case 'plp':
                return $this->getPlpList($request);
            case 'coi':
                return $this->getCoiList($request);
            case 'other':
                return $this->getOtherList($request);
            case 'unknown':
                return $this->getUnknownList($request);
            case 'first_time':
                return $this->getFirstTimeVotersList($request);
            case 'unregistered_total':
                return $this->getUnregisteredList($request);
            case 'unregistered_fnm':
                return $this->getUnregisteredFnmList($request);
            case 'total_registered': 
                return $this->getTotalRegisteredList($request);
            default:
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid list type requested'
                ], 400);
        } 
    }

    public function getCoiList(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $data = Survey::where('voting_for', 'COI')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function getUnregisteredFnmList(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $data = UnregisteredVoter::with('voter')
            ->where(function($query) {
                $query->where('party', 'FNM')
                    ->orWhere('party', 'Free National Movement');
            })
            ->where(function($query) use ($request) {
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

                if (isset($request->gender) && !empty($request->gender)) {
                    $query->whereRaw('LOWER(gender) = ?', [strtolower($request->gender)]);
                }

                if (isset($request->date_from) && !empty($request->date_from) && isset($request->date_to) && !empty($request->date_to)) {
                    $query->whereBetween('date_of_birth', [$request->date_from, $request->date_to]);
                }
            })
            ->paginate($perPage);
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
            'data' => $data,
            'searcable_fileds' => $searchParams
        ]);
    }

    public function getTotalRegisteredList(Request $request)
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
        
        $data = Voter::paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $data,
            'searchable_fields' => $searchableFields,
        ]);
    }

 
}
 