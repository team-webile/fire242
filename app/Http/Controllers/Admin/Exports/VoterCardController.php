<?php

namespace App\Http\Controllers\Admin\Exports;

use App\Http\Controllers\Controller;
use App\Models\Voter;
use App\Models\Party;
use App\Models\VoterCard; 
use App\Models\VoterCardImage;
use Illuminate\Http\Request;
use App\Exports\VotersCardExport;
use App\Exports\ElectionDayReportOneExport;
use App\Exports\VotersCardResultExport;
use Maatwebsite\Excel\Facades\Excel; 
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class VoterCardController extends Controller 
{
    /**
     * Apply common filters to the query using joined tables
     */
    private function applyFilters($query, Request $request)
    {
        $tableName = (new VoterCardImage())->getTable();

        if ($request->has('voter_id') && is_numeric($request->input('voter_id'))) {
            $query->where($tableName . '.reg_no', $request->input('voter_id'));
        }

        if ($request->has('surname')) {
            $query->whereRaw('LOWER(voters.surname) LIKE ?', ['%' . strtolower($request->input('surname')) . '%']); 
        }

        if ($request->has('first_name')) {
            $query->whereRaw('LOWER(voters.first_name) LIKE ?', ['%' . strtolower($request->input('first_name')) . '%']);
        }

        if ($request->has('second_name')) {
            $query->whereRaw('LOWER(voters.second_name) LIKE ?', ['%' . strtolower($request->input('second_name')) . '%']);
        }

        if ($request->has('address')) {
            $query->whereRaw('LOWER(voters.address) LIKE ?', ['%' . strtolower($request->input('address')) . '%']);
        }

        if ($request->has('constituency_id') && is_numeric($request->input('constituency_id'))) {
            $query->where('voters.const', $request->input('constituency_id'));
        }

        if ($request->has('polling') && is_numeric($request->input('polling'))) {
            $query->where('voters.polling', $request->input('polling'));
        }
    }

    /**
     * Generic method to get voter cards by party
     */
    private function getVoterCardByParty(Request $request, $party, $filename)
    {
        $tableName = (new VoterCardImage())->getTable();
        
        $query = VoterCardImage::query()
            ->leftJoin('voters', $tableName . '.reg_no', '=', 'voters.voter')
            ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
            ->select($tableName . '.*');
        
        // Filter by party (using exit_poll from VoterCardImage)
        $query->whereRaw('UPPER(' . $tableName . '.exit_poll) = ?', [strtoupper($party)]);
        
        $this->applyFilters($query, $request);

        $getVoterCard = $query->orderBy($tableName . '.id', 'desc')->get();
        
        // Load relationships
        $getVoterCard->load(['voter.constituency']);

        $columns = $request->has('columns') 
            ? array_map(function($column) {
                return strtolower(urldecode(trim($column)));
            }, explode(',', $request->get('columns')))
            : [];
        
        $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
        $filenameWithTimestamp = str_replace('.xlsx', '_' . $timestamp . '.xlsx', $filename);
        return Excel::download(new VotersCardExport($getVoterCard, $request, $columns), $filenameWithTimestamp);
    }

    public function getVoterCard_FNM(Request $request){
        return $this->getVoterCardByParty($request, 'FNM', 'FNM Voters.xlsx');
    }

    public function getVoterCard_PLP(Request $request){
        return $this->getVoterCardByParty($request, 'PLP', 'PLP Voters.xlsx');
    }

    public function getVoterCard_DNA(Request $request){
        return $this->getVoterCardByParty($request, 'COI', 'COI Voters.xlsx');
    }

    public function getVoterCard_UNK(Request $request){
        return $this->getVoterCardByParty($request, 'UNK', 'UNK Voters.xlsx');
    }

    public function listVoterCardResult(Request $request){
        $tableName = (new VoterCardImage())->getTable();
        
        $query = VoterCardImage::query()
            ->leftJoin('voters', $tableName . '.reg_no', '=', 'voters.voter')
            ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
            ->with('user', 'voter')
            ->select($tableName . '.*')
            ->orderBy($tableName . '.id', 'desc');

        // Filter by voter_id if provided
        if ($request->has('voter') && !empty($request->get('voter'))) {
            $query->where($tableName . '.reg_no', 'like', '%' . $request->get('voter') . '%');
        }
        // Filter by party (exit_poll) if provided
        $votingFor = $request->get('voting_for');
        if ($votingFor !== null && $votingFor !== '') {
            if (is_numeric($votingFor)) {
                $party = Party::where('id', $votingFor)->first();
            } else {
                $party = Party::whereRaw('LOWER(name) = ?', [strtolower($votingFor)])->first();
            }
            if ($party) {
                $partyShortName = strtolower($party->short_name);
                $query->whereRaw('LOWER(' . $tableName . '.exit_poll) = ?', [$partyShortName]);
            }
        }
  
        $voterCardImages = $query->get();
    
        $columns = $request->has('columns')
            ? array_map(function($column) {
                return strtolower(urldecode(trim($column)));
            }, explode(',', $request->get('columns')))
            : [];
        
        $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
        return Excel::download(new VotersCardResultExport($voterCardImages, $request, $columns), 'Voter Card Result_' . $timestamp . '.xlsx');  
    }

    public function electionDayReport_one(Request $request) 
    {   
        // Check if user is authenticated and has admin role
        if (!auth()->check() || auth()->user()->role->name !== 'Admin') {  
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }

        // Get all voters, join latest survey if exists
        $query = DB::table('voters')
        ->select(
            'surveys.home_phone_code',
            'surveys.id as survey_id',
            'surveys.home_phone',
            'surveys.work_phone_code',
            'surveys.work_phone',
            'surveys.cell_phone_code',
            'surveys.cell_phone',
            'surveys.voting_for',
            'voters.*',
            'voters.id as voter_table_id',
            'constituencies.name as constituency_name',
            'vci.*'
        )
        ->leftJoin('surveys', function($join) {
            $join->on('surveys.voter_id', '=', 'voters.id')
                    ->whereIn('surveys.id', function($subquery) {
                    $subquery->select(DB::raw('MAX(id)'))
                        ->from('surveys')
                        ->groupBy('voter_id');
                    });
        })
        ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
        ->leftJoin('voter_cards_images as vci', 'vci.reg_no', '=', 'voters.voter');

        $searchableFields = [
            'first_name' => 'First Name',
            'second_name' => 'Second Name',
            'surname' => 'Surname', 
            'address' => 'Address',
            'voter' => 'Voter ID',
            'const' => 'Constituency ID',
            'constituency_name' => 'Constituency Name',
            'polling' => 'Polling Station',
        ];  

        // Get search parameters
        $const = $request->input('const');
        $surname = $request->input('surname');
        $firstName = $request->input('first_name');
        $secondName = $request->input('second_name');
        $address = $request->input('address');
        $voterId = $request->input('voter');
        $constituencyName = $request->input('constituency_name');
        $constituencyId = $request->input('const');
        $underAge25 = $request->input('under_age_25');
        $polling = $request->input('polling');
        $houseNumber = $request->input('house_number');
        $pobse = $request->input('pobse');
        $pobis = $request->input('pobis');
        $pobcn = $request->input('pobcn');
        $existsInDatabase = $request->input('exists_in_database');
        $isVoted = $request->input('is_voted');
        $isSurveyed = $request->input('is_surveyed');
        $advance_poll = $request->input('advance_poll');
        $export = $request->input('export');

        $partyId = $request->input('voting_for');
        if ($partyId !== null && $partyId !== '') {
            if (is_numeric($partyId)) {
                $party = Party::where('id', $partyId)->first();
            } else {
                $party = Party::whereRaw('LOWER(name) = ?', [strtolower($partyId)])->first();
            }
            if ($party) {
                $partyShortName = strtolower($party->short_name);
                $query->whereRaw('LOWER(vci.exit_poll) = ?', [$partyShortName]);
            }  
        }

        if ($advance_poll == 'yes') {
            $query->where('voters.flagged', 1);
        }

        // Get sorting parameters
        $sortBy = $request->input('sort_by');
        $sortOrder = $request->input('sort_order', 'asc');
        $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc']) ? strtolower($sortOrder) : 'asc'; 

        // Apply filters

        // is_surveyed filter
        if ($isSurveyed === 'yes') {
            $query->whereNotNull('surveys.id');
        } elseif ($isSurveyed === 'no') {
            $query->whereNull('surveys.id');
        }

        // is_voted filter (voter_cards_images)
        if ($isVoted === 'yes') {
            $query->whereNotNull('vci.reg_no');
        } elseif ($isVoted === 'no') {
            $query->whereNull('vci.reg_no');
        }

        if (!empty($polling) && is_numeric($polling)) {
            $query->where('voters.polling', $polling);
        }

        if ($underAge25 === 'yes') {
            $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
        }

        // Apply exists_in_database filter
        if ($existsInDatabase === 'true') { 
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false);
        }
        
        if (!empty($const)) {
            $query->where('voters.const', $const);
        }
        
        if (!empty($surname)) {
            $query->whereRaw('voters.surname ILIKE ?', ['%' . $surname . '%']);
        }

        if (!empty($firstName)) {
            $query->whereRaw('voters.first_name ILIKE ?', ['%' . $firstName . '%']);
        }

        if (!empty($secondName)) {
            $query->whereRaw('voters.second_name ILIKE ?', ['%' . $secondName . '%']);
        }

        $query->where(function($q) use ($houseNumber, $address, $pobse, $pobis, $pobcn) {
            if ($houseNumber !== null && $houseNumber !== '') {
                $q->whereRaw('voters.house_number ILIKE ?', [$houseNumber]);
            }
            if ($address !== null && $address !== '') {
                $q->whereRaw('voters.address ILIKE ?', [$address]);
            }
            if ($pobse !== null && $pobse !== '') {
                $q->whereRaw('voters.pobse ILIKE ?', [$pobse]);
            }
            if ($pobis !== null && $pobis !== '') {
                $q->whereRaw('voters.pobis ILIKE ?', [$pobis]);
            }
            if ($pobcn !== null && $pobcn !== '') {
                $q->whereRaw('voters.pobcn ILIKE ?', [$pobcn]);
            }
        }); 


        if (!empty($voterId) && is_numeric($voterId)) {
            $query->where('voters.voter', $voterId);
        }

        if (!empty($constituencyName)) {
            $query->whereRaw('constituencies.name ILIKE ?', ['%' . $constituencyName . '%']);
        }

        if (!empty($constituencyId)) {
            $query->where('voters.const', $constituencyId);
        }

        if (!empty($sortBy)) {
            switch ($sortBy) {
                case 'voter':
                    $query->orderBy('voters.voter', $sortOrder)
                          ->orderBy('surveys.id', 'desc');
                    break;
                case 'const':
                    $query->orderBy('voters.const', $sortOrder)
                          ->orderBy('surveys.id', 'desc');
                    break;
                case 'polling':
                    $query->orderBy('voters.polling', $sortOrder)
                          ->orderBy('surveys.id', 'desc');
                    break;
                case 'first_name':
                    $query->orderByRaw('LOWER(voters.first_name) ' . strtoupper($sortOrder))
                          ->orderBy('surveys.id', 'desc');
                    break;
                case 'last_name':
                    $query->orderByRaw('LOWER(TRIM(voters.surname)) ' . strtoupper($sortOrder))
                          ->orderBy('surveys.id', 'desc');
                    break;
                default:
                    $query->orderBy('surveys.id', 'desc')
                          ->orderBy('voters.id', 'desc'); 
                    break;
            }
        } else {
            // Default sorting - surveys is base table, so sort by survey id first
            $query->orderBy('surveys.id', 'desc')
                  ->orderBy('voters.id', 'desc');
        }

        // Check if it is an export request
        if ($export === 'excel' || $request->has('columns')) {
             $columns = $request->has('columns')
                ? array_map(function($column) {
                    return strtolower(urldecode(trim($column)));
                }, explode(',', $request->get('columns')))
                : [];

             // Use a generator or query for export to save memory if supported, 
             // otherwise collect. 
             $voters = collect($query->cursor());
             
             $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
             return Excel::download(new ElectionDayReportOneExport($voters, $request, $columns), 'Election Day Report One_' . $timestamp . '.xlsx');
        }

        // Standard JSON response
        // Using simplePagination or cursorPagination is better for APIs than sending everything
        // But since original code sent everything via cursor->collect(), we'll keep that logic but PAGINATE it if it's for UI
        
        // If this is for a grid that expects all data (unlikely but possible), we might need cursor.
        // However, usually UI needs pagination.
        // I'll return the collection to match previous behavior, but warn about performance.
        $voters = collect($query->cursor());

        return response()->json([
            'success' => true,
            'data' => $voters,
            'searchable_fields' => $searchableFields,
        ]); 
    }
}