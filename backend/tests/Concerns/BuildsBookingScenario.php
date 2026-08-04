<?php

namespace Tests\Concerns;

use App\Models\Ad;
use App\Models\Booking;
use App\Models\Chat;
use App\Models\Payment;
use App\Models\Proof;
use App\Models\Space;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

/**
 * Shared fixture for the proof/dispute/payment feature tests (F07-F10): seeds the
 * role/permission matrix and a full client<->provider booking carrying a payment
 * and an uploaded (pending) proof. Returns the models keyed by name.
 */
trait BuildsBookingScenario
{
    protected function bookingScenario(array $overrides = []): array
    {
        $this->seed(RolePermissionSeeder::class);

        $client = User::factory()->create(['role' => 'client']);
        $provider = User::factory()->create(['role' => 'provider']);

        $space = Space::create([
            'user_id' => $provider->id,
            'name' => 'Test Space',
            'latitude' => 25.6597,
            'longitude' => -100.4023,
        ]);

        $ad = Ad::create([
            'space_id' => $space->id,
            'provider_user_id' => $provider->id,
            'name' => 'Test Ad',
            'media_type' => 'image',
            'status' => 'active',
        ]);

        $booking = Booking::create([
            'client_user_id' => $client->id,
            'space_id' => $space->id,
            'ad_id' => $ad->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'total_price' => 500,
            'status' => 'waiting_proof',
        ]);

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'amount' => 500,
            'status' => $overrides['payment_status'] ?? 'pending',
        ]);

        $proof = Proof::create([
            'ad_id' => $ad->id,
            'booking_id' => $booking->id,
            'uploaded_by_user_id' => $provider->id,
            'media_type' => 'image',
            'file_path' => 'proofs/x.jpg',
            'file_name' => 'x.jpg',
            'status' => Proof::STATUS_UPLOADED,
        ]);

        return compact('client', 'provider', 'space', 'ad', 'booking', 'payment', 'proof');
    }

    /**
     * The three dispute chats opened by a client proof-reject, keyed by nature.
     * Identified by their attached payment + side anchors (no `type` column).
     */
    protected function disputeChats(array $s): array
    {
        $base = fn () => Chat::query()->whereHas('objects', function ($q) use ($s) {
            $q->where('objectable_type', $s['payment']->getMorphClass())
                ->where('objectable_id', $s['payment']->id);
        });

        return [
            'internal' => $base()->whereNull('client_user_id')->whereNull('provider_user_id')->firstOrFail(),
            'support_client' => $base()->where('client_user_id', $s['client']->id)->whereNull('provider_user_id')->firstOrFail(),
            'support_provider' => $base()->whereNull('client_user_id')->where('provider_user_id', $s['provider']->id)->firstOrFail(),
        ];
    }
}
