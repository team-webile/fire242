<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\Constituency;
use App\Models\Survey;
use App\Models\ManagerSystemSetting;
use DB;
class ManagerController extends Controller
{
    
    

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

        $searchTerm = trim(strtolower($request->search));
        $constituencies = Constituency::whereRaw('LOWER(name) LIKE ?', ['%' . $searchTerm . '%'])
                          ->orderBy('id', 'desc')
                          ->limit(10)
                          ->get();

        return response()->json([
            'message' => 'Constituencies retrieved successfully',
            'data' => $constituencies
        ]);


    }

    public function index(Request $request)
    {
        $query = User::where('role_id', 3)
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
            
        if ($request->has('time_active')) {
            $query->where('time_active',$request->time_active);
        }
        // Filter users based on is_coordinator if requested
        if ($request->has('is_coordinator')) {
            $isCoordinator = (int) $request->is_coordinator; // Ensure it's an integer (0 or 1)
            $query->whereRaw("
                CASE 
                    WHEN LENGTH(constituency_id) - LENGTH(REPLACE(constituency_id, ',', '')) = 0 THEN 0
                    ELSE 1
                END = ?", [$isCoordinator]);
        }
    
        $users = $query->orderBy('id', 'desc')->paginate($request->get('per_page', 20));
    
        $users->getCollection()->transform(function($user) {
            $constituencyIds = $user->constituency_id ? explode(',', $user->constituency_id) : [];
            $constituencies = empty($constituencyIds) ? null : 
                Constituency::whereIn('id', $constituencyIds)
                    ->select('id', 'name', 'is_active')
                    ->get();
            
            $user->constituencies = $constituencies;
    
            // Get survey count for this user
            $surveyCount = Survey::where('user_id', $user->id)->count();
            $user->survey_count = $surveyCount;
    
            // Determine coordinator status
            $user->is_coordinator = count($constituencyIds) == 1 ? 0 : 1;
    
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
            'time_active' => 'required',
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
            'role_id' => 3,
            'email' => $request->email,
            'phone' => $request->phone,
            'is_active' => $request->is_active ?? true,
            'time_active' => $request->time_active,
            'constituency_id' => $request->constituency_id,
            'password' => Hash::make($request->password),
            'address' => $request->address ?? null,
            'image' => $imagePath,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Manager created successfully',
            'user' => $user
        ], 200);
    }

    public function show($id)
    {
        $user = User::find($id);
        
        if(!$user){
            return response()->json([
                'success' => false,
                'message' => 'Manager not found'
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
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Manager not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'email' => 'string|email|max:255|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'constituency_id' => 'string',
            'time_active' => 'required',
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
            'message' => 'Manager updated successfully',
            'user' => $user
        ]);
    }

    public function destroy($id)
    {
        $user = User::find($id);

        if(!$user){
            return response()->json([
                'success' => false,
                'message' => 'Manager not found'
            ], 404);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Manager deleted successfully'
        ]);
    }

    public function getUserSurveys(Request $request, $id){

  
        if (!auth()->check() || auth()->user()->role->name !== 'Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }

        $query = Survey::with('voter');

        // Search fields based on Survey model's fillable columns
        // Apply search filters
        $searchableFields = [
            'surname',
            'first_name',
            'second_name',
            'voter_id',
            'sex',
            'start_date',
            'end_date'
        ];

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
        $surveys = $query->where('user_id', $id)
                        ->orderBy('id', 'desc')
                        ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $surveys,
            'searchable_fields' => $searchableFields
        ]);
    }


    public function getSettings($id)
    {
        $settings = ManagerSystemSetting::with('constituency')->where('manager_id', $id)->get();
        return response()->json([
            'success' => true,
            'data' => $settings
        ]);  
    } 

    
}
