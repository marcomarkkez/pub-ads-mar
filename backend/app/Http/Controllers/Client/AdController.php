<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Concerns\AuthorizesOwnershipChain;
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
    use AuthorizesOwnershipChain;

    public function index(Request $request, Campaign $campaign, Adset $adset): JsonResponse
    {
        $this->authorizeAdset($request, $campaign, $adset);

        return response()->json($adset->ads()->with(['space', 'provider'])->get());
    }

    /**
     * design.md §21 rule 4 — INVARIANT: every ad has a `space_id`. A space-less ad
     * has nobody to pay and nowhere to send the files, so it can never be booked.
     * `space_id` is therefore REQUIRED here; ads legitimately originate from
     * booking a space or from moving one in from the campaign backlog (§5).
     */
    public function store(Request $request, Campaign $campaign, Adset $adset): JsonResponse
    {
        $this->authorizeAdset($request, $campaign, $adset);

        $request->validate([
            'name' => 'required|string|max:255',
            'media_type' => 'required|in:image,video,sound,gif',
            'file' => 'required|file|max:20480',
            'space_id' => 'required|integer|exists:spaces,id',
            'price' => 'nullable|numeric|min:0',
            'pricing_unit' => 'nullable|string|max:50',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $space = Space::findOrFail($request->space_id);

        $file = $request->file('file');
        $path = $file->store('ads', 'public');

        $ad = $adset->ads()->create([
            'name' => $request->name,
            'media_type' => $request->media_type,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'space_id' => $space->id,
            'provider_user_id' => $space->user_id,
            'price' => $request->price,
            'pricing_unit' => $request->pricing_unit,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => 'draft',
        ]);

        return response()->json($ad->load(['space', 'provider']), 201);
    }

    public function show(Request $request, Campaign $campaign, Adset $adset, Ad $ad): JsonResponse
    {
        $this->authorizeAd($request, $campaign, $adset, $ad);

        return response()->json($ad->load(['space', 'provider', 'proofs', 'bookings']));
    }

    public function update(Request $request, Campaign $campaign, Adset $adset, Ad $ad): JsonResponse
    {
        $this->authorizeAd($request, $campaign, $adset, $ad);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            // §21 rule 4 — an existing ad may be re-pointed at another space, but it
            // can never be left without one, so `nullable` is not allowed here.
            'space_id' => 'sometimes|integer|exists:spaces,id',
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
        $this->authorizeAd($request, $campaign, $adset, $ad);

        if ($ad->file_path) {
            Storage::disk('public')->delete($ad->file_path);
        }
        $ad->delete();

        return response()->json(['message' => 'Ad deleted.']);
    }
}
