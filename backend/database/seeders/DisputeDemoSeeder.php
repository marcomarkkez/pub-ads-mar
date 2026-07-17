<?php

namespace Database\Seeders;

use App\Models\Ad;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Payment;
use App\Models\Proof;
use App\Models\Space;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds a READY-MADE dispute so a human can walk the flow across roles without
 * building data by hand (design.md §7/§10/§11 [F07-F10]): a held payment, a
 * client-rejected proof, the "Payment held" flag on the payment, and the three
 * Support-anchored threads (internal / support_client / support_provider).
 *
 * Idempotent: safe to re-run (docker compose exec backend php artisan db:seed
 * --class=DisputeDemoSeeder). Reuses the demo logins when present.
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
            ['client_user_id' => $client->id, 'space_id' => $space->id, 'ad_id' => $ad->id],
            ['start_date' => '2026-07-01', 'end_date' => '2026-07-31', 'total_price' => 500, 'status' => 'waiting_proof']
        );

        // Held payment + client-rejected proof (the dispute end-state).
        $payment = Payment::updateOrCreate(
            ['booking_id' => $booking->id],
            ['amount' => 500, 'status' => 'held']
        );

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

        // The "Payment held" flag Payments watches.
        Ticket::firstOrCreate(
            ['ticketable_type' => Payment::class, 'ticketable_id' => $payment->id, 'subject' => 'Payment held — demo dispute'],
            [
                'user_id' => $client->id,
                'description' => 'Client rejected the proof. Payout is on hold pending Support review. (demo)',
                'priority' => 'high',
                'status' => 'open',
            ]
        );

        // The three Support-anchored threads, each announced once.
        foreach ([
            Conversation::TYPE_INTERNAL,
            Conversation::TYPE_SUPPORT_CLIENT,
            Conversation::TYPE_SUPPORT_PROVIDER,
        ] as $type) {
            $convo = Conversation::firstOrCreate(
                ['space_id' => $space->id, 'client_user_id' => $client->id, 'type' => $type],
                ['provider_user_id' => $provider->id]
            );

            if ($convo->wasRecentlyCreated) {
                $convo->messages()->create([
                    'sender_user_id' => $client->id,
                    'body' => 'Dispute opened (demo): client rejected the proof. Payout held pending Support review.',
                    'kind' => 'system',
                ]);
            }
        }

        // A plain direct thread too, so the queue shows the client↔provider chat.
        Conversation::firstOrCreate(
            ['space_id' => $space->id, 'client_user_id' => $client->id, 'type' => Conversation::TYPE_DIRECT],
            ['provider_user_id' => $provider->id]
        );

        $this->command?->info('DisputeDemoSeeder: held payment + flag + 3 dispute threads ready (booking #' . $booking->id . ').');
    }

    private function user(string $email, string $name, string $role): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'role' => $role, 'password' => Hash::make('password'), 'is_active' => true]
        );
    }
}
