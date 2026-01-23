<?php

namespace App\Http\Controllers\User\Exports;

use App\Http\Controllers\Controller;
use App\Models\UnregisteredVoter;
use App\Models\Voter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Exports\UnregisteredVotersExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;

class UnregisteredVoterController extends Controller
{
    public function export(Request $request)
    {
        try {
            $user = Auth::user();

            // Add validation for constituency_id
            if (empty($user->constituency_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No constituency assigned to user',
                    'data' => [],
                    'meta' => [
                        'total' => 0,
                        'per_page' => 0,
                        'current_page' => 1,
                        'last_page' => 1,
                    ]
                ]);
            }

            $constituency_ids = explode(',', $user->constituency_id);
            $perPage = $request->input('per_page', 20); 

            $query = UnregisteredVoter::with(['voter' => function($query) {
                $query->select('id', 'voter', 'first_name', 'second_name', 'address', 'pobse', 'const','surname','last_name');
            }]);
            // ->where('user_id', $user->id);
            // ->whereHas('voter', function($query) use ($constituency_ids) {
            //     $query->whereIn('const', $constituency_ids);
            // });

            // Add search functionality
            // Apply individual search filters

                if (isset($request->first_name) && !empty($request->first_name)) {
                    $query->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->first_name) . '%']);
                }
                if (isset($request->last_name) && !empty($request->last_name)) {
                    $query->whereRaw('LOWER(last_name) LIKE ?', ['%' . strtolower($request->last_name) . '%']);
                }


            if ($request->has('phone_number')) {
                $query->where('phone_number', 'LIKE', '%' . $request->phone_number . '%');
            }

            if ($request->has('new_email')) {
                $query->whereRaw('LOWER(new_email) LIKE ?', ['%' . strtolower($request->new_email) . '%']);
            }

            if ($request->has('new_address')) {
                $query->whereRaw('LOWER(new_address) LIKE ?', ['%' . strtolower($request->new_address) . '%']);
            }

            if ($request->has('survey_id')) {
                $query->where('survey_id', 'LIKE', '%' . $request->survey_id . '%');
            }

            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->has('voter_id')) {
                $query->where('voter_id', $request->voter_id);
            }

            // Search in related voter fields
            if ($request->has('voter_first_name')) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->voter_first_name) . '%']);
                });
            }

            if ($request->has('voter_second_name')) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(second_name) LIKE ?', ['%' . strtolower($request->voter_second_name) . '%']);
                });
            }

            if ($request->has('voter_number')) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->where('voter', 'LIKE', '%' . $request->voter_number . '%');
                });
            }

            if ($request->has('voter_address')) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(address) LIKE ?', ['%' . strtolower($request->voter_address) . '%']);
                });
            }

            // Add filters
            if ($request->has('gender')) {
                $query->whereRaw('LOWER(gender) = ?', [strtolower($request->gender)]);
            }

            if ($request->has('date_from') && $request->has('date_to')) {
                $query->whereBetween('date_of_birth', [$request->date_from, $request->date_to]);
            }

             
            $query->orderBy('id', 'desc')->get();
            // Get paginated results
            $unregisteredVoters = $query->paginate($perPage);

            // Build search parameters object
            $searchParams = [
                'name' => $request->search ?? null,
                'phone_number' => $request->search ?? null,
                'new_email' => $request->search ?? null,
                'new_address' => $request->search ?? null,
                'survey_id' => $request->search ?? null,
                'user_id' => $request->search ?? null,
                'voter_id' => $request->search ?? null,
                'voter_first_name' => $request->search ?? null,
                'voter_second_name' => $request->search ?? null,
                'voter_number' => $request->search ?? null,
                'voter_address' => $request->search ?? null
            ];
            $columns = array_map(function($column) {
                return strtolower(urldecode(trim($column)));
            }, explode(',', $_GET['columns']));
           
            return Excel::download(new UnregisteredVotersExport($unregisteredVoters, $request, $columns), 'Unregistered Voters.xlsx');  

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving unregistered voters',
                'error' => $e->getMessage()
            ], 500);
        }
    } 
    
    

    public function get_uncontacted_votersExport(Request $request)
    {
        try {
            $user = Auth::user();
            $perPage = $request->input('per_page', 20);

            $query = UnregisteredVoter::with(['voter' => function($query) {
                $query->select('id', 'voter', 'first_name', 'second_name', 'address', 'pobse', 'const','surname','last_name');
            }])
            // ->where('user_id', $user->id)
            ->where('contacted', false);

            // Add search filters
            if (isset($request->first_name) && !empty($request->first_name)) {
                $query->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->first_name) . '%']);
            }
            if (isset($request->last_name) && !empty($request->last_name)) {
                $query->whereRaw('LOWER(last_name) LIKE ?', ['%' . strtolower($request->last_name) . '%']);
            }

            if ($request->has('phone_number')) {
                $query->where('phone_number', 'LIKE', '%' . $request->phone_number . '%');
            }

            if ($request->has('new_email')) {
                $query->whereRaw('LOWER(new_email) LIKE ?', ['%' . strtolower($request->new_email) . '%']);
            }

            if ($request->has('new_address')) {
                $query->whereRaw('LOWER(new_address) LIKE ?', ['%' . strtolower($request->new_address) . '%']);
            }

            if ($request->has('survey_id')) {
                $query->where('survey_id', 'LIKE', '%' . $request->survey_id . '%');
            }

            if ($request->has('user_id')) {
                $query->where('user_id', 'LIKE', '%' . $request->user_id . '%');
            }

            if ($request->has('voter_id')) {
                $query->where('voter_id', 'LIKE', '%' . $request->voter_id . '%');
            }

            // Search in related voter fields
            if ($request->has('voter_first_name')) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->voter_first_name) . '%']);
                });
            }

            if ($request->has('voter_second_name')) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(second_name) LIKE ?', ['%' . strtolower($request->voter_second_name) . '%']);
                });
            }

            if ($request->has('voter_number')) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->where('voter', 'LIKE', '%' . $request->voter_number . '%');
                });
            }

            if ($request->has('voter_address')) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(address) LIKE ?', ['%' . strtolower($request->voter_address) . '%']);
                });
            }

            // Add filters
            if ($request->has('gender')) {
                $query->whereRaw('LOWER(gender) = ?', [strtolower($request->gender)]);
            }

            if ($request->has('date_from') && $request->has('date_to')) {
                $query->whereBetween('date_of_birth', [$request->date_from, $request->date_to]);
            }

            // Add sorting
            $sortField = $request->get('sort_by', 'id');
            $sortDirection = $request->get('sort_direction', 'desc');
            $allowedSortFields = ['id', 'name', 'date_of_birth', 'gender', 'created_at', 'survey_id', 'user_id', 'voter_id'];
            
            if (in_array($sortField, $allowedSortFields)) {
                $query->orderBy($sortField, $sortDirection);
            }

            $uncontactedVoters = $query->paginate($perPage);

            // Build search parameters object
            $searchParams = [
                'first_name' => $request->first_name ?? null,
                'last_name' => $request->last_name ?? null,
                'phone_number' => $request->phone_number ?? null,
                'new_email' => $request->new_email ?? null,
                'new_address' => $request->new_address ?? null,
                'survey_id' => $request->survey_id ?? null,
                'user_id' => $request->user_id ?? null,
                'voter_id' => $request->voter_id ?? null,
                'voter_first_name' => $request->search ?? null,
                'voter_second_name' => $request->search ?? null,
                'voter_number' => $request->search ?? null,
                'voter_address' => $request->search ?? null
            ];

            $columns = array_map(function($column) {
                return strtolower(urldecode(trim($column)));
            }, explode(',', $_GET['columns']));
             
            return Excel::download(new UnregisteredVotersExport($uncontactedVoters, $request, $columns), 'Unregister uncontacted Voters.xlsx');  

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving uncontacted voters',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function get_contacted_votersExport(Request $request)
    {
        try {
            $user = Auth::user();
            $perPage = $request->input('per_page', 20);

            $query = UnregisteredVoter::with(['voter' => function($query) {
                $query->select('id', 'voter', 'first_name', 'second_name', 'address', 'pobse', 'const','surname','last_name'); 
            }])
            // ->where('user_id', $user->id)
            ->where('contacted', true);

            // Add search filters
            if (isset($request->first_name) && !empty($request->first_name)) {
                $query->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->first_name) . '%']);
            }
            if (isset($request->last_name) && !empty($request->last_name)) {
                $query->whereRaw('LOWER(last_name) LIKE ?', ['%' . strtolower($request->last_name) . '%']);
            }

            if ($request->has('phone_number')) {
                $query->where('phone_number', 'LIKE', '%' . $request->phone_number . '%');
            }

            if ($request->has('new_email')) {
                $query->whereRaw('LOWER(new_email) LIKE ?', ['%' . strtolower($request->new_email) . '%']);
            }

            if ($request->has('new_address')) {
                $query->whereRaw('LOWER(new_address) LIKE ?', ['%' . strtolower($request->new_address) . '%']);
            }

            if ($request->has('survey_id')) {
                $query->where('survey_id', 'LIKE', '%' . $request->survey_id . '%');
            }

            if ($request->has('user_id')) {
                $query->where('user_id', 'LIKE', '%' . $request->user_id . '%');
            }

            if ($request->has('voter_id')) {
                $query->where('voter_id', 'LIKE', '%' . $request->voter_id . '%');
            }

            // Search in related voter fields
            if ($request->has('voter_first_name')) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->voter_first_name) . '%']);
                });
            }

            if ($request->has('voter_second_name')) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(second_name) LIKE ?', ['%' . strtolower($request->voter_second_name) . '%']);
                });
            }

            if ($request->has('voter_number')) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->where('voter', 'LIKE', '%' . $request->voter_number . '%');
                });
            }

            if ($request->has('voter_address')) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(address) LIKE ?', ['%' . strtolower($request->voter_address) . '%']);
                });
            }

            // Add filters
            if ($request->has('gender')) {
                $query->whereRaw('LOWER(gender) = ?', [strtolower($request->gender)]);
            }

            if ($request->has('date_from') && $request->has('date_to')) {
                $query->whereBetween('date_of_birth', [$request->date_from, $request->date_to]);
            }

            // Add sorting
            $sortField = $request->get('sort_by', 'id');
            $sortDirection = $request->get('sort_direction', 'desc');
            $allowedSortFields = ['id', 'name', 'date_of_birth', 'gender', 'created_at', 'survey_id', 'user_id', 'voter_id'];
            
            if (in_array($sortField, $allowedSortFields)) {
                $query->orderBy($sortField, $sortDirection);
            }

            $uncontactedVoters = $query->paginate($perPage);

            // Build search parameters object
            $searchParams = [
                'first_name' => $request->first_name ?? null,
                'last_name' => $request->last_name ?? null,
                'phone_number' => $request->phone_number ?? null,
                'new_email' => $request->new_email ?? null,
                'new_address' => $request->new_address ?? null,
                'survey_id' => $request->survey_id ?? null,
                'user_id' => $request->user_id ?? null,
                'voter_id' => $request->voter_id ?? null,
                'voter_first_name' => $request->search ?? null,
                'voter_second_name' => $request->search ?? null,
                'voter_number' => $request->search ?? null,
                'voter_address' => $request->search ?? null
            ];

            $columns = array_map(function($column) {
                return strtolower(urldecode(trim($column)));
            }, explode(',', $_GET['columns']));
             
            return Excel::download(new UnregisteredVotersExport($uncontactedVoters, $request, $columns), 'Unregistered Contacted Voters.xlsx');    

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving uncontacted voters',
                'error' => $e->getMessage()
            ], 500);
        }
    } 
 

}
