<?php

namespace App\Http\Controllers\Admin\Exports;

use App\Http\Controllers\Controller;
use App\Models\Constituency;
use App\Models\Island;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ConstituenciesExport;

class ConstituencyController extends Controller
{
    public function index(Request $request)
    {
        $query = Constituency::query();
        
      
        if ($request->has('constituency_name') && !empty($request->constituency_name)) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->constituency_name) . '%']);
            $searchParams['constituency_name'] = $request->constituency_name;
        }

        if ($request->has('island_name') && !empty($request->island_name)) {
            $query->whereHas('island', function($q) use ($request) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->island_name) . '%']);
            });
            $searchParams['island_name'] = $request->island_name;
        }

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->boolean('status'));
            $searchParams['status'] = $request->boolean('status');
        }

        $constituencies = $query->orderBy('id','desc')->get();
       
        $columns = array_map(function($column) {
            return strtolower(urldecode(trim($column)));
        }, explode(',', $_GET['columns']));
        $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
        return Excel::download(new ConstituenciesExport($constituencies, $request, $columns ), 'Constituencies List_' . $timestamp . '.xlsx');
    }

   

// ... existing code ...

}