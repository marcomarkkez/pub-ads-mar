<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Adset;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdsetController extends Controller
{
    public function index(Request $request, Campaign $campaign): JsonResponse
    {
        if ($campaign->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($campaign->adsets()->with('ads')->get());
    }

    public function store(Request $request, Campaign $campaign): JsonResponse
    {
        if ($campaign->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'location_name' => 'nullable|string|max:255',
            'radius_km' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:active,paused',
        ]);

        $adset = $campaign->adsets()->create($validated);

        return response()->json($adset, 201);
    }

    public function show(Request $request, Campaign $campaign, Adset $adset): JsonResponse
    {
        if ($campaign->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($adset->load('ads'));
    }

    public function update(Request $request, Campaign $campaign, Adset $adset): JsonResponse
    {
        if ($campaign->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'location_name' => 'nullable|string|max:255',
            'radius_km' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:active,paused',
        ]);

        $adset->update($validated);

        return response()->json($adset);
    }

    public function destroy(Request $request, Campaign $campaign, Adset $adset): JsonResponse
    {
        if ($campaign->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $adset->delete();

        return response()->json(['message' => 'Adset deleted.']);
    }
}
