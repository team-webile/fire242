<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Party;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class PartyController extends Controller
{   
    private function checkAdminAccess()
    {
        if (!Auth::check() || !Auth::user()->role || Auth::user()->role->name !== 'Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }
        return null; 
    }

    public function updatePositions(Request $request)
    {
        $adminCheck = $this->checkAdminAccess();
        if ($adminCheck) return $adminCheck;

        $validator = Validator::make($request->all(), [
            'items' => 'required|array',
            'items.*.id' => 'required|integer',
            'items.*.position' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // First verify all IDs exist before updating
        $ids = collect($request->items)->pluck('id');
        $existingIds = Party::whereIn('id', $ids)->pluck('id');
        
        // Find which IDs don't exist
        $invalidIds = $ids->diff($existingIds);
        
        if ($invalidIds->isNotEmpty()) {
            return response()->json([
                'message' => 'One or more party IDs do not exist',
                'invalid_ids' => $invalidIds->values()
            ], 422);
        }

        foreach ($request->items as $item) {
            Party::where('id', $item['id'])
                ->update(['position' => $item['position']]);
        }

        // Clear cache when positions are updated
        Cache::flush();

        return response()->json(['message' => 'Positions updated successfully']);
    } 

    public function index(Request $request)
    {
        $adminCheck = $this->checkAdminAccess();
        if ($adminCheck) return $adminCheck;

        // Generate cache key based on all request parameters
        $cacheKey = 'party_index_' . md5(json_encode($request->all()) . '_' . $request->get('per_page', 20));
        
        // Check if data exists in cache, otherwise execute query and cache forever
        $response = Cache::rememberForever($cacheKey, function() use ($request) {
            $perPage = $request->input('per_page', 20);
            $query = Party::query();

            if ($request->has('party_name') && !empty($request->party_name)) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->party_name) . '%']);
            }

            if ($request->has('short_name') && !empty($request->short_name)) {
                $query->whereRaw('LOWER(short_name) LIKE ?', ['%' . strtolower($request->short_name) . '%']);
            }

            $searchParams = [
                'party_name' => $request->party_name,
                'short_name' => $request->short_name
            ];

            return [
                'success' => true,
                'data' => $query->orderBy('position', 'asc')->paginate($perPage),
                'search_params' => $searchParams
            ];
        });

        return response()->json($response);
    }

    public function store(Request $request)
    {
        $adminCheck = $this->checkAdminAccess();
        if ($adminCheck) return $adminCheck;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:parties',
            'short_name' => 'required|string|max:10|unique:parties',
            'status' => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $party = Party::create($request->all());
        
        // Clear cache when party is created
        Cache::flush(); 
        
        return response()->json(['success' => true, 'message' => 'Party created successfully', 'party' => $party], 201);
    }

    public function show(Party $party)
    {
        $adminCheck = $this->checkAdminAccess();
        if ($adminCheck) return $adminCheck;

        return response()->json(['success' => true,'party' => $party]); 
    }

    public function update(Request $request, Party $party)
    {
        $adminCheck = $this->checkAdminAccess();
        if ($adminCheck) return $adminCheck;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:parties,name,' . $party->id,
            'short_name' => 'required|string|max:10|unique:parties,short_name,' . $party->id,
            'status' => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $party->update($request->all());
        
        // Clear cache when party is updated
        Cache::flush();
        
        return response()->json(['success' => true,'party' => $party]); 
    } 

    public function destroy($id)
    {   
        $adminCheck = $this->checkAdminAccess();
        if ($adminCheck) return $adminCheck;

        $party = Party::find($id);
        if (!$party) {
            return response()->json(['success' => false,'message' => 'Party not found'], 404);
        }
        $party->delete();
        
        // Clear cache when party is deleted
        Cache::flush();
        
        return response()->json(['success' => true,'message' => 'Party deleted successfully'], 200); 
    }

    public function livesearch(Request $request)
    {
        $adminCheck = $this->checkAdminAccess();
        if ($adminCheck) return $adminCheck;

        $query = Party::query();

        if ($request->has('party') && !empty($request->party)) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->party) . '%']);
        }

        return response()->json(['success' => true, 'data' => $query->get()]);
    }



    public function getAllParties()
    {
          
        $parties = Party::all();
        return response()->json(['success' => true, 'data' => $parties]);
    }   

    public function livesearchGetAllParties(Request $request)
    {
        $query = Party::query();
        if ($request->has('search') && !empty($request->party)) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->search) . '%']);
        }
        return response()->json(['success' => true, 'data' => $query->get()]);
    }
 
} 