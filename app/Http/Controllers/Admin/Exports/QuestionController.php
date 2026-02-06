<?php

namespace App\Http\Controllers\Admin\Exports;

use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\QuestionsExport;
use App\Exports\Reports1Export;
use App\Exports\Reports2Export;
use App\Exports\Reports3Export;
use App\Exports\Reports4Export;
use App\Exports\Reports5Export;
use App\Exports\VoterCardsReportExport;
use App\Models\Constituency;
use App\Models\Voter;
use App\Models\User;
use App\Models\Survey;
use App\Models\VoterCardImage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
class QuestionController extends Controller
{   
     
    public function export(Request $request)
    {   
        $query = Question::with('answers');
        
        if ($request->has('question') && !empty($request->question)) {
            $query->whereRaw('LOWER(question) LIKE ?', ['%' . strtolower($request->question) . '%']);
        }

        
        $questions = $query->orderBy('position', 'asc')->get();

        $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
        return Excel::download(new QuestionsExport($questions, $request), 'Questions List_' . $timestamp . '.xlsx');
    }


    public function getConstituencyReports(Request $request)  
    { 
        // First get all active parties
        $existsInDatabase = $request->input('exists_in_database');
        $parties = DB::table('parties')
            ->where('status', 'active')
            ->orderBy('position')
            ->get();

        // Build the select statement dynamically
        $selects = [
            'c.id as constituency_id',
            'c.name as constituency_name',
            DB::raw('COUNT(DISTINCT v.id) as total_voters'),
            DB::raw('COUNT(DISTINCT s.id) as surveyed_voters'),
            DB::raw('COUNT(DISTINCT v.id) - COUNT(DISTINCT s.id) as not_surveyed_voters'),
            DB::raw('ROUND((COUNT(DISTINCT s.id) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as surveyed_percentage'),
        ];

        // Add party-specific counts and percentages
        foreach ($parties as $party) {
            $partyName = $party->name;
            // Replace hyphens with underscores and make lowercase for column names
            $shortName = str_replace('-', '_', strtolower($party->short_name));
            
            // Add count for this party
            $selects[] = DB::raw("COUNT(DISTINCT CASE WHEN s.voting_for = '$partyName' THEN s.id END) as {$shortName}_count");
            
            // Add percentage for this party
            $selects[] = DB::raw("ROUND((COUNT(DISTINCT CASE WHEN s.voting_for = '$partyName' THEN s.id END) * 100.0) / NULLIF(COUNT(DISTINCT s.id), 0), 2) as {$shortName}_percentage");
        }

        // Add gender statistics
        $selects = array_merge($selects, [
            DB::raw("COUNT(DISTINCT CASE WHEN s.sex = 'Male' THEN s.id END) as total_male_surveyed"),
            DB::raw("COUNT(DISTINCT CASE WHEN s.sex = 'Female' THEN s.id END) as total_female_surveyed"), 
            DB::raw("COUNT(DISTINCT CASE WHEN s.sex IS NULL OR s.sex = '' THEN s.id END) as total_no_gender_surveyed"),
            DB::raw("ROUND((COUNT(DISTINCT CASE WHEN s.sex = 'Male' THEN s.id END) * 100.0) / NULLIF(COUNT(DISTINCT s.id), 0), 2) as male_percentage"),
            DB::raw("ROUND((COUNT(DISTINCT CASE WHEN s.sex = 'Female' THEN s.id END) * 100.0) / NULLIF(COUNT(DISTINCT s.id), 0), 2) as female_percentage")
        ]);

        $rawResults = DB::table('constituencies as c')
            ->leftJoin('voters as v', 'v.const', '=', 'c.id')
            ->leftJoin(DB::raw("(
                SELECT DISTINCT ON (voter_id) * 
                FROM surveys 
                ORDER BY voter_id, created_at DESC
            ) as s"), 'v.id', '=', 's.voter_id')
            ->select($selects);

        if ($existsInDatabase === 'true') {
            $rawResults->where('v.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $rawResults->where('v.exists_in_database', false);
        }

        if ($request->has('constituency_id')) {
            $rawResults->where('c.id', $request->constituency_id);
        }

        if ($request->has('constituency_name')) {
            $rawResults->whereRaw('LOWER(c.name) LIKE ?', ['%' . strtolower($request->constituency_name) . '%']);
        }

        $rawResults = $rawResults->groupBy('c.id', 'c.name')
            ->orderBy('c.id', 'asc')
            ->get();

        // Transform the results to include party data as a map
        $results = $rawResults->map(function($row) use ($parties) {
            $transformedRow = [
                'constituency_id' => $row->constituency_id,
                'constituency_name' => $row->constituency_name,
                'total_voters' => $row->total_voters,
                'surveyed_voters' => $row->surveyed_voters,
                'not_surveyed_voters' => $row->not_surveyed_voters,
                'surveyed_percentage' => $row->surveyed_percentage,
                'parties' => [],
                'gender' => [
                    'male' => [
                        'count' => $row->total_male_surveyed,
                        'percentage' => $row->male_percentage
                    ],
                    'female' => [
                        'count' => $row->total_female_surveyed,
                        'percentage' => $row->female_percentage
                    ],
                    'unspecified' => [
                        'count' => $row->total_no_gender_surveyed,
                        'percentage' => 100 - ($row->male_percentage + $row->female_percentage)
                    ]
                ]
            ];

            // Add party data as a map
            foreach ($parties as $party) {
                $shortName = str_replace('-', '_', strtolower($party->short_name));
                $countKey = "{$shortName}_count";
                $percentageKey = "{$shortName}_percentage";
                
                $transformedRow['parties'][$party->short_name] = [
                    'count' => $row->$countKey,
                    'percentage' => $row->$percentageKey
                ];
            }

            return $transformedRow;
        });

        $columns = array_map(function($column) {
            return strtolower(urldecode(trim($column)));
        }, explode(',', $_GET['columns']));

        $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
        return Excel::download(new Reports1Export($results, $columns, $request), 'Constituency Reports_' . $timestamp . '.xlsx'); 

        // dd( $results,$request->columns);  
    }



    public function getConstituencyReport4(Request $request)
    {
        // Get party names using EXACT same method as getVotersInSurvey
        $fnmParty = DB::table('parties')->whereRaw('LOWER(name) = ?', ['free national movement'])->first();
        $plpParty = DB::table('parties')->whereRaw('LOWER(name) = ?', ['progressive liberal party'])->first();
        $coiParty = DB::table('parties')->whereRaw('LOWER(name) = ?', ['coalition of independents'])->first();
        
        $fnmName = $fnmParty ? $fnmParty->name : 'Free National Movement';
        $plpName = $plpParty ? $plpParty->name : 'Progressive Liberal Party';
        $coiName = $coiParty ? $coiParty->name : 'Coalition of Independents';

        // Build query EXACTLY like getVotersInSurvey - using INNER JOIN with raw subquery
        $query = DB::table('voters as v')
            ->leftJoin('constituencies as c', 'v.const', '=', 'c.id')
            ->join(DB::raw("(
                SELECT DISTINCT ON (voter_id) 
                    voter_id,
                    id,
                    created_at,
                    user_id,
                    located,
                    voting_decision,
                    voting_for,
                    is_died,
                    died_date,
                    challenge
                FROM surveys 
                ORDER BY voter_id, id DESC
            ) as ls"), 'ls.voter_id', '=', 'v.id');

        // Get ALL filter parameters - SAME as getVotersInSurvey
        $existsInDatabase = $request->input('exists_in_database');
        $underAge25 = $request->input('under_age_25');
        $surname = $request->input('surname');
        $firstName = $request->input('first_name');
        $secondName = $request->input('second_name');
        $voterId = $request->input('voter');
        $houseNumber = $request->input('house_number'); 
        $address = $request->input('address');
        $pobse = $request->input('pobse');
        $pobis = $request->input('pobis');
        $pobcn = $request->input('pobcn');
        $constituencyId = $request->input('const') ?? $request->input('constituency_id');
        $constituencyName = $request->input('constituency_name');
        $polling = $request->input('polling');
        $located = $request->input('located');
        $voting_decision = $request->input('voting_decision');
        $voting_for = $request->input('voting_for');
        $is_died = $request->input('is_died');
        $died_date = $request->input('died_date');
        $challenge = $request->input('challenge');
        $user_id = $request->input('user_id');
        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');

        // Apply challenge filter
        if ($challenge === 'true') {
            $query->whereRaw('ls.challenge IS TRUE');
        } elseif ($challenge === 'false') {
            $query->whereRaw('ls.challenge IS FALSE');
        }

        // Apply exists_in_database filter
        if ($existsInDatabase === 'true') {
            $query->where('v.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('v.exists_in_database', false);
        }

        // Apply voting_for filter - SAME logic as getVotersInSurvey
        if ($voting_for !== null && $voting_for !== '') {
            if (is_numeric($voting_for)) {
                $get_party = DB::table('parties')->where('id', $voting_for)->first();
            } else {
                $get_party = DB::table('parties')->whereRaw('LOWER(name) = ?', [strtolower($voting_for)])->first();
            }
            if ($get_party) {
                $query->where('ls.voting_for', $get_party->name);
            }
        }

        // Apply is_died filter
        if ($is_died !== null && $is_died !== '') {
            $query->where('ls.is_died', $is_died);
        }

        // Apply died_date filter
        if ($died_date !== null && $died_date !== '') {
            $query->where('ls.died_date', $died_date);
        }

        // Apply voting_decision filter
        if (!empty($voting_decision)) {
            $query->where('ls.voting_decision', $voting_decision);
        }

        // Apply located filter
        if (!empty($located)) {
            $query->whereRaw('LOWER(ls.located) = ?', [strtolower($located)]);
        }

        // Apply polling filter
        if (!empty($polling) && is_numeric($polling)) {
            $query->where('v.polling', $polling);
        }

        // Apply under_age_25 filter
        if ($underAge25 === 'yes') {
            $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, v.dob)) < 25');
        }

        // Apply user_id filter
        if (!empty($user_id)) {
            $query->where('ls.user_id', $user_id);
        }

        // Apply date range filters
        if (!empty($start_date)) {
            $query->where('ls.created_at', '>=', $start_date . ' 00:00:00');
        }
        if (!empty($end_date)) {
            $query->where('ls.created_at', '<=', $end_date . ' 23:59:59');
        }

        // Apply name filters
        if (!empty($surname)) {
            $query->whereRaw('LOWER(v.surname) LIKE ?', ['%' . strtolower($surname) . '%']);
        }
        if (!empty($firstName)) {
            $query->whereRaw('LOWER(v.first_name) LIKE ?', ['%' . strtolower($firstName) . '%']);
        }
        if (!empty($secondName)) {
            $query->whereRaw('LOWER(v.second_name) LIKE ?', ['%' . strtolower($secondName) . '%']);
        }

        // Apply address filters
        $query->where(function($q) use ($houseNumber, $address, $pobse, $pobis, $pobcn) {
            if ($houseNumber !== null && $houseNumber !== '') {
                $q->whereRaw('LOWER(v.house_number) = ?', [strtolower($houseNumber)]);
            }
            if ($address !== null && $address !== '') {
                $q->whereRaw('LOWER(v.address) = ?', [strtolower($address)]);
            }
            if ($pobse !== null && $pobse !== '') {
                $q->whereRaw('LOWER(v.pobse) = ?', [strtolower($pobse)]);
            }
            if ($pobis !== null && $pobis !== '') {
                $q->whereRaw('LOWER(v.pobis) = ?', [strtolower($pobis)]);
            }
            if ($pobcn !== null && $pobcn !== '') {
                $q->whereRaw('LOWER(v.pobcn) = ?', [strtolower($pobcn)]);
            }
        });

        // Apply voter ID filter
        if (!empty($voterId) && is_numeric($voterId)) {
            $query->where('v.voter', $voterId);
        }

        // Apply constituency name filter
        if (!empty($constituencyName)) {
            $query->whereRaw('LOWER(c.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
        }

        // Apply constituency ID filter
        if (!empty($constituencyId) && is_numeric($constituencyId)) {
            $query->where('v.const', $constituencyId);
        }

        // Subquery: total voters per polling (all voters in that polling with same voter/constituency filters)
        $totalVotersSubquery = DB::table('voters as v2')
            ->leftJoin('constituencies as c2', 'v2.const', '=', 'c2.id')
            ->select('v2.polling', DB::raw('COUNT(DISTINCT v2.id) as total_voters'))
            ->groupBy('v2.polling');
        // Apply same voter-level and constituency-level filters (no survey filters)
        if ($existsInDatabase === 'true') {
            $totalVotersSubquery->where('v2.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $totalVotersSubquery->where('v2.exists_in_database', false);
        }
        if (!empty($constituencyId) && is_numeric($constituencyId)) {
            $totalVotersSubquery->where('v2.const', $constituencyId);
        }
        if (!empty($constituencyName)) {
            $totalVotersSubquery->whereRaw('LOWER(c2.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
        }
        if (!empty($polling) && is_numeric($polling)) {
            $totalVotersSubquery->where('v2.polling', $polling);
        }
        if ($underAge25 === 'yes') {
            $totalVotersSubquery->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, v2.dob)) < 25');
        }
        if (!empty($surname)) {
            $totalVotersSubquery->whereRaw('LOWER(v2.surname) LIKE ?', ['%' . strtolower($surname) . '%']);
        }
        if (!empty($firstName)) {
            $totalVotersSubquery->whereRaw('LOWER(v2.first_name) LIKE ?', ['%' . strtolower($firstName) . '%']);
        }
        if (!empty($secondName)) {
            $totalVotersSubquery->whereRaw('LOWER(v2.second_name) LIKE ?', ['%' . strtolower($secondName) . '%']);
        }
        if (!empty($voterId) && is_numeric($voterId)) {
            $totalVotersSubquery->where('v2.voter', $voterId);
        }
        $totalVotersSubquery->where(function ($q) use ($houseNumber, $address, $pobse, $pobis, $pobcn) {
            if ($houseNumber !== null && $houseNumber !== '') {
                $q->whereRaw('LOWER(v2.house_number) = ?', [strtolower($houseNumber)]);
            }
            if ($address !== null && $address !== '') {
                $q->whereRaw('LOWER(v2.address) = ?', [strtolower($address)]);
            }
            if ($pobse !== null && $pobse !== '') {
                $q->whereRaw('LOWER(v2.pobse) = ?', [strtolower($pobse)]);
            }
            if ($pobis !== null && $pobis !== '') {
                $q->whereRaw('LOWER(v2.pobis) = ?', [strtolower($pobis)]);
            }
            if ($pobcn !== null && $pobcn !== '') {
                $q->whereRaw('LOWER(v2.pobcn) = ?', [strtolower($pobcn)]);
            }
        });

        $query->leftJoinSub($totalVotersSubquery, 'tv', 'v.polling', '=', 'tv.polling');

        // Select aggregated data by polling division (NO pagination for Excel - get ALL rows)
        $results = $query->select(
            'v.polling as polling_division',
            // Total voters in this polling (all voters matching filters)
            DB::raw('COALESCE(MAX(tv.total_voters), COUNT(DISTINCT v.id)) as total_voters'),
            DB::raw("COUNT(DISTINCT CASE WHEN ls.voting_for = '$fnmName' THEN v.id END) as fnm_count"),
            DB::raw("COUNT(DISTINCT CASE WHEN ls.voting_for = '$plpName' THEN v.id END) as plp_count"),
            DB::raw("COUNT(DISTINCT CASE WHEN ls.voting_for = '$coiName' THEN v.id END) as coi_count"),
            DB::raw("COUNT(DISTINCT CASE WHEN ls.voting_for IS NOT NULL AND ls.voting_for NOT IN ('$fnmName', '$plpName', '$coiName') THEN v.id END) as other_count"),
            DB::raw("COUNT(DISTINCT CASE WHEN ls.voting_for IS NULL THEN v.id END) as no_vote_count"),
            DB::raw("COUNT(DISTINCT v.id) as total_count"),
            DB::raw("ROUND((COUNT(DISTINCT CASE WHEN ls.voting_for = '$fnmName' THEN v.id END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as fnm_percentage"),
            DB::raw("ROUND((COUNT(DISTINCT CASE WHEN ls.voting_for = '$plpName' THEN v.id END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as plp_percentage"),
            DB::raw("ROUND((COUNT(DISTINCT CASE WHEN ls.voting_for = '$coiName' THEN v.id END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as coi_percentage"),
            DB::raw("ROUND((COUNT(DISTINCT CASE WHEN ls.voting_for IS NOT NULL AND ls.voting_for NOT IN ('$fnmName', '$plpName', '$coiName') THEN v.id END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as other_percentage"),
            DB::raw("ROUND((COUNT(DISTINCT CASE WHEN ls.voting_for IS NULL THEN v.id END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as no_vote_percentage")
        )
        ->groupBy('v.polling')
        ->orderBy('v.polling', 'asc')
        ->get();  // Get ALL rows for Excel (no pagination)

        // Transform: add total_party_count and ensure total_voters/total_count are integers
        $results->transform(function ($item) {
            $item->total_voters = (int) ($item->total_voters ?? $item->total_count ?? 0);
            $item->total_count = (int) $item->total_count;
            $item->total_party_count = (int) $item->fnm_count + (int) $item->plp_count + (int) $item->coi_count + (int) $item->other_count;
            return $item;
        });

        $columns = array_map(function($column) {
            return strtolower(urldecode(trim($column)));
        }, explode(',', $_GET['columns'] ?? ''));
   
        $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
        return Excel::download(new Reports4Export($results, $request, $columns), 'Election Projections Report_' . $timestamp . '.xlsx'); 
    }


    



    public function getConstituencyReport1(Request $request)
    {
            // $results = DB::table('constituencies as c')
            //     ->leftJoin('voters as v', 'v.const', '=', 'c.id')
            //     ->leftJoin('surveys as s', 's.voter_id', '=', 'v.id')
            //     ->select(
            //         'c.id as constituency_id',
            //         'c.name as constituency_name',
            //         DB::raw('COUNT(DISTINCT s.id) as total_surveyed'),
            //         DB::raw('COUNT(DISTINCT v.id) as total_voters'),
            //         DB::raw('ROUND((COUNT(DISTINCT s.id) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as percentage')
            //     )
            //     ->groupBy('c.id', 'c.name')
            //     ->orderBy('c.name');

            // if ($request->has('constituency_id')) {
            //     $results->where('c.id', $request->constituency_id);
            // }

            // if ($request->has('constituency_name')) {
            //     $results->whereRaw('LOWER(c.name) LIKE ?', ['%' . strtolower($request->constituency_name) . '%']);
            // }

            $existsInDatabase = $request->input('exists_in_database');
            $query = DB::table('constituencies as c')
            ->leftJoin('voters as v', 'v.const', '=', 'c.id')
            ->leftJoin(DB::raw("(
                SELECT DISTINCT ON (voter_id) * 
                FROM surveys 
                ORDER BY voter_id, created_at DESC
            ) as s"), 'v.id', '=', 's.voter_id');

        if ($request->has('constituency_id')) {
            $query->where('c.id', $request->constituency_id);
        }

        if ($request->has('constituency_name')) {
            $query->whereRaw('LOWER(c.name) LIKE ?', ['%' . strtolower($request->constituency_name) . '%']);
        }
        if ($existsInDatabase === 'true') {
            $query->where('v.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('v.exists_in_database', false);
        }

        $results = $query->select(
                'c.id as constituency_id',
                'c.name as constituency_name', 
                DB::raw('COUNT(DISTINCT s.id) as total_surveyed'),
                DB::raw('COUNT(DISTINCT v.id) as total_voters'),
                DB::raw('ROUND((COUNT(DISTINCT s.id) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as percentage')
            )
            ->groupBy('c.id', 'c.name')
            ->orderBy('c.id', 'asc');
             


            $results = $results->get();

            $columns = array_map(function($column) {
                return strtolower(urldecode(trim($column)));
            }, explode(',', $_GET['columns']));
       
            $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
            return Excel::download(new Reports2Export($results, $request, $columns), 'Constituency Reports_' . $timestamp . '.xlsx'); 
            
        }


        public function getConstituencyReport2(Request $request) 
        {
            $existsInDatabase = $request->input('exists_in_database');
            $parties = DB::table('parties')
                ->where('status', 'active')
                ->orderBy('position')
                ->get();
        
            // Build the select statement dynamically
            $selects = [
                'c.id as constituency_id',
                'c.name as constituency_name',
                DB::raw('COUNT(DISTINCT v.id) as total_voters'),
                DB::raw('COUNT(DISTINCT s.id) as surveyed_voters'),
                DB::raw('COUNT(DISTINCT v.id) - COUNT(DISTINCT s.id) as not_surveyed_voters'),
                DB::raw('ROUND((COUNT(DISTINCT s.id) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as surveyed_percentage'),
            ];
        
            // Add party-specific counts and percentages
            foreach ($parties as $party) {
                $partyName = $party->name;
                $shortName = str_replace('-', '_', strtolower($party->short_name));
                $selects[] = DB::raw("COUNT(DISTINCT CASE WHEN s.voting_for = '$partyName' THEN s.id END) as {$shortName}_count");
                $selects[] = DB::raw("ROUND((COUNT(DISTINCT CASE WHEN s.voting_for = '$partyName' THEN s.id END) * 100.0) / NULLIF(COUNT(DISTINCT s.id), 0), 2) as {$shortName}_percentage");
            }
        
            // Add gender statistics
            $selects = array_merge($selects, [
                DB::raw("COUNT(DISTINCT CASE WHEN s.sex = 'Male' THEN s.id END) as total_male_surveyed"),
                DB::raw("COUNT(DISTINCT CASE WHEN s.sex = 'Female' THEN s.id END) as total_female_surveyed"),
                DB::raw("COUNT(DISTINCT CASE WHEN s.sex IS NULL OR s.sex = '' THEN s.id END) as total_no_gender_surveyed"),
                DB::raw("ROUND((COUNT(DISTINCT CASE WHEN s.sex = 'Male' THEN s.id END) * 100.0) / NULLIF(COUNT(DISTINCT s.id), 0), 2) as male_percentage"),
                DB::raw("ROUND((COUNT(DISTINCT CASE WHEN s.sex = 'Female' THEN s.id END) * 100.0) / NULLIF(COUNT(DISTINCT s.id), 0), 2) as female_percentage"),
            ]);
        
            // Start building the query
            $query = DB::table('constituencies as c')
                ->leftJoin('voters as v', 'v.const', '=', 'c.id')
                // ->leftJoin('surveys as s', 's.voter_id', '=', 'v.id')
                ->leftJoin(DB::raw("(
                    SELECT DISTINCT ON (voter_id) * 
                    FROM surveys 
                    ORDER BY voter_id, created_at DESC
                ) as s"), 'v.id', '=', 's.voter_id')
                ->select($selects)
                ->groupBy('c.id', 'c.name')
                ->orderBy('c.id', 'asc');
        
            if ($existsInDatabase === 'true') {
                $query->where('v.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $query->where('v.exists_in_database', false);
            }
            // Apply search filters
            if ($request->has('constituency_id')) {
                $query->where('c.id', $request->constituency_id);
            }
        
            if ($request->has('constituency_name')) {
                $query->whereRaw('LOWER(c.name) LIKE ?', ['%' . strtolower($request->constituency_name) . '%']);
            }
        
            // Execute the query
            $rawResults = $query->get();
        
            // Transform the results to include party and gender data
            $results = $rawResults->map(function ($row) use ($parties) {
                $transformedRow = [
                    'constituency_id' => $row->constituency_id,
                    'constituency_name' => $row->constituency_name,
                    'total_voters' => $row->total_voters,
                    'surveyed_voters' => $row->surveyed_voters,
                    'not_surveyed_voters' => $row->not_surveyed_voters,
                    'surveyed_percentage' => $row->surveyed_percentage,
                    'parties' => [],
                    'gender' => [
                        'male' => [
                            'count' => $row->total_male_surveyed,
                            'percentage' => $row->male_percentage
                        ],
                        'female' => [
                            'count' => $row->total_female_surveyed,
                            'percentage' => $row->female_percentage
                        ],
                        'unspecified' => [
                            'count' => $row->total_no_gender_surveyed,
                            'percentage' => 100 - ($row->male_percentage + $row->female_percentage)
                        ]
                    ]
                ];
        
                foreach ($parties as $party) {
                    $shortName = str_replace('-', '_', strtolower($party->short_name));
                    $transformedRow['parties'][$party->short_name] = [
                        'count' => $row->{$shortName . '_count'},
                        'percentage' => $row->{$shortName . '_percentage'}
                    ];
                }
        
                return $transformedRow;
            });
        
            // Get column names from query string
            $columns = array_map(function ($column) {
                return strtolower(urldecode(trim($column)));
            }, explode(',', $_GET['columns']));
        
            // Export to Excel
            $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
            return Excel::download(new Reports3Export($results, $request, $columns), 'Constituency Reports_' . $timestamp . '.xlsx');
        }

        /**
         * Report 5: Same columns as Report 2 but grouped by polling division (polling-based). Export.
         */
        public function getConstituencyReport5(Request $request)
        {
            $existsInDatabase = $request->input('exists_in_database');
            $parties = DB::table('parties')
                ->where('status', 'active')
                ->orderBy('position')
                ->get();

            $query = DB::table('voters as v')
                ->leftJoin('constituencies as c', 'v.const', '=', 'c.id')
                ->leftJoin(DB::raw("(
                    SELECT DISTINCT ON (voter_id) *
                    FROM surveys
                    ORDER BY voter_id, created_at DESC
                ) as s"), 'v.id', '=', 's.voter_id');

            if ($existsInDatabase === 'true') {
                $query->where('v.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $query->where('v.exists_in_database', false);
            }
            if ($request->has('constituency_id')) {
                $query->where('v.const', $request->constituency_id);
            }
            if ($request->has('constituency_name')) {
                $query->whereRaw('LOWER(c.name) LIKE ?', ['%' . strtolower($request->constituency_name) . '%']);
            }

            $selects = [
                'v.polling as polling_division',
                'c.id as constituency_id',
                'c.name as constituency_name',
                DB::raw('COUNT(DISTINCT v.id) as total_voters'),
                DB::raw('COUNT(DISTINCT s.id) as surveyed_voters'),
                DB::raw('COUNT(DISTINCT v.id) - COUNT(DISTINCT s.id) as not_surveyed_voters'),
                DB::raw('ROUND((COUNT(DISTINCT s.id) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as surveyed_percentage'),
            ];
            foreach ($parties as $party) {
                $partyName = $party->name;
                $shortName = str_replace('-', '_', strtolower($party->short_name));
                $selects[] = DB::raw("COUNT(DISTINCT CASE WHEN s.voting_for = '$partyName' THEN s.id END) as {$shortName}_count");
                $selects[] = DB::raw("ROUND((COUNT(DISTINCT CASE WHEN s.voting_for = '$partyName' THEN s.id END) * 100.0) / NULLIF(COUNT(DISTINCT s.id), 0), 2) as {$shortName}_percentage");
            }
            $selects = array_merge($selects, [
                DB::raw("COUNT(DISTINCT CASE WHEN s.sex = 'Male' THEN s.id END) as total_male_surveyed"),
                DB::raw("COUNT(DISTINCT CASE WHEN s.sex = 'Female' THEN s.id END) as total_female_surveyed"),
                DB::raw("COUNT(DISTINCT CASE WHEN s.sex IS NULL OR s.sex = '' THEN s.id END) as total_no_gender_surveyed"),
                DB::raw("ROUND((COUNT(DISTINCT CASE WHEN s.sex = 'Male' THEN s.id END) * 100.0) / NULLIF(COUNT(DISTINCT s.id), 0), 2) as male_percentage"),
                DB::raw("ROUND((COUNT(DISTINCT CASE WHEN s.sex = 'Female' THEN s.id END) * 100.0) / NULLIF(COUNT(DISTINCT s.id), 0), 2) as female_percentage"),
            ]);

            $rawResults = $query->select($selects)
                ->groupBy('v.polling', 'c.id', 'c.name')
                ->orderBy('c.name', 'asc')
                ->orderBy('v.polling', 'asc')
                ->get();

            $results = $rawResults->map(function ($row) use ($parties) {
                $transformedRow = [
                    'polling_division' => $row->polling_division,
                    'constituency_id' => $row->constituency_id,
                    'constituency_name' => $row->constituency_name,
                    'total_voters' => $row->total_voters,
                    'surveyed_voters' => $row->surveyed_voters,
                    'not_surveyed_voters' => $row->not_surveyed_voters,
                    'surveyed_percentage' => $row->surveyed_percentage,
                    'parties' => [],
                    'gender' => [
                        'male' => ['count' => $row->total_male_surveyed, 'percentage' => $row->male_percentage],
                        'female' => ['count' => $row->total_female_surveyed, 'percentage' => $row->female_percentage],
                        'unspecified' => [
                            'count' => $row->total_no_gender_surveyed,
                            'percentage' => 100 - ($row->male_percentage + $row->female_percentage)
                        ]
                    ]
                ];
                foreach ($parties as $party) {
                    $shortName = str_replace('-', '_', strtolower($party->short_name));
                    $countKey = "{$shortName}_count";
                    $percentageKey = "{$shortName}_percentage";
                    $transformedRow['parties'][$party->short_name] = [
                        'count' => $row->$countKey,
                        'percentage' => $row->$percentageKey
                    ];
                }
                return $transformedRow;
            });

            $columns = array_map(function ($column) {
                return strtolower(urldecode(trim($column)));
            }, explode(',', $_GET['columns'] ?? ''));

            $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
            return Excel::download(new Reports5Export($results, $request, $columns, $parties), 'Polling Reports_' . $timestamp . '.xlsx');
        }

        /**
         * Report 5 PDF Export: Same as Excel but PDF format.
         */
        public function getConstituencyReport5Pdf(Request $request)
        {
            $existsInDatabase = $request->input('exists_in_database');
            $parties = DB::table('parties')
                ->where('status', 'active')
                ->orderBy('position')
                ->get();

            $query = DB::table('voters as v')
                ->leftJoin('constituencies as c', 'v.const', '=', 'c.id')
                ->leftJoin(DB::raw("(
                    SELECT DISTINCT ON (voter_id) *
                    FROM surveys
                    ORDER BY voter_id, created_at DESC
                ) as s"), 'v.id', '=', 's.voter_id');

            if ($existsInDatabase === 'true') {
                $query->where('v.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $query->where('v.exists_in_database', false);
            }
            if ($request->has('constituency_id')) {
                $query->where('v.const', $request->constituency_id);
            }
            if ($request->has('constituency_name')) {
                $query->whereRaw('LOWER(c.name) LIKE ?', ['%' . strtolower($request->constituency_name) . '%']);
            }

            $selects = [
                'v.polling as polling_division',
                'c.id as constituency_id',
                'c.name as constituency_name',
                DB::raw('COUNT(DISTINCT v.id) as total_voters'),
                DB::raw('COUNT(DISTINCT s.id) as surveyed_voters'),
                DB::raw('COUNT(DISTINCT v.id) - COUNT(DISTINCT s.id) as not_surveyed_voters'),
                DB::raw('ROUND((COUNT(DISTINCT s.id) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as surveyed_percentage'),
            ];
            foreach ($parties as $party) {
                $partyName = $party->name;
                $shortName = str_replace('-', '_', strtolower($party->short_name));
                $selects[] = DB::raw("COUNT(DISTINCT CASE WHEN s.voting_for = '$partyName' THEN s.id END) as {$shortName}_count");
                $selects[] = DB::raw("ROUND((COUNT(DISTINCT CASE WHEN s.voting_for = '$partyName' THEN s.id END) * 100.0) / NULLIF(COUNT(DISTINCT s.id), 0), 2) as {$shortName}_percentage");
            }
            $selects = array_merge($selects, [
                DB::raw("COUNT(DISTINCT CASE WHEN s.sex = 'Male' THEN s.id END) as total_male_surveyed"),
                DB::raw("COUNT(DISTINCT CASE WHEN s.sex = 'Female' THEN s.id END) as total_female_surveyed"),
                DB::raw("COUNT(DISTINCT CASE WHEN s.sex IS NULL OR s.sex = '' THEN s.id END) as total_no_gender_surveyed"),
                DB::raw("ROUND((COUNT(DISTINCT CASE WHEN s.sex = 'Male' THEN s.id END) * 100.0) / NULLIF(COUNT(DISTINCT s.id), 0), 2) as male_percentage"),
                DB::raw("ROUND((COUNT(DISTINCT CASE WHEN s.sex = 'Female' THEN s.id END) * 100.0) / NULLIF(COUNT(DISTINCT s.id), 0), 2) as female_percentage"),
            ]);

            $rawResults = $query->select($selects)
                ->groupBy('v.polling', 'c.id', 'c.name')
                ->orderBy('c.name', 'asc')
                ->orderBy('v.polling', 'asc')
                ->get();

            $results = $rawResults->map(function ($row) use ($parties) {
                $transformedRow = [
                    'polling_division' => $row->polling_division,
                    'constituency_id' => $row->constituency_id,
                    'constituency_name' => $row->constituency_name,
                    'total_voters' => $row->total_voters,
                    'surveyed_voters' => $row->surveyed_voters,
                    'not_surveyed_voters' => $row->not_surveyed_voters,
                    'surveyed_percentage' => $row->surveyed_percentage,
                    'parties' => [],
                    'gender' => [
                        'male' => ['count' => $row->total_male_surveyed, 'percentage' => $row->male_percentage],
                        'female' => ['count' => $row->total_female_surveyed, 'percentage' => $row->female_percentage],
                        'unspecified' => [
                            'count' => $row->total_no_gender_surveyed,
                            'percentage' => 100 - ($row->male_percentage + $row->female_percentage)
                        ]
                    ]
                ];
                foreach ($parties as $party) {
                    $shortName = str_replace('-', '_', strtolower($party->short_name));
                    $countKey = "{$shortName}_count";
                    $percentageKey = "{$shortName}_percentage";
                    $transformedRow['parties'][$party->short_name] = [
                        'count' => $row->$countKey,
                        'percentage' => $row->$percentageKey
                    ];
                }
                return $transformedRow;
            });

            // Add totals row
            $totalVotersSum = 0;
            $surveyedSum = 0;
            $notSurveyedSum = 0;
            foreach ($results as $row) {
                $totalVotersSum += (int) ($row['total_voters'] ?? 0);
                $surveyedSum += (int) ($row['surveyed_voters'] ?? 0);
                $notSurveyedSum += (int) ($row['not_surveyed_voters'] ?? 0);
            }
            $totalsRow = [
                'polling_division' => 'TOTALS',
                'constituency_id' => '',
                'constituency_name' => '',
                'total_voters' => $totalVotersSum,
                'surveyed_voters' => $surveyedSum,
                'not_surveyed_voters' => $notSurveyedSum,
                'surveyed_percentage' => $totalVotersSum > 0 ? round(($surveyedSum * 100.0) / $totalVotersSum, 2) : 0,
                'parties' => [],
            ];
            $results->push($totalsRow);

            $columns = array_map(function ($column) {
                return strtolower(urldecode(trim($column)));
            }, explode(',', $_GET['columns'] ?? ''));

            // If no columns provided, use default columns
            if (empty($columns) || (count($columns) === 1 && empty($columns[0]))) {
                $columns = ['polling division', 'constituency id', 'constituency name', 'total voters', 'surveyed voters', 'not surveyed', 'surveyed %'];
                // Add party percentage columns
                foreach ($parties as $party) {
                    $columns[] = $party->short_name . ' %';
                }
            }

            // Convert Collection to array for Blade view
            $resultsArray = $results->map(function ($row) {
                return (array) $row;
            })->toArray();

            $pdf = Pdf::loadView('pdf.polling-report', [
                'results' => $resultsArray,
                'columns' => $columns,
                'constituency_id' => $request->constituency_id ?? null,
                'constituency_name' => $request->constituency_name ?? null,
                'polling' => $request->polling ?? null
            ]);
            $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
            return $pdf->download('Polling Reports_' . $timestamp . '.pdf');
        }

        public function voterCardsReport(Request $request)
        {
            // Build the query with joins - starting from voters to match index function count
            $query = DB::table('voters as v')
            ->leftJoin('constituencies as c', 'v.const', '=', 'c.id')
            // Note: We'll use DISTINCT in COUNT for reg_no (voter id in voter_cards_images) instead of vci.id
            ->leftJoin('voter_cards_images as vci', 'vci.reg_no', '=', 'v.voter'); 

        // Get filter parameters
        $existsInDatabase = $request->input('exists_in_database');
        $underAge25 = $request->input('under_age_25');
        $surname = $request->input('surname');
        $firstName = $request->input('first_name');
        $secondName = $request->input('second_name');
        $voterId = $request->input('voter');
        $houseNumber = $request->input('house_number'); 
        $address = $request->input('address');
        $pobse = $request->input('pobse');
        $pobis = $request->input('pobis');
        $pobcn = $request->input('pobcn');

        // Apply filters matching the index function
        if ($request->has('constituency_id') && !empty($request->constituency_id)) {
            $query->where('v.const', $request->constituency_id);
        }
        if ($request->has('constituency_name') && !empty($request->constituency_name)) {
            $query->whereRaw('LOWER(c.name) LIKE ?', ['%' . strtolower($request->constituency_name) . '%']); 
        }
        if ($request->has('polling') && !empty($request->polling)) {
            $query->where('v.polling', $request->polling);
        }
        
        // exists_in_database filter
        if ($existsInDatabase === 'true') {
            $query->where('v.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('v.exists_in_database', false);
        }
        // under_age_25 filter
        if ($underAge25 === 'yes') {
            $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, v.dob)) < 25');
        }
        // Name filters
        if (!empty($surname)) {
            $query->whereRaw('LOWER(v.surname) LIKE ?', ['%' . strtolower($surname) . '%']);
        }
        if (!empty($firstName)) {
            $query->whereRaw('LOWER(v.first_name) LIKE ?', ['%' . strtolower($firstName) . '%']);
        }
        if (!empty($secondName)) {
            $query->whereRaw('LOWER(v.second_name) LIKE ?', ['%' . strtolower($secondName) . '%']);
        }
        // Voter ID filter
        if (!empty($voterId) && is_numeric($voterId)) {
            $query->where('v.voter', $voterId);
        }
        // Address filters
        $query->where(function($q) use ($houseNumber, $address, $pobse, $pobis, $pobcn) {
            if ($houseNumber !== null && $houseNumber !== '') {
                $q->whereRaw('LOWER(v.house_number) = ?', [strtolower($houseNumber)]);
            }
            if ($address !== null && $address !== '') {
                $q->whereRaw('LOWER(v.address) = ?', [strtolower($address)]);
            }
            if ($pobse !== null && $pobse !== '') {
                $q->whereRaw('LOWER(v.pobse) = ?', [strtolower($pobse)]);
            }
            if ($pobis !== null && $pobis !== '') {
                $q->whereRaw('LOWER(v.pobis) = ?', [strtolower($pobis)]);
            }
            if ($pobcn !== null && $pobcn !== '') {
                $q->whereRaw('LOWER(v.pobcn) = ?', [strtolower($pobcn)]);
            }
        });

        // Select aggregated data by polling division
        $results = $query->select(
            'v.polling as polling_division',
            // Use DISTINCT vci.reg_no for UNIQUE voter id count in images (not vci.id)
            DB::raw("COUNT(DISTINCT CASE WHEN vci.exit_poll = 'fnm' THEN vci.reg_no END) as fnm_count"),
            DB::raw("COUNT(DISTINCT CASE WHEN vci.exit_poll = 'plp' THEN vci.reg_no END) as plp_count"),
            DB::raw("COUNT(DISTINCT CASE WHEN vci.exit_poll = 'coi' THEN vci.reg_no END) as dna_count"),
            DB::raw("COUNT(DISTINCT CASE WHEN vci.exit_poll NOT IN ('fnm', 'plp', 'coi') AND vci.exit_poll IS NOT NULL THEN vci.reg_no END) as other_count"),
            // For no_vote_count, those voters with NO voter card image at all
            DB::raw("COUNT(DISTINCT CASE WHEN vci.reg_no IS NULL THEN v.id END) as no_vote_count"),
            // All voters in polling division
            DB::raw("COUNT(DISTINCT v.id) as total_count"),

            // Percentages (use reg_no counts in numerator)
            DB::raw("ROUND((COUNT(DISTINCT CASE WHEN vci.exit_poll = 'fnm' THEN vci.reg_no END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as fnm_percentage"),
            DB::raw("ROUND((COUNT(DISTINCT CASE WHEN vci.exit_poll = 'plp' THEN vci.reg_no END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as plp_percentage"),
            DB::raw("ROUND((COUNT(DISTINCT CASE WHEN vci.exit_poll = 'coi' THEN vci.reg_no END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as dna_percentage"),
            DB::raw("ROUND((COUNT(DISTINCT CASE WHEN vci.exit_poll NOT IN ('fnm', 'plp', 'coi') AND vci.exit_poll IS NOT NULL THEN vci.reg_no END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as other_percentage"),
            DB::raw("ROUND((COUNT(DISTINCT CASE WHEN vci.reg_no IS NULL THEN v.id END) * 100.0) / NULLIF(COUNT(DISTINCT v.id), 0), 2) as no_vote_percentage")
        )
        ->groupBy('v.polling')
        ->orderBy('v.polling', 'asc')
        ->get();

            // Transform: add total_party_count (sum of fnm, plp, dna, other counts) to each item
            $results->transform(function ($item) {
                $item->total_party_count =
                    $item->fnm_count
                    + $item->plp_count
                    + $item->dna_count
                    + $item->other_count;
                return $item;
            });

            $columns = array_map(function ($column) {
                return strtolower(urldecode(trim($column)));
            }, explode(',', $_GET['columns']));

            $pdf = Pdf::loadView('pdf.voter-cards-report', [
                'results' => $results,
                'columns' => $columns,
                'constituency_name' => $constituency_name ?? null,
                'constituency_id' => $constituency_id ?? null,
                'polling' => $request->polling ?? null
            ]);
            $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
            return $pdf->download('Voter Cards Report_' . $timestamp . '.pdf');
        }
} 