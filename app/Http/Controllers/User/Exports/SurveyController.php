<?php

namespace App\Http\Controllers\User\Exports;

use App\Http\Controllers\Controller;
use App\Models\Survey;
use Illuminate\Http\Request;
use App\Exports\SurveysExport;
use Maatwebsite\Excel\Facades\Excel;
 
class SurveyController extends Controller
{

    
    public function SurveyListExport(Request $request)
    {
       
        // Check if user is authenticated and has admin role
       
        if (!auth()->check() || auth()->user()->role->name !== 'User') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - User access required'
            ], 403);
        }

        $query = Survey::with('voter');

        $searchableFields = [
            'sex' => $request->get('sex'),
            'marital_status' => $request->get('marital_status'),
            'employed' => $request->get('employed'),
            'children' => $request->get('children'), 
            'employment_type' => $request->get('employment_type'),
            'religion' => $request->get('religion'),
            'located' => $request->get('located'),
            'home_phone' => $request->get('home_phone'),
            'work_phone' => $request->get('work_phone'),
            'cell_phone' => $request->get('cell_phone'),
            'email' => $request->get('email'),
            'special_comments' => $request->get('special_comments'),
            'other_comments' => $request->get('other_comments'),
            'voting_for' => $request->get('voting_for'),
            'voted_in_2017' => $request->get('voted_in_2017'),
            'where_voted_in_2017' => $request->get('where_voted_in_2017'),
            'voted_in_house' => $request->get('voted_in_house'),
            'voter_id' => $request->get('voter_id'),
            'constituency_id' => $request->get('constituency_id'),
            'first_name' => $request->get('first_name') // Changed key to match voter table column name
        ];
 
        // Apply search filters
        foreach ($searchableFields as $field => $value) {
            if (!empty($value)) {
                if ($field === 'voter_id') {
                    $query->whereHas('voter', function($q) use ($value) {
                        $q->where('id', $value);
                    });
                }
                else if ($field === 'constituency_id') {
                    $query->whereHas('voter', function($q) use ($value) {
                        $q->where('const', $value);
                    });
                }
                else if (in_array($field, ['employed', 'children', 'voted_in_2017'])) {
                    $query->where($field, filter_var($value, FILTER_VALIDATE_BOOLEAN));
                }
                else if (in_array($field, ['sex', 'marital_status', 'employment_type', 'religion'])) {
                    $query->where($field, $value);
                }
                else if ($field === 'first_name') { // Handle first_name search
                    $query->whereHas('voter', function($q) use ($value) {
                        $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($value) . '%']);
                    });
                }
                else {
                    $query->where($field, 'LIKE', "%{$value}%");
                }
            }
        }

        // Get paginated results
        $surveys = $query->where('user_id', auth()->user()->id)->orderBy('id', 'desc')->get();

        $columns = array_map(function($column) {
            return strtolower(urldecode(trim($column)));
        }, explode(',', $_GET['columns']));
     
        return Excel::download(new SurveysExport($surveys, $request, $columns), 'Survey List.xlsx');


        return response()->json([
            'success' => true,
            'data' => $surveys,
            'searchable_fields' => $searchableFields
        ]);
    }

 
    

    

 


}