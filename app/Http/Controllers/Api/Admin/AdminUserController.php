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
use App\Models\DailySurveyTrack;
use App\Models\Party;
use DB;
class AdminUserController extends Controller
{
    
    

     

    public function index(Request $request)
    {
        $query = User::where('role_id', 1)
                    ->withCount('surveys'); // Add surveys count

        
        if ($request->has('search')) {
            $searchTerm = trim(strtolower($request->search));
            $query->where(function($q) use ($searchTerm) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . $searchTerm . '%']);
            });
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
            

        $users = $query->orderBy('id', 'desc')->paginate($request->get('per_page', 20));

        
       
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
            'role_id' => 1,
            'email' => $request->email,
            'phone' => $request->phone,
            'is_active' => $request->is_active ?? true,
            'password' => Hash::make($request->password),
            'address' => $request->address ?? null,
            'image' => $imagePath,
        ]);

        return response()->json([ 
            'success' => true,
            'message' => 'Admin created successfully',
            'user' => $user
        ], 200);
    }

    public function show($id)
    {
        $user = User::find($id);
        
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
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Admin not found'
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
            'message' => 'Admin updated successfully',
            'user' => $user
        ]);
    }

    public function destroy($id)
    {
        $user = User::find($id);

        if(!$user){
            return response()->json([
                'success' => false,
                'message' => 'Admin not found'
            ], 404);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Admin deleted successfully'
        ]);
    }

    
}
