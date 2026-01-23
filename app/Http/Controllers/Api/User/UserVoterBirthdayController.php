<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Voter;
use App\Models\Survey;
use App\Models\VoterHistory;
use App\Models\Constituency;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserVoterBirthdayController extends Controller
{

    public function userBirthdayVotersContacted(Request $request, $id)
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
    public function userBirthdayVoters(Request $request)
    {
        try {
            // Get authenticated user's constituency_id and split into array
            $constituency_ids = explode(',', auth()->user()->constituency_id);

            if (empty($constituency_ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'User does not have an assigned constituency'
                ], 400);
            }

            $query = Voter::query()
                ->select('voters.*', 'constituencies.name as constituency_name')
                ->join('constituencies', 'voters.const', '=', 'constituencies.id')
                ->whereIn('voters.const', $constituency_ids)
                ->selectRaw('EXISTS (
                    SELECT 1 FROM surveys 
                    WHERE surveys.voter_id = voters.id
                ) as is_surveyed');

            // Get pagination parameters
            $perPage = $request->input('per_page', 10);

            // Add search filters
            $month = $request->input('month');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $firstName = $request->input('first_name');
            $secondName = $request->input('second_name');
            $surname = $request->input('surname');
            $address = $request->input('address');
            $voterId = $request->input('voter_id');
            $const = $request->input('const');
            $polling = $request->input('polling');
            $houseNumber = $request->input('house_number');
            $pobse = $request->input('pobse');
            $pobis = $request->input('pobis');
            $pobcn = $request->input('pobcn'); 

            $constituencyName = $request->input('constituency_name');
            $isSurveyed = $request->input('is_surveyed');
            $underAge25 = $request->input('under_age_25');
            $existsInDatabase = $request->input('exists_in_database');
            
            if (!empty($polling)) {
                $query->where('voters.polling', $polling);
            } 
            $query->where(function($q) use ($houseNumber, $address, $pobse, $pobis, $pobcn) {
                if ($houseNumber !== null && $houseNumber !== '') {
                    $q->whereRaw('LOWER(voters.house_number) = ?', [strtolower($houseNumber)]);
                }
                if ($address !== null && $address !== '') {
                    $q->WhereRaw('LOWER(voters.address) = ?', [strtolower($address)]);
                }
                if ($pobse !== null && $pobse !== '') {
                    $q->WhereRaw('LOWER(voters.pobse) = ?', [strtolower($pobse)]);
                }
                if ($pobis !== null && $pobis !== '') {
                    $q->WhereRaw('LOWER(voters.pobis) = ?', [strtolower($pobis)]);
                }
                if ($pobcn !== null && $pobcn !== '') {
                    $q->WhereRaw('LOWER(voters.pobcn) = ?', [strtolower($pobcn)]);
                }
            }); 
            if ($underAge25 === 'yes') {
                $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
            }

            if ($startDate && $endDate) {
                $query->whereRaw("TO_CHAR(dob, 'MM-DD') BETWEEN TO_CHAR(?::date, 'MM-DD') AND TO_CHAR(?::date, 'MM-DD')", 
                    [$startDate, $endDate]);
            } elseif ($startDate) {
                $query->whereRaw("TO_CHAR(dob, 'MM-DD') = TO_CHAR(?::date, 'MM-DD')", 
                    [$startDate]);
            } else {
                // If no dates specified, get current month birthdays
                $startOfMonth = now()->startOfMonth()->format('Y-m-d');
                $endOfMonth = now()->endOfMonth()->format('Y-m-d');
                $query->whereRaw("TO_CHAR(dob, 'MM-DD') BETWEEN TO_CHAR(?::date, 'MM-DD') AND TO_CHAR(?::date, 'MM-DD')", 
                    [$startOfMonth, $endOfMonth]);
            }

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

            if (!empty($voterId) && is_numeric($voterId)) {
                $query->where('voters.voter', $voterId);
            }

            if (!empty($const)) {
                $query->where('voters.const', $const);
            }

            if (!empty($constituencyName)) {
                $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
            }

            if (isset($isSurveyed)) {
                if ($isSurveyed === 'true' || $isSurveyed === true || $isSurveyed === 1) {
                    $query->whereExists(function ($query) {
                        $query->select(1)
                              ->from('surveys')
                              ->whereColumn('surveys.voter_id', 'voters.id');
                    });
                } else if ($isSurveyed === 'false' || $isSurveyed === false || $isSurveyed === 0) {
                    $query->whereNotExists(function ($query) {
                        $query->select(1)
                              ->from('surveys')
                              ->whereColumn('surveys.voter_id', 'voters.id');
                    });
                }
            }

            // Apply exists_in_database filter
            if ($existsInDatabase === 'true') {
                $query->where('voters.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $query->where('voters.exists_in_database', false);
            }
 
            $voters = $query
                ->selectRaw('CAST(EXTRACT(DAY FROM dob) AS INTEGER) as birth_day')
                ->orderByRaw('CAST(EXTRACT(DAY FROM dob) AS INTEGER) ASC')
                ->orderBy('id', 'ASC')
                ->paginate($perPage);

            // Define searchable parameters
            $searchableParameters = [
                'first_name' => 'First Name',
                'second_name' => 'Second Name', 
                'surname' => 'Surname',
                'address' => 'Address',
                'voter_id' => 'Voter ID',
                'const' => 'Constituency Id',
                'constituency_name' => 'Constituency Name',
                'is_surveyed' => 'Survey Status'
            ];

            return response()->json([
                'success' => true,
                'data' => $voters,
                'searchable_parameters' => $searchableParameters,
                'current_month' => now()->month,
                'search_month' => intval($month)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving voters list',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}