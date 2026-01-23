<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voter;
use App\Models\VoterHistory;
use Illuminate\Http\Request;
use App\Models\UnregisteredVoter;
use App\Models\Survey;
use Illuminate\Support\Facades\DB;
class VoterBirthdayController extends Controller 
{
    
    public function birthdayVotersContacted(Request $request, $id)
    {
        try {
            $voter = Voter::findOrFail($id);
            
            $voter->is_contacted = true;
            $voter->save();

            return response()->json([
                'success' => true,
                'message' => 'Voter marked as contacted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating voter contact status: ' . $e->getMessage()
            ], 500);
        }
    }

    public function birthdayVoters(Request $request)
    {   
         
        // Check if user is authenticated and has admin role
        if (!auth()->check() || auth()->user()->role->name !== 'Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }
        
        DB::statement("SET TIME ZONE 'America/Nassau'");

        $query = Voter::query(); 

        // $timezone = DB::select("SHOW timezone");
        // dd($timezone);

        $searchableFields = [
            'first_name' => 'First Name',
            'second_name' => 'Second Name',
            'surname' => 'Surname', 
            'address' => 'Address',
            'voter' => 'Voter ID',
            'const' => 'Constituency ID',
            'constituency_name' => 'Constituency Name'
        ];  
        
        $perPage = $request->input('per_page', 10);

        // Get search parameters
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $const = $request->input('const');
        $surname = $request->input('surname');
        $firstName = $request->input('first_name');
        $secondName = $request->input('second_name');
        $address = $request->input('address');
        $voterId = $request->input('voter');
        $constituencyName = $request->input('constituency_name');
        $constituencyId = $request->input('const');
        $underAge25 = $request->input('under_age_25');
        $isSurveyed = $request->input('is_surveyed');
        $polling = $request->input('polling');
        $houseNumber = $request->input('house_number');
        $pobse = $request->input('pobse');
        $pobis = $request->input('pobis');
        $pobcn = $request->input('pobcn');
        $existsInDatabase = $request->input('exists_in_database');
        

        // CRITICAL: Apply indexed filters FIRST to reduce dataset before expensive TO_CHAR operations
        // This dramatically improves performance by filtering rows early
        
        // Apply indexed filters first (these can use indexes efficiently)
        if (!empty($const)) {
            $query->where('voters.const', $const);
        }
        
        if (!empty($polling) && is_numeric($polling)) {
            $query->where('voters.polling', $polling);
        }
        
        // Note: exists_in_database is stored as INTEGER (int4) in database, not boolean
        // So we must use integer values (0 or 1) for comparison
        if ($existsInDatabase === 'true' || $existsInDatabase === '1' || $existsInDatabase === 1) {
            $query->where('voters.exists_in_database', 1);
        } elseif ($existsInDatabase === 'false' || $existsInDatabase === '0' || $existsInDatabase === 0) {
            $query->where('voters.exists_in_database', 0);
        }

        if (!empty($voterId) && is_numeric($voterId)) {
            $query->where('voters.voter', $voterId);
        }

        if ($underAge25 === 'yes') {
            $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
        }

        // Apply birthday filter (TO_CHAR is expensive, but applied after indexed filters reduce dataset)
        if ($startDate && $endDate) {
            $query->whereRaw("TO_CHAR(voters.dob, 'MM-DD') BETWEEN TO_CHAR(?::date, 'MM-DD') AND TO_CHAR(?::date, 'MM-DD')", 
                [$startDate, $endDate]);
        } elseif ($startDate) {
            $query->whereRaw("TO_CHAR(voters.dob, 'MM-DD') = TO_CHAR(?::date, 'MM-DD')", 
                [$startDate]);
        } else {
            // If no dates specified, get current month birthdays
            $startOfMonth = now()->startOfMonth()->format('Y-m-d');
            $endOfMonth = now()->endOfMonth()->format('Y-m-d');
            $query->whereRaw("TO_CHAR(voters.dob, 'MM-DD') BETWEEN TO_CHAR(?::date, 'MM-DD') AND TO_CHAR(?::date, 'MM-DD')", 
                [$startOfMonth, $endOfMonth]);  
        }

        // Apply text search filters (these are slower but applied after indexed filters)
        if (!empty($surname)) {
            $query->whereRaw('LOWER(voters.surname) LIKE ?', ['%' . strtolower($surname) . '%']);
        }

        if (!empty($firstName)) {
            $query->whereRaw('LOWER(voters.first_name) LIKE ?', ['%' . strtolower($firstName) . '%']);
        }

        if (!empty($secondName)) {
            $query->whereRaw('LOWER(voters.second_name) LIKE ?', ['%' . strtolower($secondName) . '%']);
        }

        if (!empty($address)) {
            $query->whereRaw('LOWER(voters.address) LIKE ?', ['%' . strtolower($address) . '%']);
        }

        $query->where(function($q) use ($houseNumber, $address, $pobse, $pobis, $pobcn) {
            if ($houseNumber !== null && $houseNumber !== '') {
                $q->whereRaw('LOWER(voters.house_number) = ?', [strtolower($houseNumber)]);
            }
            if ($address !== null && $address !== '') {
                $q->whereRaw('LOWER(voters.address) = ?', [strtolower($address)]);
            }
            if ($pobse !== null && $pobse !== '') {
                $q->whereRaw('LOWER(voters.pobse) = ?', [strtolower($pobse)]);
            }
            if ($pobis !== null && $pobis !== '') {
                $q->whereRaw('LOWER(voters.pobis) = ?', [strtolower($pobis)]);
            }
            if ($pobcn !== null && $pobcn !== '') {
                $q->whereRaw('LOWER(voters.pobcn) = ?', [strtolower($pobcn)]);
            }
        });

        if (!empty($constituencyName)) {
            $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
        }

        if (!empty($constituencyId)) {
            $query->where('voters.const', $constituencyId);
        }

        // Note: is_surveyed filter is handled by the LEFT JOIN with surveys subquery below
        // We'll filter after the join is applied
        $filterBySurveyed = isset($isSurveyed) ? (bool)$isSurveyed : null;

        // $voters = $query
        // ->select('voters.*', 'constituencies.name as constituency_name')
        // ->join('constituencies', 'voters.const', '=', 'constituencies.id')
        // ->selectRaw('EXTRACT(DAY FROM dob) as birth_day')
        // ->selectRaw('CASE WHEN EXISTS (
        //     SELECT 1 FROM surveys 
        //     WHERE surveys.voter_id = voters.id
        // ) THEN true ELSE false END as is_surveyed')
        // ->orderByRaw('EXTRACT(MONTH FROM dob), EXTRACT(DAY FROM dob), dob ASC')
        // ->paginate($perPage); 


        // Optimized: Join constituencies first (smaller table, indexed join)
        // Then join surveys subquery (applied after main filters reduce dataset)
        $query->join('constituencies', 'voters.const', '=', 'constituencies.id');

        // Apply is_surveyed filter early if specified (before expensive survey join)
        if ($filterBySurveyed !== null) {
            if ($filterBySurveyed) {
                $query->whereExists(function ($subquery) {
                    $subquery->select(DB::raw(1))
                        ->from('surveys')
                        ->whereColumn('surveys.voter_id', 'voters.id');
                });
            } else {
                $query->whereNotExists(function ($subquery) {
                    $subquery->select(DB::raw(1))
                        ->from('surveys')
                        ->whereColumn('surveys.voter_id', 'voters.id');
                });
            }
        }

        // Use simpler DISTINCT ON approach - less resource intensive than window functions
        // DISTINCT ON is optimized in PostgreSQL for this use case
        // Only join surveys if we need the phone data (always needed for is_surveyed field)
        $voters = $query
        ->leftJoin(DB::raw('
        (
            SELECT DISTINCT ON (voter_id) voter_id, cell_phone_code, cell_phone
            FROM surveys
            ORDER BY voter_id, created_at DESC
        ) as surveys
        '), 'voters.id', '=', 'surveys.voter_id')
        ->select(
            'voters.*',
            'constituencies.name as constituency_name',
            'surveys.cell_phone_code',
            'surveys.cell_phone',
            DB::raw('
                CASE WHEN surveys.voter_id IS NOT NULL
                THEN true ELSE false END as is_surveyed
            ')
        )
        ->orderByRaw('EXTRACT(MONTH FROM voters.dob), EXTRACT(DAY FROM voters.dob), voters.dob ASC')
        ->paginate($perPage);



        

        return response()->json([
            'success' => true,
            'data' => $voters,
            'searchable_fields' => $searchableFields,
            'start_date' => $startDate ?? now()->startOfMonth()->format('m-d'),
            'end_date' => $endDate ?? now()->endOfMonth()->format('m-d')

        ]);  
    }




}