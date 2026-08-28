<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * design.json WALK-6 — "recorrido humano de entrada: login, menu y colaboradores".
 *
 * A walkthrough is a PERSON driving the real screens, and it stays that. This file is the
 * second net under it: the two claims WALK-6 makes that nothing in the suite pinned, so a
 * regression between one walk and the next lands on a red test instead of on the next
 * person to walk it.
 *
 * WHAT IS ALREADY COVERED ELSEWHERE — do not restate it here:
 *   - register/login/me all answer the account context, and agree ....... AuthAccountContextTest
 *   - `is_owner` is true from the register response (W6-1) ............... AuthAccountContextTest
 *   - accepting an invitation fills `collaborating_on` (W6-3) ............ CollaborationInviteFlowTest
 *   - a collaborator cannot unlink themselves, 409 not 404 (W6-4) ........ AccountScopeTest
 *   - there is no DELETE /admin/users/{id}, 405 (W6-5) ................... AuditWritersTest,
 *                                                                         AccountDeletionGuardrailsTest
 *   - deleting a booked listing is 409 with a blockers[] list (W6-6) ..... SpaceDeletionGuardTest
 *
 * WHAT WAS MISSING, and is here:
 *   1. W6-3's MIXED signal — owner of my own account AND collaborator on yours, both true
 *      in the same response, after a real accept through the endpoint. The pieces were
 *      each tested apart; the combination is the only case that tells the two signals
 *      apart, which is exactly why EH-11 is written about it.
 *   2. W6-5's actual admin capability — deactivate and reactivate. The suite pinned the
 *      absence of the delete route twice over and the presence of its replacement zero
 *      times, so "the admin can still take somebody off the platform" rested on nothing.
 */
