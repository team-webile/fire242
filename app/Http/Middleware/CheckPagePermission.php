<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Page;
use App\Models\RolePermission;

class CheckPagePermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        

        if (!$user) {
            return redirect('login');
        }

        $currentUrl = $request->path();
        
        $page = Page::where('url', $currentUrl)->first();
       
        if (!$page) {
            return response()->json(['message' => 'Page not found'], 404);
        }

        $hasPermission = RolePermission::where('role_id', $user->role_id)
            ->where('page_id', $page->id)
            ->exists();

        if (!$hasPermission) {
            return response()->json(['message' => 'You are not allowed to access this page'], 403);
        }

        return $next($request);
    }
}