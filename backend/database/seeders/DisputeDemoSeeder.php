<?php

namespace Database\Seeders;

use App\Models\Ad;
use App\Models\Booking;
use App\Models\Chat;
use App\Models\ChatFlag;
use App\Models\ChatParticipant;
use App\Models\Payment;
use App\Models\Proof;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * UC-7/UC-8/UC-9/UC-35 · design.md §7/§10 — a ready-made set of chats so a human can
 * walk the unified Chats/Objetos/Flags flow across roles. Produces:
 *  - a client↔provider chat (PII-masking demo);
 *  - an objectless client↔support chat (contact support, UC-9);
 *  - a held-payment DISPUTE: payments.status=held + 3 chats (Support↔Client / ↔Provider
 *    / ↔Payments) each attaching payment+booking with an active payment_held flag;
 *  - one resolved chat and one closed chat (Abierto/Cerrado + admin-reopen + hidden history).
 *
 * Idempotent: guarded so a re-run does not pile up.
 */
class DisputeDemoSeeder extends Seeder
{
    public function run(): void
    {
        $client = $this->user('client1@pubads.test', 'Demo Client', 'client');
        $provider = $this->user('provider1@pubads.test', 'Demo Provider', 'provider');
        $this->user('support@pubads.test', 'Demo Support', 'support');
        $this->user('payments@pubads.test', 'Demo Payments', 'payments');

        $space = Space::firstOrCreate(
            ['user_id' => $provider->id, 'name' => 'Demo Dispute Billboard'],
            ['latitude' => 25.6597, 'longitude' => -100.4023, 'location_text' => '123 Main St', 'is_active' => true]
        );

        $ad = Ad::firstOrCreate(
            ['space_id' => $space->id, 'name' => 'Demo Dispute Ad'],
            ['provider_user_id' => $provider->id, 'media_type' => 'image', 'status' => 'active']
        );

        $booking = Booking::firstOrCreate(
            ['client_user_id' => $client->id, 'space_id' => $space->id, 'ad_id' => $ad->id, 'start_date' => '2026-07-01'],
            ['end_date' => '2026-07-31', 'total_price' => 500, 'status' => 'waiting_proof']
        );

        $payment = Payment::updateOrCreate(['booking_id' => $booking->id], ['amount' => 500, 'status' => 'held']);

        Proof::updateOrCreate(
            ['booking_id' => $booking->id, 'ad_id' => $ad->id],
            [
                'uploaded_by_user_id' => $provider->id,
                'media_type' => 'image',
                'file_path' => 'proofs/demo.jpg',
                'file_name' => 'demo.jpg',
                'status' => 'client_rejected',
                'notes' => 'rejected by client: ad was not displayed (demo)',
            ]
        );

        // 1) A plain client↔provider chat (masking demo), with the space attached.
        $this->ensureChat('demo-direct', [
            'opened_by_user_id' => $client->id,
            'client_user_id' => $client->id,
            'provider_user_id' => $provider->id,
        ], function (Chat $chat) use ($client, $provider, $space) {
            $chat->participants()->create(['user_id' => $client->id, 'side' => ChatParticipant::SIDE_CLIENT, 'joined_at' => now()]);
            $chat->participants()->create(['user_id' => $provider->id, 'side' => ChatParticipant::SIDE_PROVIDER, 'joined_at' => now()]);
            $this->attach($chat, $space, $client->id);
            $this->message($chat, $client->id, 'Hola, ¿está disponible en julio? Mi cel es 555-123-4567.', 'user');
        });

        // 2) An objectless client↔support chat (contact support, UC-9).
        $this->ensureChat('demo-support', [
            'opened_by_user_id' => $client->id,
            'client_user_id' => $client->id,
            'provider_user_id' => null,
        ], function (Chat $chat) use ($client) {
            $chat->participants()->create(['user_id' => $client->id, 'side' => ChatParticipant::SIDE_CLIENT, 'joined_at' => now()]);
            $this->message($chat, $client->id, '¿Cómo cambio mi método de pago?', 'user');
        });

        // 3) The held-payment dispute — 3 chats, each with payment_held flag + objects.
        $sets = [
            ['client' => null, 'provider' => null, 'side' => null, 'key' => 'demo-dispute-internal'],
            ['client' => $client->id, 'provider' => null, 'side' => ChatParticipant::SIDE_CLIENT, 'key' => 'demo-dispute-client'],
            ['client' => null, 'provider' => $provider->id, 'side' => ChatParticipant::SIDE_PROVIDER, 'key' => 'demo-dispute-provider'],
        ];
        foreach ($sets as $set) {
            $this->ensureChat($set['key'], [
                'opened_by_user_id' => $client->id,
                'client_user_id' => $set['client'],
                'provider_user_id' => $set['provider'],
            ], function (Chat $chat) use ($set, $client, $provider, $payment, $booking) {
                if ($set['side'] === ChatParticipant::SIDE_CLIENT) {
                    $chat->participants()->create(['user_id' => $client->id, 'side' => ChatParticipant::SIDE_CLIENT, 'joined_at' => now()]);
                } elseif ($set['side'] === ChatParticipant::SIDE_PROVIDER) {
                    $chat->participants()->create(['user_id' => $provider->id, 'side' => ChatParticipant::SIDE_PROVIDER, 'joined_at' => now()]);
                }
                $this->attach($chat, $payment, $client->id);
                $this->attach($chat, $booking, $client->id);
                $chat->flags()->create([
                    'type' => ChatFlag::TYPE_PAYMENT_HELD,
                    'reason' => 'Client rejected the proof (demo).',
                    'active' => true,
                    'created_by_user_id' => $client->id,
                ]);
                $this->message($chat, $client->id, 'Dispute opened (demo): client rejected the proof. Payout held pending Support review.', 'system');
            });
        }

        // 4) A resolved chat and a closed chat (Abierto/Cerrado UI + admin-reopen).
        $this->ensureChat('demo-resolved', [
            'opened_by_user_id' => $client->id, 'client_user_id' => $client->id, 'provider_user_id' => null,
            'status' => Chat::STATUS_RESOLVED, 'resolved_at' => now(),
        ], function (Chat $chat) use ($client) {
            $chat->participants()->create(['user_id' => $client->id, 'side' => ChatParticipant::SIDE_CLIENT, 'joined_at' => now()]);
            $this->message($chat, $client->id, 'Gracias, quedó resuelto (demo).', 'system');
        });

        $this->ensureChat('demo-closed', [
            'opened_by_user_id' => $client->id, 'client_user_id' => $client->id, 'provider_user_id' => null,
            'status' => Chat::STATUS_CLOSED, 'closed_at' => now(), 'closed_by_user_id' => $client->id,
        ], function (Chat $chat) use ($client) {
            $chat->participants()->create(['user_id' => $client->id, 'side' => ChatParticipant::SIDE_CLIENT, 'joined_at' => now()]);
            $this->message($chat, $client->id, 'Cerrado por el cliente (demo).', 'system');
        });

        $this->command?->info('DisputeDemoSeeder: direct + support + 3 dispute chats (held+flag) + resolved + closed ready.');
    }

