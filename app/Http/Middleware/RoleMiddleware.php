<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $role
     * @return mixed
     */
    public function handle($request, Closure $next, string $role)
    {
        if (!Auth::check()) {
            return redirect('login'); // Redirect to login if not authenticated
        }

        $user = Auth::user();  
  
        // Check if the user's role matches the required role
          
            if ($user->role->name !== $role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized action'
                ], 403);
            }

        return $next($request);
    } 
}
