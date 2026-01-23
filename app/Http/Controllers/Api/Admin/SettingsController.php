<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DropdownType;
use App\Models\Location;
use App\Models\Party;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
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

    // Get all settings options
    public function getSettings()
    {
        $adminCheck = $this->checkAdminAccess();
        if ($adminCheck) return $adminCheck;
 
     
        $settings = [
            'religion' => DropdownType::where('type', 'religion')->where('status', 'active')->select('id', 'value')->orderBy('position', 'asc')->get(),
            'voter_in_house' => DropdownType::where('type', 'voter_in_house')->where('status', 'active')->select('id', 'value')->orderBy('position', 'asc')->get(),
            'gender' => DropdownType::where('type', 'gender')->where('status', 'active')->select('id', 'value')->orderBy('position', 'asc')->get(),
            'marital_status' => DropdownType::where('type', 'marital_status')->where('status', 'active')->select('id', 'value')->orderBy('position', 'asc')->get(),
            'employed' => DropdownType::where('type', 'employed')->where('status', 'active')->select('id', 'value')->orderBy('position', 'asc')->get(),
            'children' => DropdownType::where('type', 'children')->where('status', 'active')->select('id', 'value')->orderBy('position', 'asc')->get(),
            'employment_type' => DropdownType::where('type', 'employment_type')->where('status', 'active')->select('id', 'value')->orderBy('position', 'asc')->get(),
            'voted_last_election' => DropdownType::where('type', 'voted_last_election')->where('status', 'active')->select('id', 'value')->orderBy('position', 'asc')->get(),
            'off_island' => DropdownType::where('type', 'off_island')->where('status', 'active')->select('id', 'value')->orderBy('position', 'asc')->get(),
            'employment_sector' => DropdownType::where('type', 'employment_sector')->where('status', 'active')->select('id', 'value')->orderBy('position', 'asc')->get(),
            'special_comments' => DropdownType::where('type', 'special_comments')->where('status', 'active')->select('id', 'value')->orderBy('position', 'asc')->get(),
        ];
        return response()->json($settings);
    }

    // Add new setting option
    public function store(Request $request)
    {
        $adminCheck = $this->checkAdminAccess();
        if ($adminCheck) return $adminCheck;

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:religion,where_voted_last,voter_in_house,gender,marital_status,employed,children,voting_for,employment_type,voted_last_election,off_island,employment_sector,special_comments',
            'value' => 'required|string|max:255',
        ]);  
 
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $setting = DropdownType::create($request->all());
        
        // Clear the cache when creating new setting
        Cache::forget('active_dropdowns');
        
        return response()->json(['setting' => $setting], 201);
    }

    // Update setting option
    public function update(Request $request, DropdownType $setting)
    {
        $adminCheck = $this->checkAdminAccess();
        if ($adminCheck) return $adminCheck;
         
        $validator = Validator::make($request->all(), [
            'value' => 'required|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $setting->update($request->all());
        Cache::forget('active_dropdowns');
        return response()->json(['setting' => $setting]);
    } 

    // Delete setting option
    public function destroy($id)
    {   
        $adminCheck = $this->checkAdminAccess();
        if ($adminCheck) return $adminCheck;

        $setting = DropdownType::find($id);
        
        if (!$setting) {
            return response()->json(['message' => 'Setting option not found'], 404);
        }

        $setting->delete();
        Cache::forget('active_dropdowns');
        return response()->json(['message' => 'Setting option deleted successfully'], 200);
    }

    // List all options by type
    public function getByType($type)
    {
        $adminCheck = $this->checkAdminAccess();
        if ($adminCheck) return $adminCheck;

        $options = DropdownType::where('type', $type)->get();
        return response()->json(['options' => $options]);
    }

    public function show($id)
    {
        $adminCheck = $this->checkAdminAccess();
        if ($adminCheck) return $adminCheck;

        $setting = DropdownType::find($id);
        
        if (!$setting) {
            return response()->json([
                'message' => 'Setting option not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $setting
        ]);
    }

// ... existing code ...

    // Update positions of settings
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
        $existingIds = DropdownType::whereIn('id', $ids)->pluck('id');
        
        // Find which IDs don't exist
        $invalidIds = $ids->diff($existingIds);
        
        if ($invalidIds->isNotEmpty()) {
            return response()->json([
                'message' => 'One or more dropdown IDs do not exist',
                'invalid_ids' => $invalidIds->values()
            ], 422);
        }

        foreach ($request->items as $item) {
            DropdownType::where('id', $item['id'])
                ->update(['position' => $item['position']]);
        }

        Cache::forget('active_dropdowns');
        return response()->json(['message' => 'Positions updated successfully']);
    } 

    // Modify getSettings method to include ordering by position
    

// ... existing code ...

  // ... existing code ...
  public function updateActivity(Request $request)
  {
      $adminCheck = $this->checkAdminAccess();
      if ($adminCheck) return $adminCheck;
  
      $validator = Validator::make($request->all(), [
          'start_time' => 'required|date_format:H:i',
          'end_time' => 'required|date_format:H:i',
          'active_minutes' => 'required|integer|min:1|max:1440', // Maximum minutes in a day
      ]);
  
      if ($validator->fails()) {
          return response()->json(['errors' => $validator->errors()], 422);
      }
  
      // Convert times to EST
      $startTime = date('H:i', strtotime($request->start_time));
      $endTime = date('H:i', strtotime($request->end_time));
  
      // Update or create start time setting
      DropdownType::updateOrCreate(
          ['type' => 'system_settings', 'key' => 'start_time'],
          [
              'value' => $startTime,
              'status' => 'active',
              'position' => 1
          ]
      );
  
      // Update or create end time setting
      DropdownType::updateOrCreate(
          ['type' => 'system_settings', 'key' => 'end_time'],
          [
              'value' => $endTime,
              'status' => 'active',
              'position' => 2
          ]
      );
  
      // Update or create active minutes setting
      DropdownType::updateOrCreate(
          ['type' => 'system_settings', 'key' => 'active_minutes'],
          [
              'value' => (string)$request->active_minutes,
              'status' => 'active',
              'position' => 3
          ]
      );
  
      Cache::forget('system_activity_settings');
  
      return response()->json([
          'success' => true,
          'message' => 'System activity settings updated successfully',
          'data' => [
              'start_time' => $startTime,
              'end_time' => $endTime,
              'active_minutes' => $request->active_minutes
          ]
      ]);
  }
  


// ... existing code ...

}