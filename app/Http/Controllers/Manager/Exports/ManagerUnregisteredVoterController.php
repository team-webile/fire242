<?php

namespace App\Http\Controllers\Manager\Exports;

use App\Http\Controllers\Controller;
use App\Models\UnregisteredVoter;
use App\Models\Voter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\UnregisteredVotersExport;
use App\Exports\DiffAddressUnregisteredVotersExport;
class ManagerUnregisteredVoterController extends Controller
{
    public function export(Request $request)
    {
        try {
            $user = Auth::user();

            
            $query = UnregisteredVoter::with(['voter' => function($query) {
                $query->select('id', 'voter', 'first_name', 'second_name', 'address', 'pobse', 'const','surname');
            }]);

            // Add search functionality
            // Apply individual search filters
            $underAge25 = $request->input('under_age_25');
            if (isset($underAge25) && $underAge25=== 'yes') {
                $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, CAST(date_of_birth AS DATE))) < 25');
            }
            

            if (isset($request->start_date) && !empty($request->start_date)) {
                $query->where('created_at', '>=', $request->start_date . ' 00:00:00');
            }

            if (isset($request->end_date) && !empty($request->end_date)) {
                $query->where('created_at', '<=', $request->end_date . ' 23:59:59');
            }

            if (isset($request->first_name) && !empty($request->first_name)) {
                $query->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->first_name) . '%']);
            }
            if (isset($request->last_name) && !empty($request->last_name)) {
                $query->whereRaw('LOWER(last_name) LIKE ?', ['%' . strtolower($request->last_name) . '%']);
            }

            if (isset($request->phone_number) && !empty($request->phone_number)) {
                $query->where('phone_number', 'LIKE', '%' . $request->phone_number . '%');
            }

            if (isset($request->new_email) && !empty($request->new_email)) {
                $query->whereRaw('LOWER(new_email) LIKE ?', ['%' . strtolower($request->new_email) . '%']);
            }

            if (isset($request->new_address) && !empty($request->new_address)) {
                $query->whereRaw('LOWER(new_address) LIKE ?', ['%' . strtolower($request->new_address) . '%']);
            }

            if (isset($request->survey_id) && !empty($request->survey_id)) {
                $query->where('survey_id', 'LIKE', '%' . $request->survey_id . '%');
            }

            if (isset($request->user_id) && !empty($request->user_id)) {
                $query->where('user_id', 'LIKE', '%' . $request->user_id . '%');
            }

            if (isset($request->voter_id) && !empty($request->voter_id)) {
                $query->where('voter_id', 'LIKE', '%' . $request->voter_id . '%');
            }

            // Search in related voter fields
            if (isset($request->voter_first_name) && !empty($request->voter_first_name)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->voter_first_name) . '%']);
                });
            }

            if (isset($request->voter_second_name) && !empty($request->voter_second_name)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(second_name) LIKE ?', ['%' . strtolower($request->voter_second_name) . '%']);
                });
            }

            if (isset($request->voter_number) && !empty($request->voter_number)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->where('voter', 'LIKE', '%' . $request->voter_number . '%');
                });
            }

            if (isset($request->voter_address) && !empty($request->voter_address)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(address) LIKE ?', ['%' . strtolower($request->voter_address) . '%']);
                });
            }

            // Add filters
            if (isset($request->gender) && !empty($request->gender)) {
                $query->whereRaw('LOWER(gender) = ?', [strtolower($request->gender)]);
            }

            if (isset($request->date_from) && !empty($request->date_from) && isset($request->date_to) && !empty($request->date_to)) {
                $query->whereBetween('date_of_birth', [$request->date_from, $request->date_to]);
            }
            
                $query->orderBy('id', 'desc');
            

            // Get paginated results
            $unregisteredVoters = $query->get();

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
                'voter_address' => $request->search ?? null,
                'under_age_25' => $request->under_age_25 ?? null
            ];

            $columns = array_map(function($column) {
                return strtolower(urldecode(trim($column)));
            }, explode(',', $_GET['columns']));
        
            $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
            return Excel::download(new UnregisteredVotersExport($unregisteredVoters, $request, $columns), 'Unregistered Voters_' . $timestamp . '.xlsx');   

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving unregistered voters',
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
                $query->select('id', 'voter', 'first_name', 'second_name', 'address', 'pobse', 'const','surname');
            }, 'voterNotes' => function($query) {
                $query->select('id', 'unregistered_voter_id', 'note'); 
            }])
            // ->where('user_id', $user->id)
            ->where('contacted', true)
            ->whereHas('voterNotes', function($query) {
                $query->whereColumn('unregistered_voter_id', 'unregistered_voters.id');
            });  


            $searchParams = [
              
                'first_name' => $request->first_name ?? null,
                'last_name' => $request->last_name ?? null,
                'phone_number' => $request->phone_number ?? null,
                'new_email' => $request->new_email ?? null,
                'new_address' => $request->new_address ?? null,
                'survey_id' => $request->survey_id ?? null,
                'user_id' => $request->user_id ?? null,
                'voter_id' => $request->voter_id ?? null,
                'under_age_25' => $request->under_age_25 ?? null
            ];

            $underAge25 = $request->input('under_age_25');
            if (isset($underAge25) && $underAge25=== 'yes') {
                $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, CAST(date_of_birth AS DATE))) < 25');
            }
            
            

            if (isset($request->start_date) && !empty($request->start_date)) {
                $query->where('created_at', '>=', $request->start_date . ' 00:00:00');
            }

            if (isset($request->end_date) && !empty($request->end_date)) {
                $query->where('created_at', '<=', $request->end_date . ' 23:59:59');
            }

            if (isset($request->first_name) && !empty($request->first_name)) {
                $query->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->first_name) . '%']);
            }
            if (isset($request->last_name) && !empty($request->last_name)) {
                $query->whereRaw('LOWER(last_name) LIKE ?', ['%' . strtolower($request->last_name) . '%']); 
            } 
             

            if (isset($request->phone_number) && !empty($request->phone_number)) {
                $query->where('phone_number', 'LIKE', '%' . $request->phone_number . '%');
            }

            if (isset($request->new_email) && !empty($request->new_email)) {
                $query->whereRaw('LOWER(new_email) LIKE ?', ['%' . strtolower($request->new_email) . '%']);
            }

            if (isset($request->new_address) && !empty($request->new_address)) {
                $query->whereRaw('LOWER(new_address) LIKE ?', ['%' . strtolower($request->new_address) . '%']);
            }

            if (isset($request->survey_id) && !empty($request->survey_id)) {
                $query->where('survey_id', 'LIKE', '%' . $request->survey_id . '%');
            }

            if (isset($request->user_id) && !empty($request->user_id)) {
                $query->where('user_id', 'LIKE', '%' . $request->user_id . '%');
            }

            if (isset($request->voter_id) && !empty($request->voter_id)) {
                $query->where('voter_id', 'LIKE', '%' . $request->voter_id . '%');
            }

            // Search in related voter fields
            if (isset($request->voter_first_name) && !empty($request->voter_first_name)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->voter_first_name) . '%']);
                });
            }

            if (isset($request->voter_second_name) && !empty($request->voter_second_name)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(second_name) LIKE ?', ['%' . strtolower($request->voter_second_name) . '%']);
                });
            }

            if (isset($request->voter_number) && !empty($request->voter_number)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->where('voter', 'LIKE', '%' . $request->voter_number . '%');
                });
            }

            if (isset($request->voter_address) && !empty($request->voter_address)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(address) LIKE ?', ['%' . strtolower($request->voter_address) . '%']);
                });
            }

            // Add filters
            if (isset($request->gender) && !empty($request->gender)) {
                $query->whereRaw('LOWER(gender) = ?', [strtolower($request->gender)]);
            }

            if (isset($request->date_from) && !empty($request->date_from) && isset($request->date_to) && !empty($request->date_to)) {
                $query->whereBetween('date_of_birth', [$request->date_from, $request->date_to]);
            }

            // Add sorting
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy('id', 'desc');

            $contactedVoters = $query->get();

            // Build search parameters object
            $searchParams = [
                    'first_name' => $request->first_name ?? null,
                    'last_name' => $request->last_name ?? null,
                'phone_number' => $request->phone_number ?? null,
                'new_email' => $request->new_email ?? null,
                'new_address' => $request->new_address ?? null,
                'survey_id' => $request->survey_id ?? null,
                'voter_first_name' => $request->voter_first_name ?? null,
                'voter_second_name' => $request->voter_second_name ?? null,
                'voter_number' => $request->voter_number ?? null,
                'voter_address' => $request->voter_address ?? null,
                'gender' => $request->gender ?? null,
                'date_from' => $request->date_from ?? null,
                'date_to' => $request->date_to ?? null
            ];

            $columns = array_map(function($column) {
                return strtolower(urldecode(trim($column)));
            }, explode(',', $_GET['columns']));
             
            $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
            return Excel::download(new UnregisteredVotersExport($contactedVoters, $request, $columns), 'Unregistered Contacted Voters_' . $timestamp . '.xlsx');  

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving contacted voters',
                'error' => $e->getMessage()
            ], 500);
        }

       
    }

    public function get_uncontacted_votersExport(Request $request)
    
    {
        try {
            

            $query = UnregisteredVoter::with(['voter' => function($query) {
                $query->select('id', 'voter', 'first_name', 'second_name', 'address', 'pobse', 'const','surname');
            }])->where('contacted', false); // Changed from 1 to true for PostgreSQL boolean 

            $underAge25 = $request->input('under_age_25');
            if (isset($underAge25) && $underAge25=== 'yes') {
                $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, CAST(date_of_birth AS DATE))) < 25');
            }
            
            

            // Add search filters
            if (isset($request->start_date) && !empty($request->start_date)) {
                $query->where('created_at', '>=', $request->start_date . ' 00:00:00');
            }

            if (isset($request->end_date) && !empty($request->end_date)) {
                $query->where('created_at', '<=', $request->end_date . ' 23:59:59');
            }
            
            if (isset($request->first_name) && !empty($request->first_name)) {
                $query->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->first_name) . '%']);
            }
            if (isset($request->last_name) && !empty($request->last_name)) {
                $query->whereRaw('LOWER(last_name) LIKE ?', ['%' . strtolower($request->last_name) . '%']);
            }

            if ($request->has('phone_number') && !empty($request->phone_number)) {
                $query->where('phone_number', 'LIKE', '%' . $request->phone_number . '%'); 
            }

            if ($request->has('new_email') && !empty($request->new_email)) {
                $query->whereRaw('LOWER(new_email) LIKE ?', ['%' . strtolower($request->new_email) . '%']);
            }

            if ($request->has('new_address') && !empty($request->new_address)) {
                $query->whereRaw('LOWER(new_address) LIKE ?', ['%' . strtolower($request->new_address) . '%']);
            }

            if ($request->has('survey_id') && !empty($request->survey_id)) {
                $query->where('survey_id', 'LIKE', '%' . $request->survey_id . '%');
            }

            // Search in related voter fields
            // if ($request->has('voter_first_name') && !empty($request->voter_first_name)) {
            //     $query->whereHas('voter', function($q) use ($request) {
            //         $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->voter_first_name) . '%']);
            //     });
            // }

            if (isset($request->voter_id) && !empty($request->voter_id)) {
                $query->where('voter_id', 'LIKE', '%' . $request->voter_id . '%');
            }

            if (isset($request->voter_first_name) && !empty($request->voter_first_name)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->voter_first_name) . '%']);
                });
            }

            if (isset($request->voter_second_name) && !empty($request->voter_second_name)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(second_name) LIKE ?', ['%' . strtolower($request->voter_second_name) . '%']);
                });
            }

            if (isset($request->voter_number) && !empty($request->voter_number)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->where('voter', 'LIKE', '%' . $request->voter_number . '%');
                });
            }

            if (isset($request->voter_address) && !empty($request->voter_address)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(address) LIKE ?', ['%' . strtolower($request->voter_address) . '%']);
                });
            }

            // Add filters
            if ($request->has('gender') && !empty($request->gender)) {
                $query->whereRaw('LOWER(gender) = ?', [strtolower($request->gender)]);
            }

            if ($request->has('date_from') && $request->has('date_to') && !empty($request->date_from) && !empty($request->date_to)) {
                $query->whereBetween('date_of_birth', [$request->date_from, $request->date_to]);
            }

            $contactedVoters = $query->orderBy('id', 'desc')->get();

            // Build search parameters object
            $searchParams = [
                'first_name' => $request->first_name ?? null,
                'last_name' => $request->last_name ?? null,
                'phone_number' => $request->phone_number ?? null,
                'new_email' => $request->new_email ?? null,
                'new_address' => $request->new_address ?? null,
                'survey_id' => $request->survey_id ?? null,
                'voter_first_name' => $request->voter_first_name ?? null,
                'voter_second_name' => $request->voter_second_name ?? null,
                'voter_number' => $request->voter_number ?? null,
                'voter_address' => $request->voter_address ?? null,
                'gender' => $request->gender ?? null,
                'date_from' => $request->date_from ?? null,
                'date_to' => $request->date_to ?? null
            ];

            $columns = array_map(function($column) {
                return strtolower(urldecode(trim($column)));
            }, explode(',', $_GET['columns']));
             
            $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
            return Excel::download(new UnregisteredVotersExport($contactedVoters, $request, $columns), 'Unregistered Uncontacted Voters_' . $timestamp . '.xlsx');  


        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving contacted voters',
                'error' => $e->getMessage()
            ], 500);
        }

       
    }


    public function getUnregisteredVotersDiffAddress(Request $request)
    {
        try {
            $user = Auth::user();
            $perPage = $request->input('per_page', 20); 

 

            // $query = UnregisteredVoter::with(['voter' => function($query) {
            //     $query->select('id', 'voter', 'first_name', 'second_name', 'address', 'pobse', 'const');
            // }])
            // ->leftJoin('constituencies', 'unregistered_voters.living_constituency', '=', 'constituencies.id')
            // ->select('unregistered_voters.*', 'constituencies.name as living_constituency_name');

            // $query->where('diff_address', 'yes');  
            
            
            $query = UnregisteredVoter::with(['voter' => function($query) {
                $query->select('id', 'voter', 'first_name', 'second_name', 'address', 'pobse', 'const');
            }])
            ->leftJoin('constituencies as lc', 'unregistered_voters.living_constituency', '=', 'lc.id')
            ->leftJoin('constituencies as sc', 'unregistered_voters.surveyer_constituency', '=', 'sc.id')
            ->select(
                'unregistered_voters.*',
                'lc.name as living_constituency_name',
                'sc.name as surveyer_constituency_name'
            )
            ->where('diff_address', 'yes');

            if (isset($request->surveyer_constituency) && !empty($request->surveyer_constituency)) {
                $query->whereHas('surveyerConstituency', function($q) use ($request) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->surveyer_constituency) . '%']);
                });
            } 

            // Search in livingConstituency
            if (isset($request->living_constituency) && !empty($request->living_constituency)) {
                $query->whereHas('livingConstituency', function($q) use ($request) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->living_constituency) . '%']);
                });
            }
        
            // Add search functionality
            // Apply individual search filters
            $underAge25 = $request->input('under_age_25');
            if (isset($underAge25) && $underAge25=== 'yes') {
                $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, CAST(date_of_birth AS DATE))) < 25');
            }
            

            if (isset($request->start_date) && !empty($request->start_date)) {
                $query->where('created_at', '>=', $request->start_date . ' 00:00:00');
            }

            if (isset($request->end_date) && !empty($request->end_date)) {
                $query->where('created_at', '<=', $request->end_date . ' 23:59:59');
            }

            if (isset($request->first_name) && !empty($request->first_name)) {
                $query->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->first_name) . '%']);
            }
            if (isset($request->last_name) && !empty($request->last_name)) {
                $query->whereRaw('LOWER(last_name) LIKE ?', ['%' . strtolower($request->last_name) . '%']);
            }

            if (isset($request->phone_number) && !empty($request->phone_number)) {
                $query->where('phone_number', 'LIKE', '%' . $request->phone_number . '%');
            }

            if (isset($request->new_email) && !empty($request->new_email)) {
                $query->whereRaw('LOWER(new_email) LIKE ?', ['%' . strtolower($request->new_email) . '%']);
            }

            if (isset($request->new_address) && !empty($request->new_address)) {
                $query->whereRaw('LOWER(new_address) LIKE ?', ['%' . strtolower($request->new_address) . '%']);
            }

            if (isset($request->survey_id) && !empty($request->survey_id)) {
                $query->where('survey_id', 'LIKE', '%' . $request->survey_id . '%');
            }

            if (isset($request->user_id) && !empty($request->user_id)) {
                $query->where('user_id', 'LIKE', '%' . $request->user_id . '%');
            }

            if (isset($request->voter_id) && !empty($request->voter_id)) {
                $query->where('voter_id', 'LIKE', '%' . $request->voter_id . '%');
            }

            // Search in related voter fields
            if (isset($request->voter_first_name) && !empty($request->voter_first_name)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->voter_first_name) . '%']);
                });
            }

            if (isset($request->voter_second_name) && !empty($request->voter_second_name)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(second_name) LIKE ?', ['%' . strtolower($request->voter_second_name) . '%']);
                });
            }

            if (isset($request->voter_number) && !empty($request->voter_number)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->where('voter', 'LIKE', '%' . $request->voter_number . '%');
                });
            }

            if (isset($request->voter_address) && !empty($request->voter_address)) {
                $query->whereHas('voter', function($q) use ($request) {
                    $q->whereRaw('LOWER(address) LIKE ?', ['%' . strtolower($request->voter_address) . '%']);
                });
            }

            // Add filters
            if (isset($request->gender) && !empty($request->gender)) {
                $query->whereRaw('LOWER(gender) = ?', [strtolower($request->gender)]);
            }

            if (isset($request->date_from) && !empty($request->date_from) && isset($request->date_to) && !empty($request->date_to)) {
                $query->whereBetween('date_of_birth', [$request->date_from, $request->date_to]);
            }


            if (isset($request->constituency_id) && !empty($request->constituency_id)) {
                $query->where('constituencies.id', $request->constituency_id);
            } 

            if (isset($request->constituency_name) && !empty($request->constituency_name)) {
                $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($request->constituency_name) . '%']);
            } 

            
                $query->orderBy('id', 'desc');
            

            // Get paginated results
            $unregisteredVoters = $query->get();

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
                'voter_address' => $request->search ?? null,
                'under_age_25' => $request->under_age_25 ?? null,
                'constituency_id' => $request->constituency_id ?? null,
                'constituency_name' => $request->constituency_name ?? null
            ]; 

            $columns = array_map(function($column) {
                return strtolower(urldecode(trim($column)));
            }, explode(',', $_GET['columns']));
              
             
            $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
            return Excel::download(new DiffAddressUnregisteredVotersExport($unregisteredVoters, $request, $columns), 'Unregistered Voters Diff Address_' . $timestamp . '.xlsx');  

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving unregistered voters',
                'error' => $e->getMessage()
            ], 500);
        }
    } 
    


}
