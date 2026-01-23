<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\ManagerSystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ManagerSystemSettingsController extends Controller
{
    public function show()
    {
        $settings = ManagerSystemSetting::where('manager_id', Auth::user()->id)->first(); 
        return response()->json($settings);
    }

     
    public function update(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'active_time' => 'required|integer|min:1', 
            'daily_target' => 'required|integer|min:1',
            'days' => 'required|array',
            'days.*' => 'required|string' // Validate each day as string
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed', 
                'errors' => $validator->errors()
            ], 422);
        }

        // Convert days array to comma-separated string
        $days = implode(',', $request->days);

        $settings = ManagerSystemSetting::where('manager_id', Auth::user()->id)->first();
        $settings->update([
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'active_time' => $request->daily_target,
            'daily_target' => $request->active_time,
            'days' => $days // Store comma-separated day names
        ]);

        // Convert comma-separated days back to array for response
        $settingsData = $settings->toArray();
        $settingsData['days'] = explode(',', $settingsData['days']);

        return response()->json([
            'success' => true,
            'message' => 'System settings updated successfully',
            'data' => $settingsData
        ]);
    }
}