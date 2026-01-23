<?php

namespace App\Http\Controllers\Manager\Exports;

use App\Http\Controllers\Controller;
use App\Models\Voter;
use Illuminate\Http\Request;
use App\Models\UnregisteredVoter;
use App\Models\Survey;
use App\Exports\VotersExport;
use App\Exports\ManagerVotersExport;
use App\Exports\ManagerSingleUser_SurveysExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Party;
use Illuminate\Support\Facades\DB;
class ManagerVoterController  extends Controller 
{
   
 
    public function getVotersInSurveyDetails(Request $request , $id)
    {
        // Get search parameters
        $surname = $request->input('surname');
        $firstName = $request->input('first_name');
        $secondName = $request->input('second_name');
        $address = $request->input('address');
        $voterId = $request->input('voter_id'); 
        $constituencyName = $request->input('constituency_name');
        $underAge25 = $request->input('under_age_25');
        $existsInDatabase = $request->input('exists_in_database'); 
        $query = Survey::with('voter');

        $polling = $request->input('polling');
        $houseNumber = $request->input('house_number');
        $pobse = $request->input('pobse');
        $pobis = $request->input('pobis');
        $pobcn = $request->input('pobcn');


        if ($existsInDatabase === 'true') {
            $query->whereHas('voter', function($q) {
                $q->where('exists_in_database', true);
            });
        } elseif ($existsInDatabase === 'false') {
            $query->whereHas('voter', function($q) {
                $q->where('exists_in_database', false); 
            });
        }

        if (!empty($polling)) {
            $query->whereHas('voter', function($q) use ($polling) {
                $q->where('polling', $polling);
            });
        }
        
        $query->whereHas('voter', function($q) use ($houseNumber, $address, $pobse, $pobis, $pobcn) {
            if ($houseNumber !== null && $houseNumber !== '') {
                $q->whereRaw('LOWER(house_number) = ?', [strtolower($houseNumber)]);
            }
            if ($address !== null && $address !== '') {
                $q->whereRaw('LOWER(address) = ?', [strtolower($address)]);
            }
            if ($pobse !== null && $pobse !== '') {
                $q->whereRaw('LOWER(pobse) = ?', [strtolower($pobse)]);
            }
            if ($pobis !== null && $pobis !== '') {
                $q->whereRaw('LOWER(pobis) = ?', [strtolower($pobis)]);
            }
            if ($pobcn !== null && $pobcn !== '') {
                $q->whereRaw('LOWER(pobcn) = ?', [strtolower($pobcn)]);
            }
        });
           
        if (isset($request->start_date) && !empty($request->start_date)) {
            $query->where('created_at', '>=', $request->start_date . ' 00:00:00');
        }

        if (isset($request->end_date) && !empty($request->end_date)) {
            $query->where('created_at', '<=', $request->end_date . ' 23:59:59');
        }

        if ($request->filled('surname')) {
            $query->whereHas('voter', function($q) use ($request) {
                $q->whereRaw('LOWER(surname) LIKE ?', ['%' . strtolower($request->surname) . '%']);
            });
        }

        if ($request->filled('first_name')) {
            $query->whereHas('voter', function($q) use ($request) {
                $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->first_name) . '%']);
            });
        }

        if ($request->filled('second_name')) {
            $query->whereHas('voter', function($q) use ($request) {
                $q->whereRaw('LOWER(second_name) LIKE ?', ['%' . strtolower($request->second_name) . '%']);
            });
        }

        if ($request->filled('voter_id')) {
            $query->whereHas('voter', function($q) use ($request) {
                $q->where('voter', $request->voter_id);
            });
        }

        if ($request->filled('sex')) {
            $query->where('sex', $request->input('sex'));
        }

        // Get paginated results
        $voters = $query->where('user_id', $id)
                    ->orderBy('id', 'desc')->get();

        $columns = array_map(function($column) {
            return strtolower(urldecode(trim($column)));
        }, explode(',', $_GET['columns']));
        $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
        return Excel::download(
            new SingleUserSurveysExport($voters, $request, $columns),
            'Canvasser Voters In Survey_' . $timestamp . '.xlsx'
        );  
    }
   public function getVotersInSurveyBackup(Request $request)

   {
        $const = auth()->user()->constituency_id;

        $constituency_id = explode(',', $const);
       
       // Get search parameters
       $surname = $request->input('surname');
       $firstName = $request->input('first_name');
       $secondName = $request->input('second_name');
       $address = $request->input('address');
       $voterId = $request->input('voter_id'); 
       $constituencyName = $request->input('constituency_name');
       $constituencyId = $request->input('const');
      
       $underAge25 = $request->input('under_age_25');
       $polling = $request->input('polling');
       $houseNumber = $request->input('house_number');
       $pobse = $request->input('pobse');
       $pobis = $request->input('pobis');
       $pobcn = $request->input('pobcn');
       $located = $request->input('located');
       $voting_for = $request->input('voting_for');
       $is_died = $request->input('is_died');
       $died_date = $request->input('died_date'); 
       $existsInDatabase = $request->input('exists_in_database');

       $latestSurveySubquery = DB::table('surveys')
       ->selectRaw('DISTINCT ON (voter_id) 
           voter_id,
           id,
           created_at,
           user_id,
           located,
           voting_decision,
           voting_for,
           is_died,
           died_date,
           work_phone_code,
           work_phone,
           cell_phone_code,
           cell_phone,
           email,
           challenge')
       ->orderBy('voter_id') 
       ->orderBy('id', 'desc');

    // OPTIMIZED: Use leftJoinSub instead of raw join
    // This is more efficient and allows better query optimization
    $query = Voter::query()
        ->select(
            'voters.*',
            'constituencies.name as constituency_name',
            'ls.id as survey_id',
            'ls.created_at as survey_date',
            'ls.user_id',
            'ls.located',
            'ls.voting_decision',
            'ls.voting_for',
            'ls.is_died',
            'ls.died_date',
            'ls.work_phone_code',
            'ls.work_phone',
            'ls.cell_phone_code',
            'ls.cell_phone',
            'ls.email',
            'ls.challenge'
        )
        ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
        ->joinSub($latestSurveySubquery, 'ls', 'ls.voter_id', '=', 'voters.id')
        ->whereIn('voters.const', explode(',', auth()->user()->constituency_id))
        ->orderBy('ls.created_at', 'desc');

            if ($existsInDatabase === 'true') {
                $query->where('voters.exists_in_database', true);
            } elseif ($existsInDatabase === 'false') {
                $query->where('voters.exists_in_database', false);
            }

            if( $voting_for !== null && $voting_for !== ''){
                
                $get_party = Party::where('id', $voting_for)->first();
                $voting_for = $get_party->name;
                $query->where('surveys.voting_for', $voting_for);
            }
            if($is_died !== null && $is_died !== ''){
                $query->where('surveys.is_died', $is_died);
            }
            
   
            if (!empty($polling)) {
                $query->where('voters.polling', $polling);
            }
            if (!empty($located)) {
                $query->whereRaw('LOWER(surveys.located) = ?', [strtolower($located)]);
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


        if (isset($request->user_id) && !empty($request->user_id)) {
            $query->where('surveys.user_id',$request->user_id);
        }
        if ($underAge25 === 'yes') {
            $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
        }

       // Apply search filters
       if (isset($request->start_date) && !empty($request->start_date)) {
        $query->where('surveys.died_date', '>=', $request->start_date . ' 00:00:00');
        }

        if (isset($request->end_date) && !empty($request->end_date)) {
            $query->where('surveys.died_date', '<=', $request->end_date . ' 23:59:59');
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
 

       if (!empty($voterId) && is_numeric($voterId)) {
           $query->where('voters.voter', $voterId);
       }

       if (!empty($constituencyName)) {
           $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
       }

       if (!empty($constituencyId)) {
        $query->where('voters.const', $constituencyId);
    }

       // Get paginated results
       $voters = $query->get();

       $columns = array_map(function($column) {
        return strtolower(urldecode(trim($column)));
    }, explode(',', $_GET['columns']));
    $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
    return Excel::download(new ManagerVotersExport($voters, $request, $columns), 'Voters In Survey_' . $timestamp . '.xlsx');  


       return response()->json([
           'success' => true,
           'data' => $voters 
       ]);
   }


   public function getVotersInSurvey(Request $request)
{
    

    $const = $request->input('const');
    $surname = $request->input('surname');
    $firstName = $request->input('first_name');
    $secondName = $request->input('second_name');
    $address = $request->input('address');
    $voterId = $request->input('voter');
    $constituencyName = $request->input('constituency_name');
    $constituencyId = $request->input('const');
    $underAge25 = $request->input('under_age_25');
    $houseNumber = $request->input('house_number');
    $pobse = $request->input('pobse');
    $pobis = $request->input('pobis');
    $pobcn = $request->input('pobcn');
    $located = $request->input('located');
    $voting_decision = $request->input('voting_decision');
    $voting_for = $request->input('voting_for');
    $is_died = $request->input('is_died');
    $died_date = $request->input('died_date');
    $existsInDatabase = $request->input('exists_in_database');
    $challenge = $request->input('challenge');

    // OPTIMIZED: Create subquery with only needed columns instead of SELECT *
    // This significantly reduces the amount of data processed
    $latestSurveySubquery = DB::table('surveys')
        ->selectRaw('DISTINCT ON (voter_id) 
            voter_id,
            id,
            created_at,
            user_id,
            located,
            voting_decision,
            voting_for,
            is_died,
            died_date,
            work_phone_code,
            work_phone,
            cell_phone_code,
            cell_phone,
            email,
            challenge')
        ->orderBy('voter_id')
        ->orderBy('id', 'desc');
 
    // OPTIMIZED: Use leftJoinSub instead of raw join
    // This is more efficient and allows better query optimization
    $query = Voter::query()
        ->select(
            'voters.*',
            'constituencies.name as constituency_name',
            'ls.id as survey_id',
            'ls.created_at as survey_date',
            'ls.user_id',
            'ls.located',
            'ls.voting_decision',
            'ls.voting_for',
            'ls.is_died',
            'ls.died_date',
            'ls.work_phone_code',
            'ls.work_phone',
            'ls.cell_phone_code',
            'ls.cell_phone',
            'ls.email',
            'ls.challenge'
        )
        ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
        ->joinSub($latestSurveySubquery, 'ls', 'ls.voter_id', '=', 'voters.id')
        ->whereIn('voters.const', explode(',', auth()->user()->constituency_id))
        ->orderBy('ls.created_at', 'desc');
        
            
    $searchableFields = [
        'first_name' => 'First Name',
        'second_name' => 'Second Name',
        'surname' => 'Surname', 
        'address' => 'Address',
        'voter' => 'Voter ID',
        'const' => 'Constituency ID',
        'constituency_name' => 'Constituency Name',
        'user_id' => 'User ID',
        'under_age_25' => 'Under 25',
        'polling' => 'Polling Station',
        'voting_decision' => 'Voting Decision',
        'located' => 'Located',
        'voting_for' => 'Voting For'
    ];  

    // Apply challenge filter
    // For PostgreSQL with boolean type, cast string to boolean explicitly
    if ($challenge === 'true') {
        $query->whereRaw('ls.challenge IS TRUE');
    } elseif ($challenge === 'false') {
        $query->whereRaw('ls.challenge IS FALSE');
    }
    
    // Apply exists_in_database filter
    if ($existsInDatabase === 'true') {
        $query->where('voters.exists_in_database', true);
    } elseif ($existsInDatabase === 'false') {
        $query->where('voters.exists_in_database', false);
    }

    $polling = $request->input('polling');

    // Apply voting_for filter
    if ($voting_for !== null && $voting_for !== '') {
        $get_party = Party::where('id', $voting_for)->first();
        if ($get_party) {
            $voting_for = $get_party->name;
            $query->where('ls.voting_for', $voting_for);
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
        $query->where('voters.polling', $polling);
    }
    
    // Apply under_age_25 filter
    $underAge25 = $request->input('under_age_25');
    if ($underAge25 === 'yes') {
        $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
    }

    // Apply user_id filter
    if (isset($request->user_id) && !empty($request->user_id)) {
        $query->where('ls.user_id', $request->user_id);
    }

    // Apply date range filters
    if (isset($request->start_date) && !empty($request->start_date)) {
        $query->where('ls.created_at', '>=', $request->start_date . ' 00:00:00');
    }

    if (isset($request->end_date) && !empty($request->end_date)) {
        $query->where('ls.created_at', '<=', $request->end_date . ' 23:59:59');
    }

    // Apply name filters
    if (!empty($surname)) {
        $query->whereRaw('LOWER(voters.surname) LIKE ?', ['%' . strtolower($surname) . '%']);
    }

    if (!empty($firstName)) {
        $query->whereRaw('LOWER(voters.first_name) LIKE ?', ['%' . strtolower($firstName) . '%']);
    }

    if (!empty($secondName)) {
        $query->whereRaw('LOWER(voters.second_name) LIKE ?', ['%' . strtolower($secondName) . '%']);
    }

    // Apply address-related filters
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

    // Apply voter ID filter
    if (!empty($voterId) && is_numeric($voterId)) {
        $query->where('voters.voter', $voterId);
    }

    // Apply constituency name filter
    if (!empty($constituencyName)) {
        $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
    }

    // Apply constituency ID filter
    if (!empty($constituencyId) && is_numeric($constituencyId)) {
        $query->where('voters.const', $constituencyId);
    }

    $voters = $query->get();

    $columns = array_map(function($column) {
    return strtolower(urldecode(trim($column)));
    }, explode(',', $_GET['columns']));
    $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
    return Excel::download(new ManagerVotersExport($voters, $request, $columns), 'Voters In Survey_' . $timestamp . '.xlsx');  


       return response()->json([
           'success' => true,
           'data' => $voters 
       ]);

   
}
   public function getDiedVotersInSurvey(Request $request)

   {
        $const = auth()->user()->constituency_id;

        $constituency_id = explode(',', $const);
       // Get search parameters
       $surname = $request->input('surname');
       $firstName = $request->input('first_name');
       $secondName = $request->input('second_name');
       $address = $request->input('address');
       $voterId = $request->input('voter_id'); 
       $constituencyName = $request->input('constituency_name');
       $underAge25 = $request->input('under_age_25');
       $polling = $request->input('polling');
       $houseNumber = $request->input('house_number');
       $pobse = $request->input('pobse');
       $pobis = $request->input('pobis');
       $pobcn = $request->input('pobcn');
       $located = $request->input('located');
       $voting_for = $request->input('voting_for');
       $is_died = $request->input('is_died');
       $died_date = $request->input('died_date');
       $existsInDatabase = $request->input('exists_in_database');
       $query = Voter::with('user') 

       ->select('voters.*', 'constituencies.name as constituency_name', 'surveys.id as survey_id', 'surveys.created_at as survey_date','surveys.user_id','surveys.located','surveys.voting_for','surveys.voting_decision','surveys.is_died','surveys.died_date')
       ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
       ->join('surveys', 'voters.id', '=', 'surveys.voter_id')
       ->whereExists(function ($query) {
           $query->select('id')
               ->from('surveys')
               ->whereColumn('surveys.voter_id', 'voters.id');
       })
       ->whereIn('voters.const', $constituency_id)
       ->where('surveys.is_died', 1)
       ->orderBy('surveys.id', 'desc');

        if ($existsInDatabase === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false);
        }
            if( $voting_for !== null && $voting_for !== ''){
                
                $get_party = Party::where('id', $voting_for)->first();
                $voting_for = $get_party->name;
                $query->where('surveys.voting_for', $voting_for);
            }
             
            
   
            if (!empty($polling)) {
                $query->where('voters.polling', $polling);
            }
            if (!empty($located)) {
                $query->whereRaw('LOWER(surveys.located) = ?', [strtolower($located)]);
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


        if (isset($request->user_id) && !empty($request->user_id)) {
            $query->where('surveys.user_id',$request->user_id);
        }
        if ($underAge25 === 'yes') {
            $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
        }

       // Apply search filters
       if (isset($request->start_date) && !empty($request->start_date)) {
        $query->where('surveys.died_date', '>=', $request->start_date . ' 00:00:00');
        }

        if (isset($request->end_date) && !empty($request->end_date)) {
            $query->where('surveys.died_date', '<=', $request->end_date . ' 23:59:59');
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
 

       if (!empty($voterId) && is_numeric($voterId)) {
           $query->where('voters.voter', $voterId);
       }

       if (!empty($constituencyName)) {
           $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
       }

       // Get paginated results
       $voters = $query->get();

       $columns = array_map(function($column) {
        return strtolower(urldecode(trim($column)));
    }, explode(',', $_GET['columns']));
    $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
    return Excel::download(new ManagerVotersExport($voters, $request, $columns), 'Voters In Survey_' . $timestamp . '.xlsx');  


       return response()->json([
           'success' => true,
           'data' => $voters 
       ]);
   }

   public function getVotersNotInSurveyExport(Request $request)
   {
        $const = auth()->user()->constituency_id;
        $constituency_id = explode(',', $const);
       // Get search parameters
       $surname = $request->input('surname');
       $firstName = $request->input('first_name');
       $secondName = $request->input('second_name');
       $address = $request->input('address');
       $voterId = $request->input('voter_id');
       $constituencyName = $request->input('constituency_name');
       $underAge25 = $request->input('under_age_25');
       $polling = $request->input('polling');
       $houseNumber = $request->input('house_number');
       $pobse = $request->input('pobse');
       $pobis = $request->input('pobis');
       $pobcn = $request->input('pobcn');
       $existsInDatabase = $request->input('exists_in_database');

       $query = Voter::query()
           ->select('voters.*', 'constituencies.name as constituency_name')
           ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
           ->whereNotExists(function ($query) {
               $query->select('id')
                   ->from('surveys')
                   ->whereColumn('surveys.voter_id', 'voters.id');
           })
           ->whereIn('voters.const', $constituency_id);

        if ($existsInDatabase === 'true') {
            $query->where('voters.exists_in_database', true);
        } elseif ($existsInDatabase === 'false') {
            $query->where('voters.exists_in_database', false);
        }

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

       // Apply search filters
       if ($underAge25 === 'yes') {
        $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, voters.dob)) < 25');
    }
        // Apply search filters
        if (isset($request->start_date) && !empty($request->start_date)) {
            $query->where('voters.created_at', '>=', $request->start_date . ' 00:00:00');
            }
    
            if (isset($request->end_date) && !empty($request->end_date)) {
                $query->where('voters.created_at', '<=', $request->end_date . ' 23:59:59');
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

       if (!empty($constituencyName)) {
           $query->whereRaw('LOWER(constituencies.name) LIKE ?', ['%' . strtolower($constituencyName) . '%']);
       }

       // Get paginated results
       $voters = $query->orderBy('voters.id', 'desc')->get();

       $columns = array_map(function($column) {
        return strtolower(urldecode(trim($column)));
    }, explode(',', $_GET['columns']));
    $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
    return Excel::download(new ManagerVotersExport($voters, $request, $columns), 'Voters Not In Survey_' . $timestamp . '.xlsx');  


       return response()->json([
           'success' => true,
           'data' => $voters
       ]);
   }
 
   
   
 


   public function getUserSurveys(Request $request, $id)
   {    
    $query = Survey::with(['voter' => function($q) {
        $q->select('voters.*')
          ->leftJoin('constituencies', 'voters.const', '=', 'constituencies.id')
          ->addSelect('constituencies.name as constituency_name');
    }])->where('user_id', $id);

    // Search fields based on Survey model's fillable columns
    // Apply search filters
    $searchableFields = [
        'surname',
        'first_name',
        'second_name',
        'voter_id',
        'sex',
        'start_date',
        'end_date',
        'located',
        'voting_decision',
        'voting_for',
        'is_died',
        'died_date'
    ];
    $existsInDatabase = $request->input('exists_in_database');
    if(isset($request->voting_for) && !empty($request->voting_for)){
        $get_party = Party::where('id', $request->voting_for)->first();
        $voting_for = $get_party->name;
        $query->where('surveys.voting_for', $voting_for);
    }

    if(isset($request->is_died)){
        $query->where('surveys.is_died', $request->is_died);
    }
    if(isset($request->died_date) && !empty($request->died_date)){
        $query->where('surveys.died_date', $request->died_date);
    }
    if ($existsInDatabase === 'true') {
        $query->where('voters.exists_in_database', true);
    } elseif ($existsInDatabase === 'false') {
        $query->where('voters.exists_in_database', false);
    }

    if (!empty($request->voting_decision)) {
        $query->where('surveys.voting_decision', $request->voting_decision);
    }
    
    if (!empty($request->located)) {
        $query->whereRaw('LOWER(located) = ?', [strtolower($request->located)]);
    }

    if (isset($request->start_date) && !empty($request->start_date)) {
        $query->where('created_at', '>=', $request->start_date . ' 00:00:00');
    }

    if (isset($request->end_date) && !empty($request->end_date)) {
        $query->where('created_at', '<=', $request->end_date . ' 23:59:59');
    }

    if ($request->filled('surname')) {
        $query->whereHas('voter', function($q) use ($request) {
            $q->whereRaw('LOWER(surname) LIKE ?', ['%' . strtolower($request->surname) . '%']);
        });
    }

    if ($request->filled('first_name')) {
        $query->whereHas('voter', function($q) use ($request) {
            $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($request->first_name) . '%']);
        });
    }

    if ($request->filled('second_name')) {
        $query->whereHas('voter', function($q) use ($request) {
            $q->whereRaw('LOWER(second_name) LIKE ?', ['%' . strtolower($request->second_name) . '%']);
        });
    }

    if ($request->filled('voter_id')) {
        $query->whereHas('voter', function($q) use ($request) {
            $q->where('voter', $request->voter_id);
        });
    }

    if ($request->filled('sex')) {
        $query->where('sex', $request->input('sex'));
    }

    // Get paginated results
    $surveys = $query->orderBy('id', 'desc')
                    ->get();
   
        $columns = array_map(function($column) {
            return strtolower(urldecode(trim($column)));
        }, explode(',', $_GET['columns']));
        $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
        return Excel::download(
            new ManagerSingleUser_SurveysExport($surveys, $request, $columns), 
            'Voters In Survey_' . $timestamp . '.xlsx'
        );
   } 



}