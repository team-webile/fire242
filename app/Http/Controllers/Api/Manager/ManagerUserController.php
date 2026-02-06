<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\Storage;
use App\Models\Constituency;
use App\Models\Survey; 
use DB;
use App\Models\DailySurveyTrack;
use App\Models\Voter;
use App\Models\Party;
use Illuminate\Support\Facades\Auth;
use App\Models\Question;
use App\Models\Answer;
use App\Models\SurveyAnswer;
use App\Models\ManagerPage;
class ManagerUserController extends Controller

{
    
    public function livesearch(Request $request)
    {
        $query = Party::query();

        if ($request->has('party') && !empty($request->party)) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->party) . '%']);
        }

        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    public function fatch_all_manager_permissions(Request $request)
    {
        $permissions = ManagerPage::all(); 
        return response()->json(['success' => true, 'data' => $permissions]);
    }

    public function getConstituencies(Request $request)
    {

        //dd($request->all());
        $validator = Validator::make($request->all(), [
            'search' => 'required|string|min:2'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $constituencies = auth()->user()->constituency_id;
        $arrayConstituencyIds = explode(',', $constituencies);

        $searchTerm = trim(strtolower($request->search));
        $constituencies = Constituency::whereIn('id', $arrayConstituencyIds)
        ->whereRaw('LOWER(name) LIKE ?', ['%' . $searchTerm . '%'])
        ->orderBy('id', 'desc')
        ->limit(10)
        ->get();


        return response()->json([
            'message' => 'Constituencies retrieved successfully',
            'data' => $constituencies
        ]);


    }


    public function get_all_constituencies(Request $request)
    {
 
         
        $constituencies = auth()->user()->constituency_id;
        $arrayConstituencyIds = explode(',', $constituencies);
 
        $constituencies = Constituency::whereIn('id', $arrayConstituencyIds)
        ->orderBy('id', 'desc')
        ->get();


        return response()->json([
            'message' => 'Constituencies retrieved successfully',
            'data' => $constituencies
        ]);


    }


    public function index(Request $request)
    {
        $query = User::where('manager_id', auth()->user()->id)->where('role_id', 2)
                    ->withCount('surveys'); // Add surveys count

        if ($request->has('search')) {
            $searchTerm = trim(strtolower($request->search));
            $query->where(function($q) use ($searchTerm) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . $searchTerm . '%']);
            });
        }

        if ($request->has('constituency_name') && !empty($request->constituency_name)) {
            $searchTerm = strtolower($request->constituency_name);
            $constituencies = Constituency::whereRaw('LOWER(name) LIKE ?', ['%' . $searchTerm . '%'])
                ->pluck('id')
                ->toArray();

            if (!empty($constituencies)) {
                $query->where(function($q) use ($constituencies) {
                    foreach($constituencies as $constituencyId) {
                        $q->orWhereRaw("constituency_id ~ ?", ["(^|,)" . $constituencyId . "($|,)"]);
                    }
                });
            }
        }

        if ($request->has('address')) {
            $address = strtolower($request->address);
            $query->whereRaw('LOWER(address) LIKE ?', ['%' . $address . '%']);
        }

        if ($request->has('email')) {
            $email = strtolower($request->email);
            $query->whereRaw('LOWER(email) LIKE ?', ['%' . $email . '%']);
        } 


        if ($request->has('status')) {
            $status = strtolower($request->status);
            if ($status === 'active') {
                $query->where('is_active', 1);
            } else if ($status === 'inactive') {
                $query->where('is_active', 0);
            }
        } 

             
       

        $users = $query->orderBy('id', 'desc')->paginate(10);

        $users->getCollection()->transform(function($user) {
            $constituencyIds = $user->constituency_id ? explode(',', $user->constituency_id) : [];
            $constituencies = empty($constituencyIds) ? null : 
                Constituency::whereIn('id', $constituencyIds)
                    ->select('id', 'name', 'is_active')
                    ->get();
            
            $user->constituencies = $constituencies;

            // Get survey count for this user
            $surveyCount = Survey::where('user_id', $user->id)->count();
            $dailySurveyCount = DailySurveyTrack::where('user_id', $user->id)->where('date', \Carbon\Carbon::today())->first();
            $user->survey_count = $surveyCount;
            $user->daily_survey_count = $dailySurveyCount;

            return $user;
        });

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'is_super_user' => 'boolean', 
            'constituency_id' => 'required|string',
            'address' => 'nullable|string|max:255', 
            'password' => 'required|string|min:6',
            'image' => 'nullable|image|mimes:jpeg,png,jpg', 
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('users', 'public');
        }

        $user = User::create([
            'name' => $request->name,
            'role_id' => 2,
            'manager_id' => auth()->user()->id, 
            'email' => $request->email,
            'phone' => $request->phone,
            'is_active' => $request->is_active ?? true,
            'is_super_user' => $request->is_super_user, 
            'constituency_id' => $request->constituency_id,
            'password' => Hash::make($request->password),
            'address' => $request->address ?? null,
            'image' => $imagePath,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'user' => $user
        ], 200);
    }

    public function show($id)
    {
        $user = User::where('manager_id', auth()->user()->id)->find($id);
        

        if(!$user){
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
        
        // Get user's assigned constituencies
        $constituencyIds = $user->constituency_id ? explode(',', $user->constituency_id) : [];
        $userConstituencies = empty($constituencyIds) ? null : 
            Constituency::whereIn('id', $constituencyIds)
                ->select('id', 'name', 'is_active')
                ->get();
        
        // Get all constituencies
        $allConstituencies = Constituency::select('id', 'name', 'is_active')->get();
        
        $user->constituencies = $userConstituencies;

        return response()->json([
            'success' => true,
            'user' => $user,
            'all_constituencies' => $allConstituencies
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = User::where('manager_id', auth()->user()->id)->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'email' => 'string|email|max:255|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'is_super_user' => 'boolean', 
            'constituency_id' => 'string',
            'password' => 'nullable|string|min:6',
            'address' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = $request->except(['password', 'image']);
        
        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        if ($request->hasFile('image')) {
            if ($user->image) {
                Storage::disk('public')->delete($user->image);
            }
            $updateData['image'] = $request->file('image')->store('users', 'public');
        }

        $user->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'user' => $user
        ]);
    }

    public function destroy($id)
    {
        $user = User::where('manager_id', auth()->user()->id)->find($id);

        if(!$user){
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Check if user has any surveys before deletion
        $surveys = Survey::where('user_id', $id)->get();
        if($surveys->count() > 0){
            return response()->json([
                'success' => true,
                'message' => 'Cannot delete user - they have existing surveys',
                
            ]); 
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    public function getUserSurveys(Request $request, $id){


        
        $query = Survey::with(['voter' => function($q) {
            $q->select('voters.*')
              ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
              ->addSelect('constituencies.name as constituency_name');
        }])->where('user_id', $id);

        // Search fields based on Survey model's fillable columns
        // Apply search filters
        $searchableFields = [
            'surname',
            'first_name',
            'second_name',
            'voter_id',
            'sex',
            'start_date',
            'end_date',
            'located',
            'voting_decision',
            'voting_for',
            'is_died',
            'died_date'
        ];

        if(isset($request->voting_for) && !empty($request->voting_for)){
            $get_party = Party::where('id', $request->voting_for)->first();
            $voting_for = $get_party->name;
            $query->where('surveys.voting_for', $voting_for);
        }

        if (!empty($request->voting_decision)) {
            $query->where('surveys.voting_decision', $request->voting_decision);
        }

        if (isset($request->is_died)) {
           
            $query->where('surveys.is_died', $request->is_died);
        } 
        
         
        
        if (!empty($request->located)) {
            $query->whereRaw('LOWER(located) = ?', [strtolower($request->located)]);
        }

        if (isset($request->start_date) && !empty($request->start_date)) {
            $query->where('created_at', '>=', $request->start_date . ' 00:00:00');
        }

        if (isset($request->end_date) && !empty($request->end_date)) {
            $query->where('created_at', '<=', $request->end_date . ' 23:59:59');
        }

        if ($request->filled('surname')) {
            $query->whereHas('voter', function($q) use ($request) {
                $q->whereRaw('LOWER(surname) LIKE ?', ['%' . strtolower($request->surname) . '%']);
            });
        }

        if ($request->filled('first_name')) {
            $query->whereHas('voter', function($q) use ($request) {
                $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->first_name) . '%']);
            });
        }

        if ($request->filled('second_name')) {
            $query->whereHas('voter', function($q) use ($request) {
                $q->whereRaw('LOWER(second_name) LIKE ?', ['%' . strtolower($request->second_name) . '%']);
            });
        }

        if ($request->filled('voter_id')) {
            $query->whereHas('voter', function($q) use ($request) {
                $q->where('voter', $request->voter_id);
            });
        }

        if ($request->filled('sex')) {
            $query->where('sex', $request->input('sex'));
        }

        // Get paginated results
        $surveys = $query->orderBy('id', 'desc')
                        ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $surveys,
            'searchable_fields' => $searchableFields
        ]);
    }


     


    public function getUserSurveyCount(Request $request, $id){
        $query = DailySurveyTrack::with('user')->where('user_id', $id);
        
        if ($request->filled('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('date', '<=', $request->end_date);
        }

        $dailySurveyCount = $query->orderBy('date', 'desc')->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $dailySurveyCount
        ]); 
    }

    public function statsList(Request $request)
    { 
        $surveyor = User::where('id', Auth::user()->id)->first();
        $type = $request->get('type', 'registered');
        $perPage = $request->get('per_page', 20); 

        $results = match($type) {
            'registered' => $this->getRegisteredVoters($request, $surveyor, $perPage),
            'fnm' => $this->getFNMVoters($request,$surveyor, $perPage),
            'coi' => $this->getCOIVoters($request,$surveyor, $perPage),
            'plp' => $this->getPLPVoters($request,$surveyor, $perPage), 
            // 'other_parties' => $this->getOtherPartyVoters($request,$surveyor, $perPage),
            'total_surveys' => $this->getTotalSurveys($request,$surveyor, $perPage),
            'total_unknown' => $this->getTotalUnknown($request,$surveyor, $perPage),
            'total_naver_vote' => $this->getTotalnaverVote($request,$surveyor, $perPage),
            'first_time_voters' => $this->getFirstTimeVoters($request,$surveyor, $perPage),
            'total_others' => $this->getOters($request,$surveyor, $perPage),
            'total_other_parties' => $this->getOtherPartyVoters($request,$surveyor, $perPage),
            
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



    private function getOters($request,$surveyor, $perPage) 
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

        $query = Survey::with('user')->join('voters', 'surveys.voter_id', '=', 'voters.id')
        ->join(DB::raw("(
            SELECT DISTINCT ON (voter_id) id
            FROM surveys
            ORDER BY voter_id, created_at DESC
        ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
        ->select('voters.*', 'constituencies.name as constituency_name', 
        'surveys.id as survey_id', 'surveys.created_at as survey_date', 
        'surveys.user_id', 'surveys.voting_for', 'surveys.cell_phone','surveys.cell_phone_code')
        ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
        ->where('surveys.voting_for', 'Other')
        ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
        ->orderBy('surveys.id', 'desc');
        
        
        

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
    //               ->orWhere('voting_for', 'Coalition of Independents');
    //     })
    //     ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
    //     ->orderBy('surveys.id', 'desc');
    $query = Survey::with('user')
        ->join('voters', 'surveys.voter_id', '=', 'voters.id')
        ->join(DB::raw("(
            SELECT DISTINCT ON (voter_id) id 
            FROM surveys
            ORDER BY voter_id, created_at DESC
        ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
        ->select('voters.*', 'constituencies.name as constituency_name',
         'surveys.id as survey_id', 'surveys.created_at as survey_date', 'surveys.user_id',
          'surveys.voting_for', 'surveys.cell_phone','surveys.cell_phone_code')
        ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
        ->where(function($query) {
            $query->where('surveys.voting_for', 'FNM')
                  ->orWhere('surveys.voting_for', 'Coalition of Independents');
        })
        ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
        ->orderBy('surveys.id', 'desc');

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
        $voters = $query->paginate($perPage);

        
        return $voters; 

    }

    private function getRegisteredVoters($request, $surveyor, $perPage)
    {
        $existsInDatabase = $request->input('exists_in_database');
        
        $query = Voter::query()
            ->whereIn('const', explode(',', $surveyor->constituency_id));
            
        // Apply exists_in_database filter
        if ($existsInDatabase === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
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
        $existsInDatabase = $request->input('exists_in_database');


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
    //     ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
    //     ->orderBy('surveys.id', 'desc');

    $query = Survey::with('user')->join('voters', 'surveys.voter_id', '=', 'voters.id')
        ->join(DB::raw("(
            SELECT DISTINCT ON (voter_id) id
            FROM surveys
            ORDER BY voter_id, created_at DESC
        ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
        ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id',
         'surveys.created_at as survey_date', 'surveys.user_id', 'surveys.voting_for', 
         'surveys.cell_phone','surveys.cell_phone_code')
        ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
        ->where(function($query) {
            $query->where('surveys.voting_for', 'FNM')
                  ->orWhere('surveys.voting_for', 'Free National Movement');
        })
        ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
        ->orderBy('surveys.id', 'desc');

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
        //     ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
        //     ->orderBy('surveys.id', 'desc');
        $query = Survey::with('user')->join('voters', 'surveys.voter_id', '=', 'voters.id')
            ->join(DB::raw("(
                SELECT DISTINCT ON (voter_id) id 
                FROM surveys
                ORDER BY voter_id, created_at DESC
            ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
            ->select('voters.*', 'constituencies.name as constituency_name', 
            'surveys.id as survey_id', 'surveys.created_at as survey_date',
             'surveys.user_id', 'surveys.voting_for', 'surveys.cell_phone','surveys.cell_phone_code')
            ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
            ->where(function($query) {
                $query->where('surveys.voting_for', 'PLP')
                      ->orWhere('surveys.voting_for', 'Progressive Liberal Party');
            })
            ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
            ->orderBy('surveys.id', 'desc');
            
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
    
    
        $query =   Survey::with('user')->join('voters', 'surveys.voter_id', '=', 'voters.id')
        ->join(DB::raw("(
            SELECT DISTINCT ON (voter_id) id
            FROM surveys
            ORDER BY voter_id, created_at DESC
        ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
        ->select('voters.*', 'constituencies.name as constituency_name', 
        'surveys.id as survey_id', 'surveys.created_at as survey_date',
         'surveys.user_id', 'surveys.voting_for', 'surveys.cell_phone','surveys.cell_phone_code')
        ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
        ->where(function($query) {
            $query->whereNotIn('voting_for', ['FNM', 'Free National Movement', 'PLP', 'Progressive Liberal Party', 'COI', 'Coalition of Independents', 'Other'])
                  ->whereNotNull('voting_for');
            
        }) 
        ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
        ->orderBy('surveys.id', 'desc');
        
        
         
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
            $voters = $query->paginate($perPage);
 
            return $voters; 

    }

    private function getTotalSurveys($request,$surveyor, $perPage)
    {
        $query = Survey::with('voter');
        

        // Search fields based on Survey model's fillable columns
        // Apply all search filters directly from URL parameters with case-insensitive search
        $existsInDatabase = $request->input('exists_in_database');
        if ($existsInDatabase === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
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

        $query->whereHas('voter', function($q) use ($surveyor) {
            $q->whereIn('voters.const', explode(',', $surveyor->constituency_id));
        });
 

        // Get paginated results
        $surveys = $query->orderBy('id', 'desc')->paginate($perPage);

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
        //     ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
        //     // ->where('surveys.user_id', Auth::id())
        //     ->orderBy('surveys.id', 'desc');

        $query =  Survey::with('user')->join('voters', 'surveys.voter_id', '=', 'voters.id')
        ->join(DB::raw("(
            SELECT DISTINCT ON (voter_id) id 
            FROM surveys
            ORDER BY voter_id, created_at DESC
        ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
        ->select('voters.*', 'constituencies.name as constituency_name',
         'surveys.id as survey_id', 'surveys.created_at as survey_date',
          'surveys.user_id', 'surveys.voting_for', 'surveys.cell_phone','surveys.cell_phone_code')
        ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
        ->where(function($query) {
            $query->whereNull('surveys.voting_for')->where('surveys.voting_decision','undecided');
        })
        ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
        ->orderBy('surveys.id', 'desc');
            
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
        //     ->where('surveys.voting_decision','no')
        //     ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
        
        //     ->orderBy('surveys.id', 'desc');
     
        $query = Survey::with('user')->join('voters', 'surveys.voter_id', '=', 'voters.id')
            ->join(DB::raw("(
                SELECT DISTINCT ON (voter_id) id 
                FROM surveys
                ORDER BY voter_id, created_at DESC
            ) AS latest_surveys"), 'surveys.id', '=', 'latest_surveys.id')
            ->select('voters.*', 'constituencies.name as constituency_name', 
            'surveys.id as survey_id', 'surveys.created_at as survey_date',
             'surveys.user_id', 'surveys.voting_for', 'surveys.cell_phone','surveys.cell_phone_code')
            ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
            ->where(function($query) {
                $query->whereNull('surveys.voting_for')->where('surveys.voting_decision','no');
            })
            ->whereIn('voters.const', explode(',', $surveyor->constituency_id))
            ->orderBy('surveys.id', 'desc');
     


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
    public function getQuestionStats()
    {
        try {
            // Get manager's constituency IDs
            $managerConstituencyIds = explode(',', Auth::user()->constituency_id);
            $managerConstituencyIds = array_map('trim', $managerConstituencyIds);
    
            // Get all questions with their answers
            $questions = Question::with('answers')->get();
            $stats = [];
    
            foreach ($questions as $question) {
                // Get count of each answer for surveys in manager's constituencies
                $answerCounts = SurveyAnswer::where('question_id', $question->id)
                    ->whereExists(function ($query) use ($managerConstituencyIds) {
                        $query->select(DB::raw(1))
                            ->from('surveys')
                            ->join('voters', 'surveys.voter_id', '=', 'voters.id')
                            ->whereColumn('surveys.id', 'survey_answers.survey_id')
                            ->whereIn('voters.const', $managerConstituencyIds);
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
                    $percentage = $totalResponses > 0 ? round(($count / $totalResponses) * 100, 2) : 0;

                    // Get gender breakdown for this answer
                    $maleCount = SurveyAnswer::where('question_id', $question->id)
                        ->where('answer_id', $answer->id)
                        ->whereExists(function ($query) use ($managerConstituencyIds) {
                            $query->select(DB::raw(1))
                                ->from('surveys')
                                ->join('voters', 'surveys.voter_id', '=', 'voters.id')
                                ->whereColumn('surveys.id', 'survey_answers.survey_id')
                                ->whereIn('voters.const', $managerConstituencyIds)
                                ->where('surveys.sex', 'Male');
                        })
                        ->count();

                    $femaleCount = SurveyAnswer::where('question_id', $question->id)
                        ->where('answer_id', $answer->id)
                        ->whereExists(function ($query) use ($managerConstituencyIds) {
                            $query->select(DB::raw(1))
                                ->from('surveys')
                                ->join('voters', 'surveys.voter_id', '=', 'voters.id')
                                ->whereColumn('surveys.id', 'survey_answers.survey_id')
                                ->whereIn('voters.const', $managerConstituencyIds)
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
    
                // Get user-specific breakdowns
                $userBreakdowns = [];
                
                // Get all users who have conducted surveys in these constituencies
                $surveyUsers = Survey::join('voters', 'surveys.voter_id', '=', 'voters.id')
                    ->whereIn('voters.const', $managerConstituencyIds)
                    ->select('surveys.user_id')
                    ->distinct()
                    ->get()
                    ->pluck('user_id')
                    ->toArray();
    
                foreach ($surveyUsers as $userId) {
                    $userAnswerCounts = SurveyAnswer::where('question_id', $question->id)
                        ->whereExists(function ($query) use ($userId, $managerConstituencyIds) {
                            $query->select(DB::raw(1))
                                ->from('surveys')
                                ->join('voters', 'surveys.voter_id', '=', 'voters.id')
                                ->whereColumn('surveys.id', 'survey_answers.survey_id')
                                ->where('surveys.user_id', $userId)
                                ->whereIn('voters.const', $managerConstituencyIds);
                        })
                        ->select('answer_id', DB::raw('count(*) as total'))
                        ->groupBy('answer_id')
                        ->get()
                        ->keyBy('answer_id');
    
                    $userTotalResponses = $userAnswerCounts->sum('total');
                    
                    $userAnswerStats = [];
                    foreach ($question->answers as $answer) {
                        $userCount = isset($userAnswerCounts[$answer->id]) ? $userAnswerCounts[$answer->id]->total : 0;
                        $userPercentage = $userTotalResponses > 0 ? round(($userCount / $userTotalResponses) * 100, 2) : 0;
    
                        $userAnswerStats[] = [
                            'answer' => $answer->answer,
                            'count' => $userCount,
                            'percentage' => $userPercentage
                        ]; 
                    }
    
                    // Get user details
                    $user = User::find($userId);
                    if ($user) {
                        $userBreakdowns[] = [
                            'user_id' => $userId,
                            'user_name' => $user->name,
                            'total_responses' => $userTotalResponses,
                            'answers' => $userAnswerStats
                        ];
                    }
                }
    
                $stats[] = [
                    'question' => $question->question,
                    'total_responses' => $totalResponses,
                    'overall_stats' => $answerStats,
                    'user_breakdowns' => $userBreakdowns
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
