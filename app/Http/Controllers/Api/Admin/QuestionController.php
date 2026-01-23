<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Question;
use Illuminate\Support\Facades\Validator;
class QuestionController extends Controller
{
    /**
     * Update the positions of the questions.
     */
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
        $existingIds = Question::whereIn('id', $ids)->pluck('id');
        
        // Find which IDs don't exist
        $invalidIds = $ids->diff($existingIds);
        
        if ($invalidIds->isNotEmpty()) {
            return response()->json([
                'message' => 'One or more question IDs do not exist',
                'invalid_ids' => $invalidIds->values()
            ], 422);
        }

        foreach ($request->items as $item) {
            Question::where('id', $item['id'])
                ->update(['position' => $item['position']]);
        }

        return response()->json(['message' => 'Position of questions updated successfully']);
    } 
    
    /**
     * Display a listing of the resource.
     */ 
    public function index(Request $request) 
    {
        $perPage = $request->input('per_page', 20);
        $query = Question::with('answers');
        
        if ($request->has('question') && !empty($request->question)) {
            $query->whereRaw('LOWER(question) LIKE ?', ['%' . strtolower($request->question) . '%']);
        }

        $searchParams = [
            'question' => $request->question
        ];

        $data = [
            'success' => true,
            'data' => $query->orderBy('position', 'asc')->paginate($perPage),
            'search_params' => $searchParams
        ];

        return response()->json($data);  
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:255|unique:questions',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $highestPosition = Question::max('position') + 1; // Get the highest position value and add 1
        $request->merge(['position' => $highestPosition]); // Merge the new position into the request
        $question = Question::create($request->all());
        return response()->json(['success' => true, 'message' => 'Question created successfully', 'question' => $question], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Question $question)
    {
        $question = Question::with('answers')->findOrFail($question->id);
        return response()->json(['success' => true, 'question' => $question]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Question $question)
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:255|unique:questions,question,' . $question->id,
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $question->update($request->all());
        return response()->json(['success' => true, 'message' => 'Question updated successfully', 'question' => $question]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $question = Question::find($id);
        if (!$question) {
            return response()->json(['success' => false, 'message' => 'Question not found'], 404);
        }
        $question->delete();
        return response()->json(['success' => true, 'message' => 'Question deleted successfully']);
    }
}
