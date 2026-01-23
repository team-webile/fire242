<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\ManagerSystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
class ManagerSystemSettingsController extends Controller 
{
    public function __construct()
    {
        DB::statement("SET TIME ZONE 'America/Nassau'");
    }
    public function index()
    {   
        DB::statement("SET TIME ZONE 'America/Nassau'");
        $settings = ManagerSystemSetting::with('manager')->where('manager_id', Auth::user()->id)->where('all_constituency', "1")->first();  

        return response()->json($settings);
    }

    public function show($id)
    { 
        $settings =[];
        if(isset($id)){
            $settings = ManagerSystemSetting::with('manager')->where('manager_id', Auth::user()->id)->where('constituency_id', $id)->first();
            
        }else{
            $settings = ManagerSystemSetting::with('manager')->where('manager_id', Auth::user()->id)->where('all_constituency', "1")->first();   
        }
        
        return response()->json($settings);
    }

    public function store(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'active_time' => 'required|integer|min:1',
            'daily_target' => 'required|integer|min:1',
            'days' => 'required|array',
            'days.*' => 'required|string',
            'constituency_id' => 'required|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        } 

        $days = implode(',', $request->days); // Convert days array to comma-separated string
   
        if($request->constituency_id == 'all'){
          
            $get_constituency = explode(',', Auth::user()->constituency_id);

            foreach($get_constituency as $constituency){
                $settings = ManagerSystemSetting::updateOrCreate(
                    ['manager_id' => Auth::user()->id, 'constituency_id' => $constituency],
                    [
                        'start_time' => $request->start_time,
                        'end_time' => $request->end_time,
                        'active_time' => $request->active_time,
                        'daily_target' => $request->daily_target,
                        'days' => $days ,
                        'all_constituency' => '1',
                    ]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'System settings created or updated successfully',
                'data' => $settings
            ]); 

        } else { 
          
            $settings = ManagerSystemSetting::updateOrCreate(
                ['manager_id' => Auth::user()->id, 'constituency_id' => $request->constituency_id],
                [
                    'start_time' => $request->start_time,
                    'end_time' => $request->end_time,
                    'active_time' => $request->active_time,
                    'daily_target' => $request->daily_target,
                    'days' => $days,
                    'all_constituency' => '0',
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'System settings created or updated successfully',
                'data' => $settings
            ]); 

        } 

      
    }

   

    public function destroy($id)
    {
        $settings = ManagerSystemSetting::where('manager_id', Auth::user()->id)->find($id);
        if (!$settings) {
            return response()->json(['message' => 'Settings not found'], 404);
        }

        $settings->delete();

        return response()->json([
            'success' => true,
            'message' => 'System settings deleted successfully'
        ]);
    }
}