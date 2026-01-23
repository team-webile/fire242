<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Answer;
use App\Models\Question;

class AnswerController extends Controller 
{
    
    public function updatePositions(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'items' => 'required|array',
            'items.*.id' => 'required|integer',
            'items.*.position' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // First verify all IDs exist before updating
        $ids = collect($request->items)->pluck('id');
        $existingIds = Answer::whereIn('id', $ids)->pluck('id');
        
        // Find which IDs don't exist
        $invalidIds = $ids->diff($existingIds);
        
        if ($invalidIds->isNotEmpty()) {
            return response()->json([
                'message' => 'One or more answer IDs do not exist',
                'invalid_ids' => $invalidIds->values()
            ], 422);
        }

        foreach ($request->items as $item) {
            Answer::where('id', $item['id'])
                ->update(['position' => $item['position']]);
        }

        return response()->json(['message' => 'Position of answers updated successfully']);
    } 
    
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 20);
        $query = Answer::with('question');

        $question = null;
        if ($request->has('question_id') && !empty($request->question_id)) {
            $query->where('question_id', $request->question_id);
            $question = Question::where('id', $request->question_id)->first(['question']);
        }

        if ($request->has('answer') && !empty($request->answer)) {
            $query->whereRaw('LOWER(answer) LIKE ?', ['%' . strtolower($request->answer) . '%']);
        }

        if ($request->has('question') && !empty($request->question)) {
            $query->whereHas('question', function($q) use ($request) {
                $q->whereRaw('LOWER(question) LIKE ?', ['%' . strtolower($request->question) . '%']);
            });
        }
        
        $searchParams = [   
            'answer' => $request->answer,
            'question' => $request->question
        ];

        $data = [
            'success' => true,
            'data' => $query->orderBy('position', 'asc')->paginate($perPage),
            'search_params' => $searchParams,
            'question' => $question
        ];

        return response()->json($data); 
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $questions = Question::orderBy('position', 'asc')->get();
        return response()->json(['success' => true, 'data' => $questions]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question_id' => 'required|exists:questions,id',
            'answer' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {  
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $highestPosition = Answer::max('position') + 1; // Get the highest position value and add 1
        //dd($highestPosition);
        $request->merge(['position' => $highestPosition]); // Merge the new position into the request
        //dd($request->all());
        $answer = Answer::create($request->all());
        return response()->json(['success' => true, 'message' => 'Answer created successfully', 'answer' => $answer], 201);

    }

    /**
     * Display the specified resource.
     */
    public function show(Answer $answer)
    {
        $answer = Answer::with('question')->findOrFail($answer->id);
        return response()->json(['success' => true, 'answer' => $answer]);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Answer $answer)
    {
        $validator = Validator::make($request->all(), [
            'question_id' => 'required|exists:questions,id',
            'answer' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $answer->update($request->all());
        return response()->json(['success' => true, 'message' => 'Answer updated successfully', 'answer' => $answer]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $answer = Answer::find($id);
        if (!$answer) {
            return response()->json(['success' => false, 'message' => 'Answer not found'], 404);
        }
        $answer->delete();
        return response()->json(['success' => true, 'message' => 'Answer deleted successfully']);
    }
}
