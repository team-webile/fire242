<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Voter;

class MapController extends Controller
{
    public function getUsersInBounds(Request $request)
    {
        // Get users within the bounds
        $users = Voter::select(['id', 'first_name', 'second_name', 'surname', 'address', 'house_number', 'pobse', 'pobis', 'pobcn', 'latitude', 'longitude'])
            ->where('address', '!=', '')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereRaw('ROUND(CAST(latitude AS numeric), 4) >= ?', [round($request->bounds['sw']['lat'], 4)])
            ->whereRaw('ROUND(CAST(latitude AS numeric), 4) <= ?', [round($request->bounds['ne']['lat'], 4)])
            ->whereRaw('ROUND(CAST(longitude AS numeric), 4) >= ?', [round($request->bounds['sw']['lng'], 4)])
            ->whereRaw('ROUND(CAST(longitude AS numeric), 4) <= ?', [round($request->bounds['ne']['lng'], 4)])
            //->limit(10)
            ->get();
        //dd(count($users));
        foreach ($users as $user) {
            $user->full_address = $user->house_number . ' ' . $user->address . ' ' . $user->pobse . ' ' . $user->pobis . ' ' . $user->pobcn;
        }
    
        return response()->json($users);
    }
}
