<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
       if (!auth()->check() || !auth()->user()->is_admin) {
           return response()->json(['message' => 'Unauthorized. Admin access required 123.'], 403);
       }
        return $next($request);
    }    
} 