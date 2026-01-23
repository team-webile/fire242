<?php

namespace App\Http\Controllers\Admin\Exports;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AnswersExport;

class AnswerController extends Controller
{   
     
    public function export(Request $request)
    {   
        $query = Answer::with('question');
        
        if ($request->has('question_id') && !empty($request->question_id)) {
            $query->where('question_id', $request->question_id);
        }

        if ($request->has('answer') && !empty($request->answer)) {
            $query->whereRaw('LOWER(answer) LIKE ?', ['%' . strtolower($request->answer) . '%']);
        }

        if ($request->has('question') && !empty($request->question)) {
            $query->whereHas('question', function($q) use ($request) {
                $q->whereRaw('LOWER(question) LIKE ?', ['%' . strtolower($request->question) . '%']);
            });
        }
        

        $answers = $query->orderBy('position', 'asc')->get();

        $timestamp = now('America/New_York')->format('Y-m-d_g:iA');
        return Excel::download(new AnswersExport($answers, $request), 'Answers List_' . $timestamp . '.xlsx');
    }
} 