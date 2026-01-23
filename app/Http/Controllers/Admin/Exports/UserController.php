<?php

namespace App\Http\Controllers\Admin\Exports;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Constituency;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\UsersExport;
use App\Exports\AdminUsersExport;
use App\Exports\SurveyTargetExport;
use App\Models\DailySurveyTrack;
use App\Models\Survey;
class UserController extends Controller
{
     
    
    public function adminUsersExport(Request $request)  
    {
        $query = User::where('role_id', 1)
                    ->withCount('surveys'); // Add surveys count

        
        if ($request->has('search')) {
            $searchTerm = trim(strtolower($request->search));
            $query->where(function($q) use ($searchTerm) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . $searchTerm . '%']);
            });
        } 

          
        if ($request->has('address')) {
            $address = strtolower($request->address);
            $query->whereRaw('LOWER(address) LIKE ?', ['%' . $address . '%']);
        }

        if ($request->has('email')) {
            $email = strtolower($request->email);
            $query->whereRaw('LOWER(email) LIKE ?', ['%' . $email . '%']);
        } 


        if ($request->has('status')) {
            $status = strtolower($request->status);
            if ($status === 'active') {
                $query->where('is_active', 1);
            } else if ($status === 'inactive') {
                $query->where('is_active', 0);
            }
        } 


        $users = $query->orderBy('id', 'desc')->get(); 

        
          
        $columns = array_map(function($column) {
            return strtolower(urldecode(trim($column)));
        }, explode(',', $_GET['columns']));
      
        $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
        return Excel::download(new AdminUsersExport($users, $request, $columns), 'Admin-Users_' . $timestamp . '.xlsx');   
    }
    public function export(Request $request)  
    {
        $query = User::where('role_id', 2)
                    ->withCount('surveys'); // Add surveys count

        
        if ($request->has('search')) { 
            $searchTerm = trim(strtolower($request->search));
            $query->where(function($q) use ($searchTerm) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . $searchTerm . '%']);
            });
        }

        if ($request->has('constituency_name') && !empty($request->constituency_name)) {
            $searchTerm = strtolower($request->constituency_name);
            $constituencies = Constituency::whereRaw('LOWER(name) LIKE ?', ['%' . $searchTerm . '%'])
                ->pluck('id')
                ->toArray();

            if (!empty($constituencies)) {
                $query->where(function($q) use ($constituencies) {
                    foreach($constituencies as $constituencyId) {
                        $q->orWhereRaw("constituency_id ~ ?", ["(^|,)" . $constituencyId . "($|,)"]);
                    }
                });
            }
        }

        if ($request->has('address')) {
            $address = strtolower($request->address);
            $query->whereRaw('LOWER(address) LIKE ?', ['%' . $address . '%']);
        }

        if ($request->has('email')) {
            $email = strtolower($request->email);
            $query->whereRaw('LOWER(email) LIKE ?', ['%' . $email . '%']);
        } 


        if ($request->has('status')) {
            $status = strtolower($request->status);
            if ($status === 'active') {
                $query->where('is_active', 1);
            } else if ($status === 'inactive') {
                $query->where('is_active', 0);
            }
        } 


        $users = $query->orderBy('id', 'desc')->get(); 

        $users->transform(function($user) {
            $constituencyIds = $user->constituency_id ? explode(',', $user->constituency_id) : [];
            $constituencies = empty($constituencyIds) ? null : 
                Constituency::whereIn('id', $constituencyIds)
                    ->select('id', 'name', 'is_active')
                    ->get();
            
            $user->constituencies = $constituencies;

            // Get survey count for this user
            $surveyCount = Survey::where('user_id', $user->id)->count();
            $dailySurveyCount = DailySurveyTrack::where('user_id', $user->id)->where('date', \Carbon\Carbon::today())->first();
            $user->survey_count = $surveyCount;
            $user->daily_survey_count = $dailySurveyCount; 

            return $user;
        });

        $columns = array_map(function($column) {
            return strtolower(urldecode(trim($column)));
        }, explode(',', $_GET['columns']));
      
        $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
        return Excel::download(new UsersExport($users, $request, $columns), 'Canvassers_' . $timestamp . '.xlsx');   
    }
    // public function export(Request $request)  
    // {
    //     $query = User::where('role_id', 2)
    //                 ->withCount('surveys'); // Add surveys count

    //     if ($request->has('search')) {
    //         $searchTerm = trim(strtolower($request->search));
    //         $query->where(function($q) use ($searchTerm) {
    //             $q->whereRaw('LOWER(name) LIKE ?', ['%' . $searchTerm . '%'])
    //               ->orWhereRaw('LOWER(email) LIKE ?', ['%' . $searchTerm . '%'])
    //               ->orWhereRaw('LOWER(phone) LIKE ?', ['%' . $searchTerm . '%']);
    //         });
    //     }

    //     $users = $query->orderBy('id', 'desc')->get(); 

    //     $users->getCollection()->transform(function($user) {
    //         $constituencyIds = $user->constituency_id ? explode(',', $user->constituency_id) : [];
    //         $constituencies = empty($constituencyIds) ? null : 
    //             Constituency::whereIn('id', $constituencyIds)
    //                 ->select('id', 'name', 'is_active')
    //                 ->get();
            
    //         $user->constituencies = $constituencies;

    //         // Get survey count for this user
    //         $surveyCount = Survey::where('user_id', $user->id)->count();
    //         $dailySurveyCount = DailySurveyTrack::where('user_id', $user->id)->where('date', \Carbon\Carbon::today())->first();
    //         $user->survey_count = $surveyCount;
    //         $user->daily_survey_count = $dailySurveyCount; 

    //         return $user;
    //     });

    //     $columns = array_map(function($column) {
    //         return strtolower(urldecode(trim($column)));
    //     }, explode(',', $_GET['columns']));
      
    //     return Excel::download(new UsersExport($users, $request, $columns), 'Canvassers.xlsx');   
    // }

    
    public function getUserSurveyCount(Request $request, $id){
        $query = DailySurveyTrack::with('user')->where('user_id', $id);
        
        if ($request->filled('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('date', '<=', $request->end_date);
        }

        $dailySurveyCount = $query->orderBy('date', 'desc')->get();

        $columns = array_map(function($column) {
            return strtolower(urldecode(trim($column)));
        }, explode(',', $_GET['columns']));
      
        $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
        return \Maatwebsite\Excel\Facades\Excel::download(new SurveyTargetExport($dailySurveyCount, $request, $columns), 'Canvassers-Target-Surveys_' . $timestamp . '.xlsx');
    }


    
}
