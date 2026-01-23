<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class LocationController extends Controller
{


    public function updatePositions(Request $request)
    {
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
        $existingIds = Location::whereIn('id', $ids)->pluck('id');
        
        // Find which IDs don't exist
        $invalidIds = $ids->diff($existingIds);
        
        if ($invalidIds->isNotEmpty()) {
            return response()->json([
                'message' => 'One or more location IDs do not exist',
                'invalid_ids' => $invalidIds->values()
            ], 422);
        }

        foreach ($request->items as $item) {
            Location::where('id', $item['id'])
                ->update(['position' => $item['position']]);
        }

        return response()->json(['message' => 'Positions updated successfully']);
    } 
    public function index(Request $request)
    {
        $query = Location::with('country');

        // Search functionality
        if ($request->has('name') || !empty($request->name)) {
            $searchTerm = trim(strtolower($request->name));
            //dd($searchTerm);
            $query->where(function($q) use ($searchTerm) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . $searchTerm . '%']);
            });
        }

        // Filter by country
        if ($request->has('country_id')) {
            $query->where('country_id', $request->country_id);
        }

        $locations = $query->orderBy('id', 'desc')->paginate($request->input('per_page', 20));

            $searchParam = [
                'name' => $request->name
            ];
        return response()->json([
            'message' => 'Locations retrieved successfully',
            'success' => true,
            'data' => $locations,
            'search' =>   $searchParam 
        ]);  
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'country_id' => 'required|exists:countries,id',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $location = Location::create($validator->validated());

        return response()->json([
            'message' => 'Location created successfully',
            'success' => true,
            'data' => $location->load('country')
        ], 200);
    }

    public function show(Location $location)
    {
        return response()->json([
            'message' => 'Location retrieved successfully',
            'success' => true,
            'data' => $location->load('country')
        ]);
    }

    public function update(Request $request, Location $location)
    {
        $validator = Validator::make($request->all(), [
            'country_id' => 'exists:countries,id',
            'name' => 'string|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $location->update($validator->validated());

        return response()->json([
            'message' => 'Location updated successfully',
            'success' => true,
            'data' => $location->load('country')
        ]);
    }

    public function destroy(Location $location)
    {
        $location->delete();

        return response()->json([
            'message' => 'Location deleted successfully',
            'success' => true
        ]);
    }

    // Additional method for country search
    public function searchCountries(Request $request)
    {
        //dd($request->all());
        $validator = Validator::make($request->all(), [
            'search' => 'required|string|min:2'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $searchTerm = trim(strtolower($request->search));
        $countries = Country::whereRaw('LOWER(name) LIKE ?', ['%' . $searchTerm . '%'])
                          ->orderBy('name')
                          ->limit(10)
                          ->get();

        return response()->json([
            'message' => 'Countries retrieved successfully',
            'success' => true,
            'data' => $countries
        ]);
    }
}
