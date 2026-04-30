<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Adset;
use App\Models\Campaign;
use App\Models\Space;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdController extends Controller
{
    public function index(Request $request, Campaign $campaign, Adset $adset): JsonResponse
    {
        if ($campaign->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($adset->ads()->with(['space', 'provider'])->get());
    }

    public function store(Request $request, Campaign $campaign, Adset $adset): JsonResponse
    {
        if ($campaign->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'media_type' => 'required|in:image,video,sound,gif',
            'file' => 'required|file|max:20480',
            'space_id' => 'nullable|exists:spaces,id',
            'price' => 'nullable|numeric|min:0',
            'pricing_unit' => 'nullable|string|max:50',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $file = $request->file('file');
        $path = $file->store('ads', 'public');

        $adData = [
            'name' => $request->name,
            'media_type' => $request->media_type,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'space_id' => $request->space_id,
            'price' => $request->price,
            'pricing_unit' => $request->pricing_unit,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => 'draft',
        ];

        if ($request->space_id) {
            $space = Space::findOrFail($request->space_id);
            $adData['provider_user_id'] = $space->user_id;
        }

        $ad = $adset->ads()->create($adData);

        return response()->json($ad->load(['space', 'provider']), 201);
    }

    public function show(Request $request, Campaign $campaign, Adset $adset, Ad $ad): JsonResponse
    {
        if ($campaign->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($ad->load(['space', 'provider', 'proofs', 'bookings']));
    }

    public function update(Request $request, Campaign $campaign, Adset $adset, Ad $ad): JsonResponse
    {
        if ($campaign->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'space_id' => 'nullable|exists:spaces,id',
            'price' => 'nullable|numeric|min:0',
            'pricing_unit' => 'nullable|string|max:50',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'sometimes|in:draft,pending_approval,cancelled',
        ]);

        if (isset($validated['space_id'])) {
            $space = Space::findOrFail($validated['space_id']);
            $validated['provider_user_id'] = $space->user_id;
        }

        $ad->update($validated);

        return response()->json($ad->load(['space', 'provider']));
    }

    public function destroy(Request $request, Campaign $campaign, Adset $adset, Ad $ad): JsonResponse
    {
        if ($campaign->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($ad->file_path) {
            Storage::disk('public')->delete($ad->file_path);
        }
        $ad->delete();

        return response()->json(['message' => 'Ad deleted.']);
    }
}
