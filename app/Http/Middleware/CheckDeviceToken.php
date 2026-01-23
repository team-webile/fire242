<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckDeviceToken
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth('api')->user();
        
        if (!$user) {
            return response()->json(['success' => false,'message' => 'Unauthenticated'], 401);
        }
        
        // Get token from request header
        $token = $request->bearerToken(); 
        
        // If device token doesn't match, logout the user
        if ($user->last_token_id !== $token) {
            auth('api')->logout();
            return response()->json(['success' => false,'message' => 'You have been logged out due to login on another device'], 401);
        }
        
        return $next($request); 
    }
}