<?php

namespace App\Http\Controllers\Manager\Exports;

use App\Http\Controllers\Controller;
use App\Models\Voter;
use App\Models\VoterCardImage;
use App\Models\VoterCard;
use Illuminate\Http\Request;
use App\Exports\VotersCardExport;
use Maatwebsite\Excel\Facades\Excel;
class ManagerVoterCardController extends Controller 
{

//   public function getVoterCard_FNM(Request $request){
//     $constituencyIds = explode(',', auth()->user()->constituency_id); 
//      $query = VoterCard::with('voter.constituency');
//      $query->where('circled_exit_poll', 'FNM'); 
//      $query->whereHas('voter', function ($q) use ($constituencyIds) {
//         $q->whereIn('const', $constituencyIds);
//     });
      


//      // Apply filters
//      if ($request->has('voter_id') && is_numeric($request->input('voter_id'))) {
//          $query->whereHas('voter', function($q) use ($request) {
//              $q->where('voter', $request->input('voter_id'));
//          });
//      }

//      if ($request->has('surname')) {
//          $query->whereHas('voter', function($q) use ($request) {
//              $q->whereRaw('LOWER(surname) LIKE ?', ['%' . strtolower($request->input('surname')) . '%']); 
//          });
//      }

//      if ($request->has('first_name')) {
//          $query->whereHas('voter', function($q) use ($request) {
//              $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->input('first_name')) . '%']);
//          });
//      }

//      if ($request->has('second_name')) {
//          $query->whereHas('voter', function($q) use ($request) {
//              $q->whereRaw('LOWER(second_name) LIKE ?', ['%' . strtolower($request->input('second_name')) . '%']);
//          });
//      }

//      if ($request->has('address')) {
//          $query->whereHas('voter', function($q) use ($request) {
//              $q->whereRaw('LOWER(address) LIKE ?', ['%' . strtolower($request->input('address')) . '%']);
//          });
//      }

//      if ($request->has('constituency_id') && is_numeric($request->input('constituency_id'))) {
//          $query->whereHas('voter.constituency', function($q) use ($request) {
//              $q->where('id', $request->input('constituency_id'));
//          });
//      }

//      if ($request->has('polling') && is_numeric($request->input('polling'))) {
//          $query->whereHas('voter', function($q) use ($request) {
//              $q->where('polling', $request->input('polling'));
//          });
//      }

//      $getVoterCard = $query->orderBy('id', 'desc')->get();


//      $columns = array_map(function($column) {
//         return strtolower(urldecode(trim($column)));
//     }, explode(',', $_GET['columns']));
    
//     $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
//     return Excel::download(new VotersCardExport($getVoterCard, $request, $columns), 'FNM Voters_' . $timestamp . '.xlsx');  

 
    
//   }


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
private function getVoterCardByParty(Request $request, $party, $filename)
    {
        $tableName = (new VoterCardImage())->getTable();
        $constituencyIds = array_filter(array_map('trim', explode(',', (string) (auth()->user()->constituency_id ?? ''))));
 
        $query = VoterCardImage::query()
            ->leftJoin('voters', $tableName . '.reg_no', '=', 'voters.voter')
            ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
            ->select($tableName . '.*')
            ->whereIn('voters.const', $constituencyIds);
        
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
   
    return $this->getVoterCardByParty($request, 'DNA', 'DNA Voters.xlsx');

      
  }
  public function getVoterCard_UNK(Request $request){
    return $this->getVoterCardByParty($request, 'UNK', 'UNK Voters.xlsx');

     
  } 


  public function listVoterCardResult(Request $request){
    $tableName = (new VoterCardImage())->getTable();
    $constituencyIds = array_filter(array_map('trim', explode(',', (string) (auth()->user()->constituency_id ?? ''))));
    $query = VoterCardImage::query()
        ->leftJoin('voters', $tableName . '.reg_no', '=', 'voters.voter')
        ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
        ->with('user', 'voter')
        ->select($tableName . '.*')
        ->whereIn('voters.const', $constituencyIds)
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
            $party = Party::whereRaw('LOWER(short_name) = ?', [strtolower($votingFor)])->first();
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
}