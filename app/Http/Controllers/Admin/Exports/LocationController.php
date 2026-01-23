<?php

namespace App\Http\Controllers\Admin\Exports;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\LocationsExport;
 

class LocationController extends Controller
{
    public function export(Request $request)
    {
        $query = Location::with('country');

        // Search functionality
        if ($request->has('name') || !empty($request->name)) {
            $searchTerm = trim(strtolower($request->name));
            $query->where(function($q) use ($searchTerm) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . $searchTerm . '%']);
            });
        }

        // Filter by country
        if ($request->has('country_id')) {
            $query->where('country_id', $request->country_id);
        }

        $locations = $query->orderBy('id', 'desc')->get();

        $columns = array_map(function($column) {
            return strtolower(urldecode(trim($column)));
        }, explode(',', $_GET['columns']));
 
        $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
        return Excel::download(new LocationsExport($locations, $request, $columns), 'tridad_' . $timestamp . '.xlsx');  
    }

     
}
