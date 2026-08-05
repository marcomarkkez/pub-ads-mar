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
            // 404, never 403 — §21 rule 2 (BR-3): a 403 confirms the row exists, which is
            // enough to enumerate another account's ids. "Not yours" and "does not exist"
            // must be indistinguishable to a stranger. SpaceController::show() already
            // 404s on the same listing; a 403 here made the leak depend on which door
            // the caller knocked at.
            return response()->json(['message' => 'Not found.'], 404);
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
        // 404, never 403 — §21 rule 2 (BR-3): a 403 confirms the row exists, which is
        // enough to enumerate another account's ids. "Not yours" and "does not exist"
        // must be indistinguishable to a stranger.
        //
        // The photo link is checked too, and for the same reason: this route is not
        // ->scopeBindings(), so `{photo}` was resolved independently of `{space}` and a
        // provider could delete a FOREIGN photo by pairing it with a listing of their
        // own. Checking only the parent is the chain break §21 rule 1 exists to stop.
        if ($space->user_id !== $request->user()->id || $photo->space_id !== $space->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        Storage::disk('public')->delete($photo->file_path);
        $photo->delete();

        return response()->json(['message' => 'Photo deleted.']);
    }
}
