<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Booking;
use App\Models\Campaign;
use App\Models\Chat;
use App\Models\ChatObject;
use App\Models\Space;
use App\Models\SpaceAvailability;
use App\Models\SpacePhoto;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * §21 rule 2 (BR-3) — "not yours" and "does not exist" are the SAME answer.
 *
 * A 403 on a row-addressed endpoint confirms the row exists, and that is all an
 * attacker needs: walk the id space, collect 403s, and you have an inventory of another
 * account's campaigns, bookings, listings and disputes without ever reading one.
 *
 * The bug this file exists to prevent is not "a 403 somewhere". It is a surface that
 * answers 404 through one door and 403 through another, because then the fix looks done
 * from wherever the auditor happened to knock. So every case below is asserted through
 * EVERY verb the object has, and paired with a nonexistent id that must produce the
 * identical status.
 *
 * The 403s that are CORRECT are pinned here too (bottom half). They are the ones that
 * name no row — role gates, permission gates, and "you can see this but you may not do
 * that" — and losing them to an over-eager sweep would be its own regression.
 */
class ExistenceLeakTest extends TestCase
{
    use RefreshDatabase;

    /** An id no row will ever have, for the "indistinguishable" half of each pair. */
    private const GHOST = 987654321;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function spaceOf(User $provider): Space
    {
        return Space::create([
            'user_id' => $provider->id,
            'name' => 'Barda Centro',
            'latitude' => 25.6597,
            'longitude' => -100.4023,
            'price_per_day' => 100.00,
        ]);
    }

    // ── The chain root: campaigns ────────────────────────────────────────────

    /**
     * The campaign was the loudest leak in the API and the best hidden: the NESTED
     * routes 404ed (AuthorizesOwnershipChain) while the campaign itself 403ed, so
     * OwnershipChainTest::test_foreign_campaign_is_404_not_403 passed while the root
     * it was named after still leaked.
     */
    public function test_a_foreign_campaign_is_404_through_every_verb(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $stranger = User::factory()->create(['role' => 'client']);
        $theirs = Campaign::create(['user_id' => $stranger->id, 'name' => 'Theirs', 'status' => 'active']);

        // The caller's OWN campaign first: proves the route, the role gate and the
        // permission gate all pass, so the 404s below can only come from the ownership
        // check and not from a mistyped path quietly passing this test.
        $mine = Campaign::create(['user_id' => $client->id, 'name' => 'Mine', 'status' => 'active']);

        Sanctum::actingAs($client);
        $this->getJson("/api/client/campaigns/{$mine->id}")->assertStatus(200);

        foreach ([$theirs->id, self::GHOST] as $id) {
            $this->getJson("/api/client/campaigns/{$id}")->assertStatus(404);
            $this->putJson("/api/client/campaigns/{$id}", ['name' => 'Mine now'])->assertStatus(404);
            $this->deleteJson("/api/client/campaigns/{$id}")->assertStatus(404);
        }

        $this->assertSame('Theirs', $theirs->fresh()->name);
    }

    // ── Bookings: the same row, both sides ───────────────────────────────────

