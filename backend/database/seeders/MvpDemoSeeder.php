<?php

namespace Database\Seeders;

use App\Models\Ad;
use App\Models\Adset;
use App\Models\Booking;
use App\Models\Campaign;
use App\Models\Chat;
use App\Models\ChatParticipant;
use App\Models\Proof;
use App\Models\Space;
use App\Models\User;
use App\Models\WalletEntry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Additive demo data on top of DatabaseSeeder — gives every role-side screen
 * something to look at: bookings (mixed statuses), proofs, pending payments,
 * support tickets WITH objects attached + threaded messages (incl. an internal
 * Support<->Payments thread and a reference-less ticket), and extra conversations.
 *
 * Idempotent-ish: guarded by a marker campaign name so re-running won't pile up.
 * Run:  php artisan db:seed --class=MvpDemoSeeder
 */
class MvpDemoSeeder extends Seeder
{
    public function run(): void
    {
        $client1   = User::where('email', 'client1@pubads.test')->first();
        $client2   = User::where('email', 'client2@pubads.test')->first();
        $provider1 = User::where('email', 'provider1@pubads.test')->first();
        $provider2 = User::where('email', 'provider2@pubads.test')->first();
        $support   = User::where('email', 'support@pubads.test')->first();
        $payments  = User::where('email', 'payments@pubads.test')->first();

        if (!$client1 || !$provider1) {
            $this->command->warn('Base demo users missing — run DatabaseSeeder first.');
            return;
        }

        if (Campaign::where('name', 'Demo MVP — Reservas')->exists()) {
            $this->command->info('MvpDemoSeeder already applied — skipping.');
            return;
        }

        $p1Spaces = Space::where('user_id', $provider1->id)->orderBy('id')->get();
        $p2Spaces = Space::where('user_id', $provider2->id)->orderBy('id')->get();

        // ── A campaign per client holding the demo ads we will book ───────────
        $camp1 = Campaign::create([
            'user_id' => $client1->id, 'name' => 'Demo MVP — Reservas',
            'description' => 'Campaña de demostración con reservas en varios estados.',
            'status' => 'active', 'start_date' => '2026-06-01', 'end_date' => '2026-09-30',
            'budget' => 500000,
        ]);
        $adset1 = Adset::create([
            'campaign_id' => $camp1->id, 'name' => 'Monterrey Centro',
            'latitude' => 25.6714, 'longitude' => -100.3090, 'location_name' => 'Monterrey Centro',
            'radius_km' => 10, 'status' => 'active',
        ]);

        $camp2 = Campaign::create([
            'user_id' => $client2->id, 'name' => 'Demo MVP — Reservas (C2)',
            'description' => 'Reservas de demostración del cliente 2.',
            'status' => 'active', 'start_date' => '2026-06-01', 'end_date' => '2026-08-31',
            'budget' => 200000,
        ]);
        $adset2 = Adset::create([
            'campaign_id' => $camp2->id, 'name' => 'San Pedro',
            'latitude' => 25.6580, 'longitude' => -100.4020, 'location_name' => 'San Pedro',
            'radius_km' => 8, 'status' => 'active',
        ]);

        // ── Booking plan: [space, client, adset, mediaType, status, payStatus, proof] ──
        $today = Carbon::create(2026, 6, 15);
        $plan = [
            // provider1 spaces booked by client1
            [$p1Spaces->get(0), $client1, $adset1, 'image', 'active',         'completed', 'approved'],
            [$p1Spaces->get(1), $client1, $adset1, 'video', 'waiting_proof',  'completed', 'pending_review'],
            [$p1Spaces->get(2), $client1, $adset1, 'image', 'waiting_approval','pending',   null],
            // provider2 spaces booked by client2
            [$p2Spaces->get(0), $client2, $adset2, 'image', 'active',         'completed', 'rejected'],
            [$p2Spaces->get(1) ?? $p2Spaces->get(0), $client2, $adset2, 'video', 'completed', 'completed', 'approved'],
            [$p1Spaces->get(0), $client2, $adset2, 'image', 'cancelled',      'refunded',  null],
        ];

        $bookingsForProof = [];
        foreach ($plan as $i => [$space, $client, $adset, $mediaType, $bStatus, $payStatus, $proofStatus]) {
            if (!$space) { continue; }
            $start = (clone $today)->addDays(($i - 2) * 7);
            $end   = (clone $start)->addDays(14);
            $price = (float) $space->price_per_day * 14;

            $ad = Ad::create([
                'space_id' => $space->id, 'provider_user_id' => $space->user_id,
                'name' => $space->name . ' — ' . ucfirst($mediaType),
                'media_type' => $mediaType, 'status' => 'approved',
            ]);
            $ad->adset_id = $adset->id; $ad->campaign_id = $adset->campaign_id; $ad->save();

            $booking = Booking::create([
                'client_user_id' => $client->id, 'space_id' => $space->id, 'ad_id' => $ad->id,
                'adset_id' => $adset->id, 'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(), 'total_price' => $price, 'status' => $bStatus,
            ]);

            $booking->payment()->create([
                'amount' => $price, 'status' => $payStatus, 'payment_method' => 'mocked',
                'payment_platform' => $i % 2 ? 'mercado_pago' : 'paypal',
                'transaction_id' => 'TXN-' . Str::upper(Str::random(10)),
                'approved_by_payments' => in_array($payStatus, ['completed', 'released']),
                'approved_by_user_id' => in_array($payStatus, ['completed', 'released']) ? $payments?->id : null,
            ]);

            if ($payStatus === 'refunded') {
                // wallet_entries stores signed DECIMAL pesos (`amount`); the old
                // `amount_centavos` column was renamed by 2026_06_23_000001.
                WalletEntry::create([
                    'user_id' => $client->id, 'amount' => round($price * 0.9, 2),
                    'type' => 'refund', 'ref_type' => 'booking', 'ref_id' => $booking->id,
                    'idempotency_key' => 'seed-refund-booking-' . $booking->id,
                ]);
            }

            if ($proofStatus) {
                Proof::create([
                    'ad_id' => $ad->id, 'booking_id' => $booking->id,
                    'uploaded_by_user_id' => $space->user_id,
                    'media_type' => $mediaType === 'video' ? 'video' : 'image',
                    'file_path' => 'proofs/demo-' . $booking->id . ($mediaType === 'video' ? '.mp4' : '.jpg'),
                    'file_name' => 'proof-' . $booking->id . ($mediaType === 'video' ? '.mp4' : '.jpg'),
                    'notes' => $proofStatus === 'rejected' ? 'La imagen no coincide con el anuncio aprobado.' : null,
                    'status' => $proofStatus,
                    'reviewed_by_user_id' => $proofStatus === 'pending_review' ? null : $payments?->id,
                    'reviewed_at' => $proofStatus === 'pending_review' ? null : $today,
                    'deadline' => (clone $start)->addDays(5),
                ]);
                $bookingsForProof[] = $booking;
            }
        }

        // ── Chats: a "ticket" is just a chat with a Support participant (design.json §10) ──
        $firstAd = Ad::where('campaign_id', $camp1->id)->first();
        $firstSpaceP2 = $p2Spaces->first();

        // 1) Client↔support chat with an AD attached (UC-9 contact support).
        $c1 = Chat::create(['opened_by_user_id' => $client1->id, 'client_user_id' => $client1->id, 'status' => Chat::STATUS_IN_PROGRESS, 'last_message_at' => now()]);
        $c1->participants()->create(['user_id' => $client1->id, 'side' => ChatParticipant::SIDE_CLIENT, 'joined_at' => now()]);
        if ($firstAd) {
            $c1->objects()->create(['objectable_type' => $firstAd->getMorphClass(), 'objectable_id' => $firstAd->id, 'attached_by_user_id' => $client1->id]);
        }
        $c1->messages()->create(['sender_user_id' => $client1->id, 'body' => 'Adjunto el anuncio afectado. ¿Pueden revisar con el proveedor?', 'kind' => 'user']);
        if ($support) {
            $c1->participants()->create(['user_id' => $support->id, 'side' => ChatParticipant::SIDE_SUPPORT, 'announced' => true, 'joined_at' => now()]);
            $c1->messages()->create(['sender_user_id' => $support->id, 'body' => 'Hola, soporte se une. Estamos validando con el proveedor la instalación.', 'kind' => 'user']);
        }

        // 2) Client2↔support chat with a SPACE attached.
        $c2 = Chat::create(['opened_by_user_id' => $client2->id, 'client_user_id' => $client2->id, 'status' => Chat::STATUS_OPEN, 'last_message_at' => now()]);
        $c2->participants()->create(['user_id' => $client2->id, 'side' => ChatParticipant::SIDE_CLIENT, 'joined_at' => now()]);
        if ($firstSpaceP2) {
            $c2->objects()->create(['objectable_type' => $firstSpaceP2->getMorphClass(), 'objectable_id' => $firstSpaceP2->id, 'attached_by_user_id' => $client2->id]);
        }
        $c2->messages()->create(['sender_user_id' => $client2->id, 'body' => '¿Me confirman disponibilidad para agosto?', 'kind' => 'user']);

        // 3) Internal Support↔Payments chat (both anchors null → nature internal).
        $proofBooking = $bookingsForProof[1] ?? $bookingsForProof[0] ?? null;
        if ($proofBooking && $payments && $support) {
            $c3 = Chat::create(['opened_by_user_id' => $payments->id, 'status' => Chat::STATUS_IN_PROGRESS, 'last_message_at' => now()]);
            $c3->participants()->create(['user_id' => $payments->id, 'side' => ChatParticipant::SIDE_PAYMENTS, 'joined_at' => now()]);
            $c3->participants()->create(['user_id' => $support->id, 'side' => ChatParticipant::SIDE_SUPPORT, 'joined_at' => now()]);
            if ($proofBooking->payment) {
                $c3->objects()->create(['objectable_type' => $proofBooking->payment->getMorphClass(), 'objectable_id' => $proofBooking->payment->id, 'attached_by_user_id' => $payments->id]);
            }
            $c3->messages()->create(['sender_user_id' => $payments->id, 'body' => 'Soporte, ¿confirman que la prueba corresponde antes de liberar el pago?', 'kind' => 'user']);
            $c3->messages()->create(['sender_user_id' => $support->id, 'body' => 'Confirmado, la prueba coincide. Pueden proceder.', 'kind' => 'user']);
        }

        // 4) Objectless general support chat (contact support, UC-9).
        $c4 = Chat::create(['opened_by_user_id' => $client1->id, 'client_user_id' => $client1->id, 'status' => Chat::STATUS_OPEN, 'last_message_at' => now()]);
        $c4->participants()->create(['user_id' => $client1->id, 'side' => ChatParticipant::SIDE_CLIENT, 'joined_at' => now()]);
        $c4->messages()->create(['sender_user_id' => $client1->id, 'body' => 'Necesito ayuda para cambiar mi método de pago.', 'kind' => 'user']);

        // ── Extra client↔provider chat attached to a space (provider2) ────────
        if ($firstSpaceP2) {
            $c5 = Chat::create(['opened_by_user_id' => $client1->id, 'client_user_id' => $client1->id, 'provider_user_id' => $provider2->id, 'status' => Chat::STATUS_OPEN, 'last_message_at' => now()]);
            $c5->participants()->create(['user_id' => $client1->id, 'side' => ChatParticipant::SIDE_CLIENT, 'joined_at' => now()]);
            $c5->participants()->create(['user_id' => $provider2->id, 'side' => ChatParticipant::SIDE_PROVIDER, 'joined_at' => now()]);
            $c5->objects()->create(['objectable_type' => $firstSpaceP2->getMorphClass(), 'objectable_id' => $firstSpaceP2->id, 'attached_by_user_id' => $client1->id]);
            $c5->messages()->create(['sender_user_id' => $client1->id, 'body' => 'Hola, me interesa este espacio para una campaña de verano.', 'is_read' => true, 'kind' => 'user']);
            $c5->messages()->create(['sender_user_id' => $provider2->id, 'body' => 'Con gusto, tiene disponibilidad en julio. ¿Qué fechas necesita?', 'is_read' => false, 'kind' => 'user']);
        }

        $this->command->info('MvpDemoSeeder: bookings, proofs, payments, and 5 chats (support/internal/direct) seeded.');
    }
}
