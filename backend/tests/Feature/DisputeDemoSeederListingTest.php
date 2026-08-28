<?php

namespace Tests\Feature;

use App\Models\Space;
use App\Models\User;
use Database\Seeders\DisputeDemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * UC-8 · design.json §10 — the DisputeDemoSeeder listing is PUBLIC on purpose (it is the
 * published listing the seeded client↔provider chat hangs off, and that is the only entry
 * point into a provider chat), so it cannot be hidden from /client/spaces/search. What it
 * must not do is look like a leaked test fixture to a client browsing real inventory: on
 * 2026-08-27 the owner hit "Demo Dispute Billboard / 123 Main St" on the map and read it
 * as a bug. This pins the label, and pins that the row stays reachable from the search.
 */
class DisputeDemoSeederListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_demo_listing_is_searchable_but_not_named_like_a_fixture(): void
    {
        $this->seed(DisputeDemoSeeder::class);

        $space = Space::where('name', 'Espectacular Calzada del Valle')->first();
        $this->assertNotNull($space, 'The dispute demo listing should exist under its inventory name.');
        $this->assertTrue((bool) $space->is_active);
        $this->assertSame('billboard', $space->type);
        $this->assertNotNull($space->price_per_day);
        $this->assertSame(0, Space::where('name', 'like', 'Demo%')->count());

        // Still in the default client search (San Pedro, 20 km) — the UC-8 walkthrough needs it.
        $this->seed(RolePermissionSeeder::class);
        Sanctum::actingAs(User::where('email', 'client1@pubads.test')->firstOrFail());
        $this->getJson('/api/client/spaces/search?latitude=25.6597&longitude=-100.4023&radius_km=20')
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'Espectacular Calzada del Valle']);
    }

    public function test_rerun_renames_a_legacy_row_instead_of_duplicating_it(): void
    {
        $this->seed(DisputeDemoSeeder::class);

        // Simulate a database seeded before the rename, then re-seed: updateOrCreate is keyed
        // on (user_id, name), so without the heal step this would leave two billboards behind.
        $space = Space::where('name', 'Espectacular Calzada del Valle')->firstOrFail();
        $space->update(['name' => 'Demo Dispute Billboard', 'location_text' => '123 Main St']);

        $this->seed(DisputeDemoSeeder::class);

        $this->assertSame(0, Space::where('name', 'Demo Dispute Billboard')->count());
        $this->assertSame(1, Space::where('name', 'Espectacular Calzada del Valle')->count());
        $this->assertSame($space->id, Space::where('name', 'Espectacular Calzada del Valle')->value('id'));
    }
}
