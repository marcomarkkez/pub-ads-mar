<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\Space;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $spaces = $request->user()->spaces()->with(['photos', 'availabilities'])->latest()->get();

        return response()->json($spaces);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'price_per_day' => 'nullable|numeric|min:0',
            'price_per_month' => 'nullable|numeric|min:0',
            'pricing_unit' => 'nullable|in:day,month,custom',
            'description' => 'nullable|string',
            'location_text' => 'nullable|string|max:255',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'ical_url' => 'nullable|url|max:500',
            'calendar_keyword' => 'nullable|string|max:255',
        ]);

        $space = $request->user()->spaces()->create($validated);

        return response()->json($space, 201);
    }

    public function show(Request $request, Space $space): JsonResponse
    {
        if ($space->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($space->load(['photos', 'availabilities', 'bookings.client', 'ads']));
    }

    public function update(Request $request, Space $space): JsonResponse
    {
        if ($space->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'nullable|string|max:255',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            'price_per_day' => 'nullable|numeric|min:0',
            'price_per_month' => 'nullable|numeric|min:0',
            'pricing_unit' => 'nullable|in:day,month,custom',
            'description' => 'nullable|string',
            'location_text' => 'nullable|string|max:255',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'ical_url' => 'nullable|url|max:500',
            'calendar_keyword' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $space->update($validated);

        return response()->json($space);
    }

    public function destroy(Request $request, Space $space): JsonResponse
    {
        if ($space->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $space->delete();

        return response()->json(['message' => 'Space deleted.']);
    }
}
