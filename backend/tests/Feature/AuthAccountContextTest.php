<?php

namespace Tests\Feature;

use App\Models\Collaborator;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * design.json §3 (AC-accounts-01/02, owner 2026-08-04) — the account context
 * (`account_id`, `is_owner`, `collaborating_on`) is ONE fact, so the three endpoints
 * that open a session must all state it and must all state the same thing.
 *
 * THE BUG THIS PINS: only `/me` carried it. A user who had just logged in or just
 * registered got a token and no idea whether they owned an account, so the sidebar
 * could not decide whether to show the Collaborators tab and it only appeared after a
 * full page reload sent the app through `/me`. The three-copies alternative is worse
 * than the missing keys were: two of the three lie the day "owner" changes meaning.
 */
class AuthAccountContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /** The three keys /me answers with — the contract the other two must match. */
    private const CONTEXT_KEYS = ['account_id', 'is_owner', 'collaborating_on'];

    public function test_a_freshly_registered_client_is_an_owner_in_the_register_response(): void
    {
        // §3 — "un dueño es la persona que abre la cuenta". Registering IS opening it,
        // so `is_owner` is true on the very first response, not after a reload.
        $response = $this->postJson('/api/register', [
            'name' => 'Ana Nueva',
            'email' => 'ana.nueva@x.test',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'role' => 'client',
        ])->assertStatus(201);

        $response->assertJsonPath('is_owner', true);
        $response->assertJsonPath('collaborating_on', []);
        $this->assertNotNull($response->json('account_id'));

        // The account is a real row and the user actually points at it.
        $user = User::where('email', 'ana.nueva@x.test')->firstOrFail();
        $this->assertSame($response->json('account_id'), $user->account_id);
        $this->assertNotNull($user->account);
        $this->assertSame('client', $user->account->type);
    }

    public function test_a_freshly_registered_provider_is_an_owner_too(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Vallas del Sur',
            'email' => 'vallas@x.test',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'role' => 'provider',
            'company_name' => 'Vallas del Sur SA',
        ])->assertStatus(201);

        $response->assertJsonPath('is_owner', true);

        $user = User::where('email', 'vallas@x.test')->firstOrFail();
        $this->assertSame('provider', $user->account->type);
        // The account takes the company name when there is one — it is the account's
        // name, not the human's.
        $this->assertSame('Vallas del Sur SA', $user->account->name);
    }

    public function test_register_login_and_me_agree_for_the_same_user(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Ana Nueva',
            'email' => 'ana.nueva@x.test',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'role' => 'client',
        ])->assertStatus(201);

        $registerContext = $this->contextOf($this->postJson('/api/register', [
            'name' => 'Otro Cliente',
            'email' => 'otro@x.test',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'role' => 'client',
        ])->assertStatus(201)->json());

        $login = $this->postJson('/api/login', ['email' => 'otro@x.test', 'password' => 'secret-pass'])
            ->assertStatus(200);

        $me = $this->withToken($login->json('token'))->getJson('/api/me')->assertStatus(200);

        $this->assertSame($registerContext, $this->contextOf($login->json()));
        $this->assertSame($registerContext, $this->contextOf($me->json()));
    }

    public function test_login_reports_the_accounts_this_user_collaborates_on(): void
    {
        // Owning my account and helping run yours are two different facts, and the
        // sidebar needs both of them before the first reload.
        $owner = User::factory()->create(['role' => 'client']);
        $helper = User::factory()->create(['role' => 'client', 'email' => 'helper@x.test']);

        Collaborator::create([
            'account_id' => $owner->account_id,
            'invited_by_user_id' => $owner->id,
            'user_id' => $helper->id,
            'email' => $helper->email,
            'role' => 'manager',
            'status' => 'accepted',
        ]);

        $login = $this->postJson('/api/login', ['email' => 'helper@x.test', 'password' => 'password'])
            ->assertStatus(200);

        // Owner of their OWN account and a collaborator on someone else's — both true.
        $login->assertJsonPath('is_owner', true);
        $login->assertJsonPath('collaborating_on', [$owner->account_id]);

        $me = $this->withToken($login->json('token'))->getJson('/api/me')->assertStatus(200);
        $this->assertSame($this->contextOf($login->json()), $this->contextOf($me->json()));
    }

    public function test_a_pending_or_revoked_grant_is_not_a_collaboration(): void
    {
        // A pending invitation grants nothing yet and a revoked one granted something
        // once; neither should put an account in somebody's menu.
        $owner = User::factory()->create(['role' => 'client']);
        $invited = User::factory()->create(['role' => 'client', 'email' => 'invited@x.test']);
        $former = User::factory()->create(['role' => 'client', 'email' => 'former@x.test']);

        foreach ([[$invited, 'pending'], [$former, 'revoked']] as [$user, $status]) {
            Collaborator::create([
                'account_id' => $owner->account_id,
                'invited_by_user_id' => $owner->id,
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => 'manager',
                'status' => $status,
            ]);

            $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
                ->assertStatus(200)
                ->assertJsonPath('collaborating_on', []);
        }
    }

    public function test_staff_are_never_owners(): void
    {
        // accounts.type is client|provider — staff carry account_id NULL, so `is_owner`
        // is false and stays false. This is the line that changes if staff ever get one.
        foreach (['admin', 'support', 'payments'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $login = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
                ->assertStatus(200);

            $login->assertJsonPath('is_owner', false);
            $login->assertJsonPath('account_id', null);

            $me = $this->withToken($login->json('token'))->getJson('/api/me')->assertStatus(200);
            $this->assertSame($this->contextOf($login->json()), $this->contextOf($me->json()));
        }
    }

    public function test_the_context_comes_from_the_user_model_so_there_is_one_definition(): void
    {
        // If a fourth endpoint ever needs the context, it calls this — not a fourth copy.
        $user = User::factory()->create(['role' => 'client']);

        $login = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(200);

        $this->assertSame(
            $this->contextOf($user->fresh()->accountContext()),
            $this->contextOf($login->json()),
        );
    }

    /** @param array<string, mixed> $payload */
    private function contextOf(array $payload): array
    {
        $context = [];

        foreach (self::CONTEXT_KEYS as $key) {
            $this->assertArrayHasKey($key, $payload, "The response is missing the account context key '{$key}'.");

            $value = $payload[$key];
            // /me and /login serialize `collaborating_on` to a JSON array while the model
            // hands back a Collection — compare the values, not the container.
            $context[$key] = $value instanceof \Illuminate\Support\Collection ? $value->all() : $value;
        }

        return $context;
    }
}
