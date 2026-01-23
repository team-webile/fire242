<?php

namespace App\Http\Controllers\Api\Admin; 
use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function index(Request $request)
    {
        $query = Page::query();

        if ($request->has('name')) {
            $query->where('name', 'LIKE', '%' . $request->get('name') . '%');
        }

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        $pages = $query->orderBy('id', 'desc')->paginate($request->get('per_page', 20));
        return response()->json(['success' => true, 'data' => $pages]);
    } 

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|string|max:255',
        ]);

        $page = Page::create($request->all());
        return response()->json(['success' => true, 'data' => $page,'message' => 'Page created successfully']);
    }

    public function show(Page $page)
    {
        return response()->json(['success' => true, 'data' => $page,'message' => 'Page fetched successfully']);
    }

    public function update(Request $request, Page $page)
    {
        $request->validate([
            'name' => 'string|max:255',
            'url' => 'string|max:255',
        ]);

        $page->update($request->all());
        return response()->json(['success' => true, 'data' => $page,'message' => 'Page updated successfully']);
    }

    public function destroy(Page $page)
    {
        $page->delete();
        return response()->json(['success' => true, 'message' => 'Page deleted successfully']);
    }

    public function update_status(Request $request)
    {
      
        $page = Page::find($request->id);
        
        $page->status = $request->status;
        $page->save();
        return response()->json(['success' => true, 'message' => 'Page status updated successfully']);
    }  
}