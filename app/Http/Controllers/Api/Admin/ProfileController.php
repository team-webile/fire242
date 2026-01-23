<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Models\Constituency;
class ProfileController extends Controller
{
    /**
     * Get user profile by ID
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function getProfile()
    {
        try {
            $user = Auth::user();
            
            
            // Add image path if image exists
           
            return response()->json([
                'success' => true,
                'message' => 'User profile retrieved successfully',
                'data' => [
                    'data' => $user,
                    
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'error' => $e->getMessage()
            ], Response::HTTP_NOT_FOUND);
        }
    }

 // ... existing code ...

    /**
     * Update user profile
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function updateProfile(Request $request) 
    {
        try {
            $user = Auth::user(); 
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], Response::HTTP_NOT_FOUND);
            }
            
            try {
                // Check if email already exists for another user
                $existingUser = User::where('email', $request->email)
                    ->where('id', '!=', $user->id)
                    ->first();
                    
                if ($existingUser) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Email already exists'
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                // Basic validation for profile fields
                $request->validate([
                    'name' => 'required|string|max:255',
                    'email' => 'required|email',
                    'phone' => 'nullable|string|max:20', 
                    'address' => 'nullable|string|max:255',
                    'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                    'password' => 'nullable|min:6' 
                ]);
            } catch (\Illuminate\Validation\ValidationException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
    
            $input = $request->only(['name', 'email', 'phone', 'address']);
    
            // Handle image upload if provided
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($user->image) {
                    Storage::disk('public')->delete('users/' . $user->image);
                }
    
                // Store new image
                $imagePath = $request->file('image')->store('users', 'public');
                $input['image'] = $imagePath;
            }  
    
            // Handle password update if provided
            if ($request->filled('password')) {
                $input['password'] = Hash::make($request->password);
            }
    
            $user->update($input);
    
            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $user
            ], Response::HTTP_OK);
    
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }
 

}