class Walk6EntryFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    // ── W6-3 · EH-11: the mixed case ──────────────────────────────────────────

    /**
     * The person who registered their own account and then accepted an invitation into
     * somebody else's is BOTH things at once, and the menu needs both facts:
     * `is_owner` keeps their own Collaborators tab, `collaborating_on` opens the other
     * account. A UI that hangs the tab on the wrong one of the two looks correct for
     * every user except this one — and this one is the normal case the day two clients
     * help each other out.
     */
    public function test_a_collaborator_keeps_being_the_owner_of_their_own_account(): void
    {
        $owner = User::factory()->create(['role' => 'client']);

        // Registered through the front door, so their own account is real, not a fixture.
        $helper = $this->postJson('/api/register', [
            'name' => 'Ana Ayuda',
            'email' => 'ana.ayuda@x.test',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'role' => 'client',
        ])->assertStatus(201);

        $ownAccount = $helper->json('account_id');
        $this->assertNotSame($owner->account_id, $ownAccount);

        Sanctum::actingAs($owner);
        $invitation = $this->postJson('/api/collaborators', [
            'email' => 'ana.ayuda@x.test',
            'role' => 'manager',
        ])->assertStatus(201)->json('id');

        $helperUser = User::where('email', 'ana.ayuda@x.test')->firstOrFail();
        Sanctum::actingAs($helperUser);
        $this->postJson("/api/collaborations/{$invitation}/accept")->assertStatus(200);

        // BOTH signals, in the same payload. Note `account_id` still points at their OWN
        // account: collaborating somewhere else does not move you there.
        $me = $this->getJson('/api/me')->assertStatus(200);
        $me->assertJsonPath('is_owner', true);
        $me->assertJsonPath('account_id', $ownAccount);
        $me->assertJsonPath('collaborating_on', [$owner->account_id]);

        // …and the same on the response that opens the session, or the tab would only
        // appear after a full reload — which is the bug WALK-6 exists to catch.
        $login = $this->postJson('/api/login', [
            'email' => 'ana.ayuda@x.test',
            'password' => 'secret-pass',
        ])->assertStatus(200);

        $login->assertJsonPath('is_owner', true);
        $login->assertJsonPath('account_id', $ownAccount);
        $login->assertJsonPath('collaborating_on', [$owner->account_id]);
    }

    /**
     * The owner's own view of the same fact: their Collaborators list shows the person,
     * `accepted`, and the owner is not suddenly "collaborating on" their own account.
     * (An owner appearing in their own `collaborating_on` would put a second copy of
     * their account in their own menu.)
     */
    public function test_the_owner_is_not_a_collaborator_on_their_own_account(): void
    {
        $owner = User::factory()->create(['role' => 'client']);
        $helper = User::factory()->create(['role' => 'client']);

        Sanctum::actingAs($owner);
        $invitation = $this->postJson('/api/collaborators', [
            'email' => $helper->email,
            'role' => 'manager',
        ])->assertStatus(201)->json('id');

        Sanctum::actingAs($helper);
        $this->postJson("/api/collaborations/{$invitation}/accept")->assertStatus(200);

        Sanctum::actingAs($owner);
        $this->getJson('/api/me')
            ->assertStatus(200)
            ->assertJsonPath('is_owner', true)
            ->assertJsonPath('collaborating_on', []);

        $this->getJson('/api/collaborators')
            ->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.status', 'accepted');
    }

    // ── W6-5 · the capability the admin actually has ──────────────────────────

    /**
     * §2/§12 (owner 2026-08-03) — "el admin sólo puede quitar usuarios de sus roles, no
     * eliminar". The suite already pins the DELETE route's absence (405, twice). This
     * pins the half that replaces it, because a screen whose only button is a deactivate
     * that silently no-ops is worse than the delete it replaced: the admin believes the
     * person is out.
     *
     * The two requests are exactly the ones the Users screen fires per row — the paginated
     * index and PUT `{is_active}` — so a 404 here is EH-10 (a button pointing at nothing),
     * which is the thing W6-5 asks the walker to watch the network console for.
     */
    public function test_the_admin_deactivates_and_reactivates_instead_of_deleting(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = User::factory()->create([
            'role' => 'client',
            'email' => 'fuera@x.test',
            'password' => Hash::make('secret-pass'),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users?page=1')->assertStatus(200)->assertJsonStructure(['data']);
        $this->getJson("/api/admin/users/{$client->id}")->assertStatus(200);

        $this->putJson("/api/admin/users/{$client->id}", ['is_active' => false])
            ->assertStatus(200)
            ->assertJsonPath('is_active', false);

        $this->assertFalse((bool) $client->fresh()->is_active);

        // The switch has to reach the door, or it is decoration.
        $this->postJson('/api/login', ['email' => 'fuera@x.test', 'password' => 'secret-pass'])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'AUTH_ACCOUNT_DEACTIVATED');

        // Reversible, which is the whole reason it is not a delete: nothing was destroyed,
        // so the person comes back with their campaigns, bookings and money intact.
        Sanctum::actingAs($admin);
        $this->putJson("/api/admin/users/{$client->id}", ['is_active' => true])
            ->assertStatus(200)
            ->assertJsonPath('is_active', true);

        $this->postJson('/api/login', ['email' => 'fuera@x.test', 'password' => 'secret-pass'])
            ->assertStatus(200)
            ->assertJsonPath('is_owner', true);

        $this->assertDatabaseHas('users', ['id' => $client->id, 'is_active' => true]);
    }

    /**
     * BR-10 in the one direction PlanningCodeCongruenceTest cannot see. That file proves the
     * URL the UI builds resolves to a route; it says nothing about the SHAPE that comes back,
     * and W6-5 fell into exactly that gap: `GET /admin/users/{id}` answered 200, the walker
     * got a spinner that never stopped, and the console said
     *
     *     TypeError: Cannot read properties of undefined (reading 'name')
     *
     * The edit screen was typed `http.get<{ user: User }>` — the envelope POST /login uses —
     * while Admin\UserController::show() returns the model bare, like store(), update() and
     * every row of index()'s paginator. `res.user` was undefined, reading `.name` threw
     * INSIDE the subscriber, so `loading` never cleared: a green request and a dead screen.
     *
     * So this test does not restate the controller. It reads the shape the screen declares
     * and the fields the screen assigns straight out of the component source, and holds the
     * live response to them. It fails whichever side moves — re-wrap the endpoint without
     * telling the form, or re-type the form without telling the endpoint — which is the only
     * way "the UI goes hand in hand with the endpoints" is a check and not a hope.
     */
    public function test_the_user_edit_screen_reads_the_shape_the_api_actually_returns(): void
    {
        $component = base_path('../frontend/src/app/features/admin/users/user-form.component.ts');

        if (! is_file($component)) {
            $this->markTestSkipped('No frontend checked out next to the backend.');
        }

        $source = file_get_contents($component);

        $this->assertSame(1, preg_match(
            '/this\.http\.get<(?<shape>[^>]+)>\(\s*`\$\{this\.api\}\/admin\/users\/\$\{this\.userId\}`/',
            $source,
            $call
        ), 'The edit screen no longer loads the user the way this test recognises, so the test is '
         . 'no longer checking anything. Re-read user-form.component.ts and re-point it.');

        // `this.email = user.email` — the fields the screen will actually reach for. Deriving
        // them beats listing them: a field added to the form is covered the day it is added.
        preg_match_all('/this\.\w+ = user\.(?<field>\w+)/', $source, $reads);
        $fields = array_values(array_unique($reads['field']));
        $this->assertGreaterThan(3, count($fields), 'Too few field reads found — see above.');

        $admin = User::factory()->create(['role' => 'admin']);
        $client = User::factory()->create(['role' => 'client']);

        Sanctum::actingAs($admin);
        $payload = $this->getJson("/api/admin/users/{$client->id}")->assertStatus(200)->json();

        // `{ user: User }` declares an envelope; `User` declares the model bare. Either is a
        // fine contract — what is not fine is the two ends holding different ones.
        $root = $payload;

        if (preg_match('/^\{\s*(?<key>\w+)\s*:/', trim($call['shape']), $envelope)) {
            $this->assertArrayHasKey($envelope['key'], $payload,
                "The screen unwraps `res.{$envelope['key']}`, which the endpoint does not send. "
                . 'Every read off it throws inside the subscriber, so the spinner never stops. '
                . 'Top-level keys served: ' . implode(', ', array_keys($payload)));
            $root = $payload[$envelope['key']];
        }

        foreach ($fields as $field) {
            $this->assertArrayHasKey($field, $root,
                "The screen reads `user.{$field}`, which this response has no field for (EH-2).");
        }
    }

    // ── W6-4 · the walk step, driven the way the walk drives it ───────────────

    /**
     * The refusal (409, BR-15) is pinned in AccountScopeTest. What was NOT pinned is the
     * SHAPE OF THE STEP, and that gap cost a walkthrough: WALK-6 told the walker to
     * `DELETE /api/collaborators/$MI_PROPIA_FILA` without ever giving the command
     * that fills `$MI_PROPIA_FILA`. With the variable empty the URL collapses to the
     * COLLECTION URI, which exists for GET and POST and not for DELETE, so the walker got
     *
     *     HTTP/1.1 405 Method Not Allowed   (allow: GET, HEAD, POST)
     *
     * and read it as "the guardrail is missing". It was not: the request never reached the
     * controller. A 405 from a routing table is indistinguishable, at the walker's end,
     * from a rule that was never written — so this test pins both halves together, in
     * order: the id has to come from somewhere (GET /api/collaborations, the only place a
     * collaborator can see their own grant), and only then does the refusal exist.
     *
     * The empty-variable case is asserted too, so that if the collection URI ever gains a
     * DELETE the walk's failure mode changes loudly here instead of quietly there.
     */
    public function test_the_collaborator_finds_their_own_row_before_being_refused_by_it(): void
    {
        $owner = User::factory()->create(['role' => 'client']);
        $helper = User::factory()->create(['role' => 'client']);

        Sanctum::actingAs($owner);
        $this->postJson('/api/collaborators', [
            'email' => $helper->email,
            'role' => 'manager',
        ])->assertStatus(201);

        Sanctum::actingAs($helper);

        // Step 1 — the command the walk was missing. The collaborator never sees
        // /collaborators (that lists THEIR OWN account's helpers, which is empty); the
        // grant they hold on somebody else's account only shows up here.
        $mine = $this->getJson('/api/collaborations')->assertStatus(200)->assertJsonCount(1);
        $id = $mine->json('0.id');
        $this->assertIsInt($id);

        $this->postJson("/api/collaborations/{$id}/accept")->assertStatus(200);

        // Step 2 — with a real id, the guardrail answers, and it answers about STATE.
        $this->deleteJson("/api/collaborators/{$id}")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'CONFLICTING_STATE');

        // …and with the id the walk forgot to fetch, the router answers first: 405 on the
        // collection, which says nothing at all about collaborations.
        $this->deleteJson('/api/collaborators/')->assertStatus(405);
    }
}
