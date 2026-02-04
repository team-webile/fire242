<?php

namespace App\Http\Controllers\Admin\Exports;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Constituency;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Survey;
use App\Exports\ManagerExport; 
class ManagerUserController extends Controller
{
     
    
    public function export(Request $request)  
    {
        $query = User::where('role_id', 3)
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
            
        
        if ($request->has('time_active')) {
            $query->where('time_active',$request->time_active); 
        }

        // Filter users based on is_coordinator if requested
        if ($request->has('is_coordinator')) {
            $isCoordinator = (int) $request->is_coordinator; // Ensure it's an integer (0 or 1)
            $query->whereRaw("
                CASE 
                    WHEN LENGTH(constituency_id) - LENGTH(REPLACE(constituency_id, ',', '')) = 0 THEN 0
                    ELSE 1
                END = ?", [$isCoordinator]);
        }
    
        $users = $query->orderBy('id', 'desc')->paginate(10);
    
        $users->getCollection()->transform(function($user) {
            $constituencyIds = $user->constituency_id ? explode(',', $user->constituency_id) : [];
            $constituencies = empty($constituencyIds) ? null : 
                Constituency::whereIn('id', $constituencyIds)
                    ->select('id', 'name', 'is_active')
                    ->get();
            
            $user->constituencies = $constituencies;
    
            // Get survey count for this user
            $surveyCount = Survey::where('user_id', $user->id)->count();
            $user->survey_count = $surveyCount;
    
            // Determine coordinator status
            $user->is_coordinator = count($constituencyIds) == 1 ? 0 : 1;
    
            return $user;
        });

        $columns = array_map(function($column) {
            return strtolower(urldecode(trim($column)));
        }, explode(',', $_GET['columns']));
        
        $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
        return Excel::download(new ManagerExport($users, $request, $columns), 'Managers_' . $timestamp . '.xlsx');   
    }

   

    
}
