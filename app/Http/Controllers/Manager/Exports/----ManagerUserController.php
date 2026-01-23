<?php

namespace App\Http\Controllers\Manager\Exports;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Constituency;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\UsersExport;
use App\Exports\ManagerExport;
class ManagerUserController extends Controller
{
     
    
    public function export(Request $request)  
    {
        $query = User::where('role_id', 3); // Add surveys count

        if ($request->has('search')) {
            $searchTerm = trim(strtolower($request->search));
            $query->where(function($q) use ($searchTerm) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . $searchTerm . '%'])
                  ->orWhereRaw('LOWER(email) LIKE ?', ['%' . $searchTerm . '%'])
                  ->orWhereRaw('LOWER(phone) LIKE ?', ['%' . $searchTerm . '%']);
            });
        }

        $users = $query->orderBy('id', 'desc')->paginate(10);

        $users->getCollection()->transform(function($user) {
            $constituencyIds = $user->constituency_id ? explode(',', $user->constituency_id) : [];
            $constituencies = empty($constituencyIds) ? null : 
                Constituency::whereIn('id', $constituencyIds)
                    ->select('id', 'name', 'is_active')
                    ->get();
            
            $user->constituencies = $constituencies;
            return $user;
        });

        $columns = array_map(function($column) {
            return strtolower(urldecode(trim($column)));
        }, explode(',', $_GET['columns']));
        
        return Excel::download(new ManagerExport($users, $request, $columns), 'Managers.xlsx');   
    }

   

    
}
