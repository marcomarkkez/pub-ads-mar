<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Space;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpaceSearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'radius_km' => 'nullable|numeric|min:1|max:500',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'type' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        // UC-29 · §12 — the catalog honours ALL THREE levels of "off": the provider's
        // own pause, an admin takedown, and a frozen provider account ("freeze pauses
        // NEW bookings"). Space::scopeBookable() is the single place that knows this,
        // so a listing hidden by one level cannot resurface through another screen.
        $query = Space::bookable()->with(['photos', 'user', 'availabilities']);

        // Bounding-box geolocation filter
        if ($request->filled(['latitude', 'longitude'])) {
            $lat = $request->latitude;
            $lng = $request->longitude;
            $radius = $request->input('radius_km', 10);

            // ~1 degree latitude = 111 km
            $latDelta = $radius / 111.0;
            // ~1 degree longitude varies by latitude
            $lngDelta = $radius / (111.0 * cos(deg2rad($lat)));

            $query->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
                  ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta]);
        }

        if ($request->filled('min_price')) {
            $query->where('price_per_day', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price_per_day', '<=', $request->max_price);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $spaces = $query->latest()->paginate(20);

        // P2: when a date range is provided, annotate each space with an
        // `available` boolean (true when an available range coincides). Spaces
        // that do not coincide are NOT hidden — the frontend renders red/green.
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $from = $request->date_from;
            $to = $request->date_to;

            $spaces->getCollection()->transform(function ($space) use ($from, $to) {
                $space->available = $space->availabilities
                    ->where('status', 'available')
                    ->contains(fn ($a) => $a->start_date <= $to && $a->end_date >= $from);

                return $space;
            });
        }

        return response()->json($spaces);
    }
}
