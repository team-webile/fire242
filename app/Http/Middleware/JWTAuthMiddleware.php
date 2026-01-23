<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

class JWTAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            // Check if token exists
            $token = JWTAuth::getToken();
            dd($token);
            if (!$token) {
                return response()->json(['success' => false,'message' => 'Token not provided'], 401);
            }

            // Attempt to authenticate user
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json(['success' => false,'message' => 'User not found'], 401);
            }
            
            // Check if the current token matches the one stored in the database
            // This ensures only one device can be logged in at a time
            $payload = JWTAuth::getPayload($token);
            if ($user->last_token_id !== $payload->get('jti')) {
                return response()->json(['success' => false,'message' => 'You have been logged out due to login on another device'], 401);
            }
            
        } catch (TokenExpiredException $e) {
            return response()->json(['success' => false,'message' => 'Token expired'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['message' => 'Token invalid'], 401);
        } catch (JWTException $e) {
            return response()->json(['message' => 'Token not found'], 401);
        }

        return $next($request);
    }
}