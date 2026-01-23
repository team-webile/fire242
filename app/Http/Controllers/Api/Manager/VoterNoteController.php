<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\VoterNote;
use App\Models\UnregisteredVoter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Party;
class VoterNoteController extends Controller
{
    public function index(Request $request, $unregistered_voter_id)
    {
        try {
            $user = Auth::user();
            $perPage = $request->input('per_page', 20);
    
            // Get unregistered voter details first
            $unregisteredVoter = UnregisteredVoter::with('voter')
                ->where('id', $unregistered_voter_id)
                ->where('user_id', $user->id)
                ->first();
 
            if (!$unregisteredVoter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unregistered voter not found'
                ], 404);
            }

            // Get notes query
            $query = VoterNote::with('user')
                ->where('unregistered_voter_id', $unregistered_voter_id)
                ->where('user_id', $user->id);
    
            // Add search functionality
            if ($request->has('name')) {
                $query->whereHas('unregisteredVoter', function($q) use ($request) {
                    $q->where('name', 'LIKE', '%' . $request->name . '%');
                });
            }

            if ($request->has('email')) {
                $query->whereHas('unregisteredVoter', function($q) use ($request) {
                    $q->where('new_email', 'LIKE', '%' . $request->email . '%');
                });
            }

            if ($request->has('note')) {
                $query->where('note', 'LIKE', '%' . $request->note . '%');
            }
            
            // Add sorting
            $sortField = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $allowedSortFields = ['id', 'note', 'created_at', 'updated_at'];
            
            if (in_array($sortField, $allowedSortFields)) {
                $query->orderBy($sortField, $sortDirection);
            }
    
            $notes = $query->paginate($perPage);
    
            return response()->json([
                'success' => true,
                'data' => [
                    'voter' => $unregisteredVoter,
                    'notes' => $notes
                ],
                'search_parameters' => [
                    'name' => $request->name,
                    'email' => $request->email,
                    'note' => $request->note
                ]
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving notes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request, $unregistered_voter_id)
    {    
        try {
            $user = Auth::user();

            // Check if unregistered voter exists and belongs to auth user
            $unregisteredVoter = UnregisteredVoter::where('id', $unregistered_voter_id)
                ->where('user_id', $user->id)
                ->first();

            if (!$unregisteredVoter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unregistered voter not found or not authorized'
                ], 404);
            }

            $unregisteredVoter->contacted = 1;
            $unregisteredVoter->save();

            // Validate request
            $validatedData = $request->validate([
                'note' => 'required|string|max:1000',
            ]);

            // Create note
            $note = new VoterNote([
                'note' => $validatedData['note'],
                'unregistered_voter_id' => $unregistered_voter_id,
                'user_id' => $user->id
            ]);

            $note->save();

            return response()->json([
                'success' => true,
                'message' => 'Note created successfully',
                'data' => $note->load('user')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating note',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $user = Auth::user();
            $note = VoterNote::with('user')
                ->where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $note
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving note',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $note = VoterNote::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            // Validate request
            $validatedData = $request->validate([
                'note' => 'required|string|max:1000',
            ]);

            // Update note
            $note->update($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Note updated successfully',
                'data' => $note->load('user')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating note',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $user = Auth::user();
            $note = VoterNote::where('id', $id)
                ->where('user_id', $user->id)
                ->first();
          
            if (!$note) {
                return response()->json([
                    'success' => false,
                    'message' => 'Note not found or not authorized'
                ], 404);
            }

            // Get the unregistered_voter_id before deleting
            $unregisteredVoterId = $note->unregistered_voter_id;

            $note->delete();

            // Check remaining notes count for this unregistered voter
            $remainingNotesCount = VoterNote::where('unregistered_voter_id', $unregisteredVoterId)->count();
            
            // If no notes remain, update contacted status to 0
            if ($remainingNotesCount === 0) {
                UnregisteredVoter::where('id', $unregisteredVoterId)
                    ->update(['contacted' => 0]);
                     
            }

            return response()->json([
                'success' => true,
                'message' => 'Note deleted successfully'
            ]); 

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting note',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function parties()
    {
        $parties = Party::select('id', 'name','short_name')->get();
        return response()->json([
            'success' => true,
            'data' => $parties
        ]);
    }
    
}