<?php

namespace App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Controller;
use App\Models\RolePermission;  
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    public function index()
    {
        $rolePermissions = RolePermission::all();
        return response()->json(['success' => true, 'data' => $rolePermissions, 'message' => 'Role permissions fetched successfully']);
    }

    public function store(Request $request)
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id',
            'page_id' => 'required|exists:pages,id',
        ]);

        $rolePermission = RolePermission::create($request->all());
        return response()->json(['success' => true, 'data' => $rolePermission, 'message' => 'Role permission created successfully']);
    }

    public function show(RolePermission $rolePermission)
    {
        return response()->json(['success' => true, 'data' => $rolePermission, 'message' => 'Role permission fetched successfully']);
    }

    public function update(Request $request, RolePermission $rolePermission)
    {
        $request->validate([
            'role_id' => 'exists:roles,id',
            'page_id' => 'exists:pages,id',
        ]);

        $rolePermission->update($request->all());
        return response()->json(['success' => true, 'data' => $rolePermission, 'message' => 'Role permission updated successfully']);
    }

    public function destroy(RolePermission $rolePermission)
    {
        $rolePermission->delete();
        return response()->json(['success' => true, 'message' => 'Role permission deleted successfully']);
    }
}