    public function test_a_foreign_booking_is_404_for_a_stranger_client(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $stranger = User::factory()->create(['role' => 'client']);
        $provider = User::factory()->create(['role' => 'provider']);

        $booking = Booking::create([
            'client_user_id' => $stranger->id,
            'space_id' => $this->spaceOf($provider)->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'total_price' => 300.00,
            'status' => 'confirmed',
        ]);

        Sanctum::actingAs($client);

        foreach ([$booking->id, self::GHOST] as $id) {
            $this->getJson("/api/client/bookings/{$id}")->assertStatus(404);
            $this->putJson("/api/client/bookings/{$id}", ['status' => 'cancelled'])->assertStatus(404);
        }

        // And the provider side of the very same row answers the same way, so the leak
        // cannot be reached by switching prefix.
        $otherProvider = User::factory()->create(['role' => 'provider']);
        Sanctum::actingAs($otherProvider);
        $this->putJson("/api/provider/bookings/{$booking->id}", ['status' => 'confirmed'])->assertStatus(404);

        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    // ── Listing sub-resources: photos + availabilities ───────────────────────

    /**
     * SpaceController::show()/update() already answered 404. Its own sub-resources did
     * not — so `GET /provider/spaces/{s}/availabilities` still confirmed, for any id,
     * whether that listing existed.
     */
    public function test_a_foreign_listings_sub_resources_are_404(): void
    {
        Storage::fake('public');

        $provider = User::factory()->create(['role' => 'provider']);
        $stranger = User::factory()->create(['role' => 'provider']);
        $theirs = $this->spaceOf($stranger);
        $mine = $this->spaceOf($provider);

        Sanctum::actingAs($provider);

        // Same guard as above: the owned listing must really answer 200 on these paths,
        // or the 404s below would prove nothing but a typo.
        $this->getJson("/api/provider/spaces/{$mine->id}/availabilities")->assertStatus(200);
        $this->postJson("/api/provider/spaces/{$mine->id}/photos", [
            'photo' => UploadedFile::fake()->image('shot.jpg'),
        ])->assertStatus(201);

        foreach ([$theirs->id, self::GHOST] as $id) {
            $this->getJson("/api/provider/spaces/{$id}/availabilities")->assertStatus(404);
            $this->postJson("/api/provider/spaces/{$id}/availabilities", [
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDay()->toDateString(),
            ])->assertStatus(404);
            $this->postJson("/api/provider/spaces/{$id}/sync-ical")->assertStatus(404);
            $this->postJson("/api/provider/spaces/{$id}/import-ical", [
                'file' => UploadedFile::fake()->create('cal.ics', 1),
            ])->assertStatus(404);
            $this->postJson("/api/provider/spaces/{$id}/photos", [
                'photo' => UploadedFile::fake()->image('shot.jpg'),
            ])->assertStatus(404);
        }

        $this->assertSame(0, $theirs->availabilities()->count());
        $this->assertSame(0, $theirs->photos()->count());
    }

    /**
     * Neither sub-resource route is ->scopeBindings(), so `{photo}` and `{availability}`
     * were resolved independently of `{space}`: pairing a listing you DO own with a
     * child id you do not was a delete on someone else's row, and a probe for whether
     * that row existed.
     */
    public function test_a_foreign_child_paired_with_an_owned_listing_is_404(): void
    {
        $provider = User::factory()->create(['role' => 'provider']);
        $stranger = User::factory()->create(['role' => 'provider']);

        $mine = $this->spaceOf($provider);
        $theirs = $this->spaceOf($stranger);

        $theirPhoto = SpacePhoto::create([
            'space_id' => $theirs->id,
            'file_path' => 'space_photos/theirs.jpg',
            'file_name' => 'theirs.jpg',
        ]);
        $theirSlot = SpaceAvailability::create([
            'space_id' => $theirs->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'status' => 'available',
        ]);

        $myPhoto = SpacePhoto::create([
            'space_id' => $mine->id,
            'file_path' => 'space_photos/mine.jpg',
            'file_name' => 'mine.jpg',
        ]);

        Sanctum::actingAs($provider);

        // The owned pairing works…
        $this->deleteJson("/api/provider/spaces/{$mine->id}/photos/{$myPhoto->id}")->assertStatus(200);

        // …the cross pairing does not, and says nothing about what it found.
        $this->deleteJson("/api/provider/spaces/{$mine->id}/photos/{$theirPhoto->id}")->assertStatus(404);
        $this->deleteJson("/api/provider/spaces/{$mine->id}/availabilities/{$theirSlot->id}")->assertStatus(404);

        // Nothing of the stranger's moved.
        $this->assertNotNull($theirPhoto->fresh());
        $this->assertNotNull($theirSlot->fresh());
    }

    // ── Chats: the sharpest surface ──────────────────────────────────────────

    private function chatBetween(?User $client, ?User $provider): Chat
    {
        return Chat::create([
            'opened_by_user_id' => ($client ?? $provider)->id,
            'client_user_id' => $client?->id,
            'provider_user_id' => $provider?->id,
            'status' => Chat::STATUS_OPEN,
        ]);
    }

    public function test_an_unreachable_chat_is_404_through_every_verb(): void
    {
        $outsider = User::factory()->create(['role' => 'client']);
        $client = User::factory()->create(['role' => 'client']);
        $provider = User::factory()->create(['role' => 'provider']);

        $chat = $this->chatBetween($client, $provider);
        $object = ChatObject::create([
            'chat_id' => $chat->id,
            'objectable_type' => (new Campaign)->getMorphClass(),
            'objectable_id' => Campaign::create(['user_id' => $client->id, 'name' => 'C', 'status' => 'active'])->id,
            'attached_by_user_id' => $client->id,
        ]);

        // The chat really is reachable — by its own client. Anything the outsider gets
        // below is therefore the ACL speaking, not a broken route.
        Sanctum::actingAs($client);
        $this->getJson("/api/chats/{$chat->id}")->assertStatus(200);

        Sanctum::actingAs($outsider);

        foreach ([$chat->id, self::GHOST] as $id) {
            $this->getJson("/api/chats/{$id}")->assertStatus(404);
            $this->postJson("/api/chats/{$id}/messages", ['body' => 'hi'])->assertStatus(404);
            $this->postJson("/api/chats/{$id}/objects", ['object_type' => 'campaign', 'object_id' => 1])->assertStatus(404);
            $this->postJson("/api/chats/{$id}/flags", ['type' => 'refund'])->assertStatus(404);
            $this->postJson("/api/chats/{$id}/close")->assertStatus(404);
            $this->postJson("/api/chats/{$id}/resolve")->assertStatus(404);
        }

        // Detach: the chat_object id is real, it simply hangs off a chat the caller
        // cannot reach — which must not be distinguishable from "no such object".
        $this->deleteJson("/api/chats/{$chat->id}/objects/{$object->id}")->assertStatus(404);
        $this->assertNotNull($object->fresh());
        $this->assertSame(Chat::STATUS_OPEN, $chat->fresh()->status);
    }

    /**
     * `{chat}` is an implicit route binding, so a nonexistent id is answered by the ROUTER
     * with 404 and never reaches the controller. Any refusal the controller invents for a
     * real-but-unreachable chat must therefore be 404 too, or the pair of answers is the
     * oracle: 404 = no such chat, anything else = it is there.
     *
     * `resolve` had exactly that shape — it asked "are you staff?" (403) before "can you
     * see this chat?" — so it is pinned from both sides here.
     */
    public function test_a_real_unreachable_chat_answers_exactly_what_a_missing_one_does(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $provider = User::factory()->create(['role' => 'provider']);
        $support = User::factory()->create(['role' => 'support']);
        $chat = $this->chatBetween($client, $provider);

        // A client is not staff AND cannot see this chat: both answers must be the ghost's.
        $outsider = User::factory()->create(['role' => 'client']);
        Sanctum::actingAs($outsider);
        $this->assertSame(
            $this->postJson('/api/chats/' . self::GHOST . '/resolve')->getStatusCode(),
            $this->postJson("/api/chats/{$chat->id}/resolve")->getStatusCode(),
        );

        // Support has the role but has not joined this client↔provider chat, so it is not
        // on its side of the wall either.
        Sanctum::actingAs($support);
        $this->postJson("/api/chats/{$chat->id}/resolve")->assertStatus(404);

        // …and once joined, the same call works — proving the 404 above was the ACL.
        $this->postJson("/api/chats/{$chat->id}/join")->assertStatus(200);
        $this->postJson("/api/chats/{$chat->id}/resolve")->assertStatus(200);
        $this->assertSame(Chat::STATUS_RESOLVED, $chat->fresh()->status);
    }

    /**
     * The attach endpoint takes a raw {object_type, object_id} pair from the caller, so
     * "you cannot attach that" and "there is no such object" have to be the same
     * sentence — otherwise it is an existence oracle over every type in the enum.
     */
    public function test_attaching_a_foreign_object_is_indistinguishable_from_a_missing_one(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $stranger = User::factory()->create(['role' => 'client']);
        $foreign = Campaign::create(['user_id' => $stranger->id, 'name' => 'Theirs', 'status' => 'active']);

        Sanctum::actingAs($client);
        $chatId = $this->postJson('/api/chats', ['body' => 'help'])->assertStatus(201)->json('id');

        foreach ([$foreign->id, self::GHOST] as $id) {
            $this->postJson("/api/chats/{$chatId}/objects", ['object_type' => 'campaign', 'object_id' => $id])
                ->assertStatus(404)
                ->assertJsonPath('message', 'Object not found.');
        }

        // Same rule on the open-a-chat form, which accepts the same pair.
        foreach ([$foreign->id, self::GHOST] as $id) {
            $this->postJson('/api/chats', ['object_type' => 'campaign', 'object_id' => $id])
                ->assertStatus(404)
                ->assertJsonPath('message', 'Object not found.');
        }
    }

    /**
     * Proof upload addresses an ad by raw id in the BODY. ProofController::show() already
     * answered 404 on a proof outside the chain; store() answered 403 on an ad outside it,
     * so the same controller leaked or did not depending on the verb.
     */
    public function test_uploading_a_proof_against_a_foreign_ad_is_404(): void
    {
        Storage::fake('public');

        $stranger = User::factory()->create(['role' => 'provider']);
        $provider = User::factory()->create(['role' => 'provider']);
        $client = User::factory()->create(['role' => 'client']);

        $theirSpace = $this->spaceOf($stranger);
        $theirAd = Ad::create([
            'space_id' => $theirSpace->id,
            'provider_user_id' => $stranger->id,
            'name' => 'Their Ad',
            'media_type' => 'image',
            'status' => 'active',
        ]);
        $theirBooking = Booking::create([
            'client_user_id' => $client->id,
            'space_id' => $theirSpace->id,
            'ad_id' => $theirAd->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'total_price' => 300.00,
            'status' => 'waiting_proof',
        ]);

        Sanctum::actingAs($provider);

        $this->postJson('/api/provider/proofs', [
            'booking_id' => $theirBooking->id,
            'ad_id' => $theirAd->id,
            'file' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertStatus(404);

        $this->assertSame(0, $theirBooking->proofs()->count());
    }

    // ── The other half: 403s that are CORRECT and must survive ───────────────

    /**
     * A 403 is right exactly when it names no row: the caller either already knows the
     * object exists, or no object has been named at all.
     */
    public function test_the_403s_that_reveal_nothing_are_kept(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $provider = User::factory()->create(['role' => 'provider']);
        $support = User::factory()->create(['role' => 'support']);

        // 1. Role gate on a collection — no row named.
        Sanctum::actingAs($client);
        $this->getJson('/api/provider/spaces')->assertStatus(403);
        $this->getJson('/api/payments/payments')->assertStatus(403);

        // 2. Role statement on a create form — no row named.
        Sanctum::actingAs($support);
        $this->postJson('/api/chats', ['body' => 'hi'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Only clients and providers can open a chat.');

        // 3. "You can see it, you may not do it": the client reads this chat, so its
        //    existence is not news — only the staff-ness of the action is refused.
        $chat = $this->chatBetween($client, null);
        Sanctum::actingAs($client);
        $this->getJson("/api/chats/{$chat->id}")->assertStatus(200);
        $this->postJson("/api/chats/{$chat->id}/flags", ['type' => 'refund'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Only staff can raise a flag.');

        // 4. Same shape on close: the provider is a participant of this chat and did not
        //    open it. A stranger gets 404 (above); a participant gets the real reason.
        $shared = $this->chatBetween($client, $provider);
        Sanctum::actingAs($provider);
        $this->postJson("/api/chats/{$shared->id}/close")
            ->assertStatus(403)
            ->assertJsonPath('message', 'Only the opener or staff can close a chat.');
        $this->assertSame(Chat::STATUS_OPEN, $shared->fresh()->status);
    }

    /**
     * EH-14 at the layer where it actually lived. The controller sweep converted
     * eighteen refusals and left the role/permission middleware alone, because a role
     * check names no row. But `SubstituteBindings` runs first, so a phantom id dies in
     * the router with 404 while a REAL id reaches the middleware and used to come back
     * 403 — and the difference is the oracle.
     *
     * This asserts the PAIR, not a status. Asserting "404" alone would have passed
     * before the fix too, on the phantom half.
     */
    public function test_a_role_refusal_answers_the_same_for_a_real_id_and_a_phantom_one(): void
    {
        $space = $this->spaceOf(User::factory()->create(['role' => 'provider']));

        Sanctum::actingAs(User::factory()->create(['role' => 'client']));

        $real = $this->getJson("/api/provider/spaces/{$space->id}");
        $phantom = $this->getJson('/api/provider/spaces/999999');

        $this->assertSame(
            $phantom->getStatusCode(),
            $real->getStatusCode(),
            'A client gets a different status for a provider listing that exists than for one that '
            . 'does not, so subtracting the two enumerates every provider listing id (EH-14).'
        );
        $this->assertSame($phantom->json(), $real->json(), 'Same status, different body — still an oracle.');
        $this->assertSame(404, $real->getStatusCode());
    }

    /**
     * The other half of the same rule, and the reason this is not "return 404 everywhere":
     * a collection route names no row, so nothing can be enumerated from its refusal, and
     * 403 stays because it tells an honest caller what is actually wrong.
     */
    public function test_a_collection_route_still_refuses_with_403(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'client']));

        $this->getJson('/api/provider/spaces')->assertStatus(403);
    }
}