    /** Idempotent chat creation keyed by a stable marker in the opener system message. */
    private function ensureChat(string $key, array $attributes, callable $build): void
    {
        $marker = 'seed-key:' . $key;

        $exists = Chat::whereHas('messages', fn ($q) => $q->where('body', $marker))->exists();
        if ($exists) {
            return;
        }

        $chat = Chat::create(array_merge(['status' => Chat::STATUS_OPEN, 'last_message_at' => now()], $attributes));
        // Hidden marker message so re-runs are idempotent without a dedicated column.
        $chat->messages()->create(['sender_user_id' => $attributes['opened_by_user_id'], 'body' => $marker, 'kind' => 'system']);
        $build($chat);
    }

    private function attach(Chat $chat, $object, int $userId): void
    {
        $chat->objects()->create([
            'objectable_type' => $object->getMorphClass(),
            'objectable_id' => $object->getKey(),
            'attached_by_user_id' => $userId,
        ]);
    }

    private function message(Chat $chat, int $senderId, string $body, string $kind): void
    {
        $chat->messages()->create(['sender_user_id' => $senderId, 'body' => $body, 'kind' => $kind]);
        $chat->update(['last_message_at' => now()]);
    }

    private function user(string $email, string $name, string $role): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'role' => $role, 'password' => Hash::make('password'), 'is_active' => true]
        );
    }
}
