<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Collaborator;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollaboratorController extends Controller
{
    public function index(Request $request, Campaign $campaign): JsonResponse
    {
        if ($campaign->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($campaign->collaborators()->with('user')->get());
    }

    public function store(Request $request, Campaign $campaign): JsonResponse
    {
        if ($campaign->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'role' => 'required|in:installator,publicist,manager',
        ]);

        $existingUser = User::where('email', $validated['email'])->first();

        $collaborator = $campaign->collaborators()->create([
            'invited_by_user_id' => $request->user()->id,
            'user_id' => $existingUser?->id,
            'email' => $validated['email'],
            'role' => $validated['role'],
        ]);

        return response()->json($collaborator, 201);
    }

    public function destroy(Request $request, Campaign $campaign, Collaborator $collaborator): JsonResponse
    {
        if ($campaign->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $collaborator->update(['status' => 'revoked']);

        return response()->json(['message' => 'Collaborator revoked.']);
    }
}
