<?php

namespace App\Http\Controllers\Admin\Exports;

use App\Http\Controllers\Controller;
use App\Models\Party;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PartiesExport;

class ExportPartyController extends Controller
{   
     
    public function export(Request $request)
    {   
        $query = Party::query();

        if ($request->has('party_name') && !empty($request->party_name)) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->party_name) . '%']);
            $searchParams['party_name'] = $request->party_name;
        } 

        if ($request->has('short_name') && !empty($request->short_name)) {
            $query->whereRaw('LOWER(short_name) LIKE ?', ['%' . strtolower($request->short_name) . '%']);
        }

        $parties = $query->orderBy('position', 'asc')->get();

        $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
        return Excel::download(new PartiesExport($parties, $request), 'Parties List_' . $timestamp . '.xlsx');
    }
} 