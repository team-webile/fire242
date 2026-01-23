<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class SystemSettingsController extends Controller
{

    public function __construct()
    {
        DB::statement("SET TIME ZONE 'America/Nassau'");
    }

    public function show()
    {
        $settings = SystemSetting::first();
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
            'days.*' => 'required|string',
            'admin_active_time' => 'required|integer|min:1',
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

        $settings = SystemSetting::first();
        $settings->update([
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'active_time' => $request->daily_target,
            'daily_target' => $request->active_time,
            'days' => $days,
            'admin_active_time' => $request->admin_active_time,
        ]);

        // Convert comma-separated days back to array for response
        $settingsData = $settings->toArray();
        $settingsData['days'] = explode(',', $settingsData['days']);
        $settingsData['admin_active_time'] = $settingsData['admin_active_time'];
        return response()->json([
            'success' => true,
            'message' => 'System settings updated successfully',
            'data' => $settingsData
        ]);
    }
}