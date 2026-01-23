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