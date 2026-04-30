<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\Space;
use App\Models\SpacePhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SpacePhotoController extends Controller
{
    public function store(Request $request, Space $space): JsonResponse
    {
        if ($space->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $request->validate([
            'photo' => 'required|image|max:5120',
            'is_primary' => 'nullable|boolean',
        ]);

        $file = $request->file('photo');
        $path = $file->store('space_photos', 'public');

        // If this is primary, unset other primaries
        if ($request->boolean('is_primary')) {
            $space->photos()->update(['is_primary' => false]);
        }

        $photo = $space->photos()->create([
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'is_primary' => $request->boolean('is_primary', false),
            'sort_order' => $space->photos()->count(),
        ]);

        return response()->json($photo, 201);
    }

    public function destroy(Request $request, Space $space, SpacePhoto $photo): JsonResponse
    {
        if ($space->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        Storage::disk('public')->delete($photo->file_path);
        $photo->delete();

        return response()->json(['message' => 'Photo deleted.']);
    }
}
