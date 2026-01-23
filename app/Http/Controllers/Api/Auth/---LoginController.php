<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Constituency;
use App\Models\Page;
use App\Models\ManagerSystemSetting;
use App\Models\ManagerPage;
use Spatie\Activitylog\Facades\LogActivity;
use GeoIp2\Database\Reader; 

class LoginController extends Controller
{
     
    private function getGeoInfo($ip)
    {
        try {
            $reader = new Reader(storage_path('geoip/GeoLite2-City.mmdb'));
            $record = $reader->city($ip);
            return json_encode($record);
        } catch (\Exception $e) {
            \Log::error('GeoIP lookup failed: ' . $e->getMessage());
            return ['error' => 'GeoIP lookup failed'];
        }   
    }

    public function login(Request $request)
    {
        // Log the login attempt
        \Log::info('login called with login attempt');
        try {
            $credentials = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            if (!$token = auth()->attempt($credentials)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email or password'
                ], 401);
            }

            // Check if user is inactive
            $user = auth()->user();
            
            if (!$user->is_active) {
                auth()->logout();
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been blocked by admin'
                ], 403);
            }

            // Get geo info
            $geoData = $this->getGeoInfo($request->ip());

            // Log successful login
            activity('auth')
                ->causedBy($user)
                ->withProperties([
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'session_id' => session()->getId(),
                    'login_time' => now(),
                    'geo' => $geoData
                ])
                ->log('User Logged In');

            return $this->respondWithToken($token);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while logging in',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function logout()
    {
        $user = auth()->user();
        
        // Get geo info
        $geoData = $this->getGeoInfo(request()->ip());
        
        activity('auth')
            ->causedBy($user)
            ->withProperties([
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'session_id' => session()->getId(),
                'logout_time' => now(),
                'geo' => $geoData
            ])
            ->log('User Logged Out');
            
        auth()->logout();
        return response()->json(['message' => 'Successfully logged out']);
    }

    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    protected function respondWithToken($token)
    {
        $user = auth()->user();
        $page = [];
        if(strtolower($user->role->name) === 'user'){
            $page = Page::all();
        }
        $settings = [];
        if(strtolower($user->role->name) === 'manager'){
            $page = ManagerPage::all();
            $constituencyIds = $user->constituency_id ? explode(',', $user->constituency_id) : [];
            $user->is_coordinator = count($constituencyIds) == 1 ? 0 : 1;  
            $settings = ManagerSystemSetting::with('constituency')->where('manager_id', $user->id)->first(); 

        }

        $response = [
            'user' => $user,
            'role' => strtolower($user->role->name),
            'access_token' => $token,
            'token_type' => 'bearer',
            'pages' => $page,
            'settings' => $settings,
            // 'expires_in' => null
        ];

        if (strtolower($user->role->name) === 'user' && !empty($user->constituency_id) && $user->constituency_id !== null) {
            $constituency_ids = explode(',', $user->constituency_id);
            $constituencies = Constituency::whereIn('id', $constituency_ids)
                ->pluck('name')
                ->toArray();
            $response['constituencies'] = $constituencies;
        }

        return response()->json($response); 
    }
} 
