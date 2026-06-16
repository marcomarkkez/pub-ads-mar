<?php

namespace Database\Seeders;

use App\Models\Ad;
use App\Models\Adset;
use App\Models\Booking;
use App\Models\Campaign;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Proof;
use App\Models\Space;
use App\Models\Ticket;
use App\Models\TicketMessage;
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
                WalletEntry::create([
                    'user_id' => $client->id, 'amount_centavos' => (int) round($price * 100 * 0.9),
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

        // ── Support tickets WITH objects attached + threaded messages ─────────
        $firstAd = Ad::where('campaign_id', $camp1->id)->first();
        $firstSpaceP2 = $p2Spaces->first();

        // 1) Client raises a ticket on an AD, support replies.
        $t1 = Ticket::create([
            'user_id' => $client1->id, 'ticketable_type' => Ad::class, 'ticketable_id' => $firstAd->id,
            'subject' => 'El anuncio no se ve en el espectacular',
            'description' => 'Pasé por la ubicación y mi anuncio no aparecía ayer por la tarde.',
            'status' => 'in_progress', 'priority' => 'high', 'assigned_to_user_id' => $support?->id,
        ]);
        TicketMessage::create(['ticket_id' => $t1->id, 'user_id' => $client1->id, 'body' => 'Adjunto el anuncio afectado. ¿Pueden revisar con el proveedor?', 'is_internal' => false]);
        TicketMessage::create(['ticket_id' => $t1->id, 'user_id' => $support?->id, 'body' => 'Hola, soporte se une a la conversación. Estamos validando con el proveedor la instalación.', 'is_internal' => false]);

        // 2) Client2 raises a ticket on a SPACE.
        $t2 = Ticket::create([
            'user_id' => $client2->id, 'ticketable_type' => Space::class, 'ticketable_id' => $firstSpaceP2?->id,
            'subject' => 'Duda sobre disponibilidad del espacio',
            'description' => '¿Este espacio estará libre la primera semana de agosto?',
            'status' => 'open', 'priority' => 'medium',
        ]);
        TicketMessage::create(['ticket_id' => $t2->id, 'user_id' => $client2->id, 'body' => '¿Me confirman disponibilidad para agosto?', 'is_internal' => false]);

        // 3) Payments-originated ticket on an AD with an INTERNAL Support<->Payments thread.
        $proofBooking = $bookingsForProof[1] ?? $bookingsForProof[0] ?? null;
        if ($proofBooking && $payments) {
            $t3 = Ticket::create([
                'user_id' => $payments->id, 'ticketable_type' => Ad::class, 'ticketable_id' => $proofBooking->ad_id,
                'subject' => 'Revisión de pago retenido por prueba',
                'description' => 'El pago de esta reserva está retenido a la espera de validar la prueba de exhibición.',
                'status' => 'in_progress', 'priority' => 'high', 'assigned_to_user_id' => $support?->id,
            ]);
            TicketMessage::create(['ticket_id' => $t3->id, 'user_id' => $payments->id, 'body' => '[interno] Soporte, ¿pueden confirmar si la prueba corresponde al anuncio antes de liberar el pago?', 'is_internal' => true]);
            TicketMessage::create(['ticket_id' => $t3->id, 'user_id' => $support?->id, 'body' => '[interno] Confirmado, la prueba coincide. Pueden proceder con el pago.', 'is_internal' => true]);
        }

        // 4) Reference-less general ticket (no object attached).
        $t4 = Ticket::create([
            'user_id' => $client1->id, 'ticketable_type' => null, 'ticketable_id' => null,
            'subject' => '¿Cómo cambio mi método de pago?',
            'description' => 'No encuentro dónde actualizar mi tarjeta.',
            'status' => 'open', 'priority' => 'low',
        ]);
        TicketMessage::create(['ticket_id' => $t4->id, 'user_id' => $client1->id, 'body' => 'Necesito ayuda para cambiar mi método de pago.', 'is_internal' => false]);

        // ── Extra conversations attached to spaces (provider2) ────────────────
        if ($firstSpaceP2) {
            $conv = Conversation::create([
                'space_id' => $firstSpaceP2->id, 'client_user_id' => $client1->id, 'provider_user_id' => $provider2->id,
            ]);
            Message::create(['conversation_id' => $conv->id, 'sender_user_id' => $client1->id, 'body' => 'Hola, me interesa este espacio para una campaña de verano.', 'is_read' => true]);
            Message::create(['conversation_id' => $conv->id, 'sender_user_id' => $provider2->id, 'body' => 'Con gusto, tiene disponibilidad en julio. ¿Qué fechas necesita?', 'is_read' => false]);
        }

        $this->command->info('MvpDemoSeeder: bookings, proofs, payments, 4 tickets (+threads), conversations seeded.');
    }
}
