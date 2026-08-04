<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Campaign;
use App\Models\Adset;
use App\Models\Ad;
use App\Models\Space;
use App\Models\SpaceAvailability;
use App\Models\Booking;
use App\Models\Chat;
use App\Models\ChatParticipant;
use App\Models\Collaborator;
use App\Models\Invoice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        // ── System configuration defaults (C12) ───────────────────────
        // Guarded so it runs only after P9's migration creates the table.
        if (Schema::hasTable('system_configurations') && class_exists(\App\Models\SystemConfiguration::class)) {
            $configDefaults = [
                'proof_deadline_days'    => 5,
                // No 'reupload_window_hours' (owner 2026-07-10): §7 abolished the re-upload
                // window. Seeding it put a tunable 48h dial in the admin Config screen that
                // no code has ever read — a setting that lies is worse than a missing one.
                'strike_window_days'     => 90,
                'payout_stop_hours'      => 24,
                'calendar_staleness_days' => 7,
                'refund_split_client'    => 90,
                'refund_split_platform'  => 5,
                'refund_split_provider'  => 5,
                'currency'               => 'MXN',
                'deletion_grace_days'    => 30,
            ];

            foreach ($configDefaults as $key => $value) {
                \App\Models\SystemConfiguration::updateOrCreate(
                    ['key' => $key],
                    ['value' => (string) $value],
                );
            }
        }

        // ── System users ──────────────────────────────────────────────
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@pubads.test',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'phone' => '+5218112345678',
            'company_name' => 'PubAds Admin',
        ]);

        $support = User::create([
            'name' => 'Soporte Agent',
            'email' => 'support@pubads.test',
            'password' => Hash::make('password'),
            'role' => 'support',
            'phone' => '+5218119876543',
        ]);

        $payments = User::create([
            'name' => 'Pagos Officer',
            'email' => 'payments@pubads.test',
            'password' => Hash::make('password'),
            'role' => 'payments',
            'phone' => '+5218118765432',
        ]);

        // ── Providers (San Pedro, NL) ─────────────────────────────────
        $provider1 = User::create([
            'name' => 'Roberto Garza',
            'email' => 'provider1@pubads.test',
            'password' => Hash::make('password'),
            'role' => 'provider',
            'phone' => '+5218112223344',
            'company_name' => 'Garza Medios Exteriores',
            'address' => 'Av. Vasconcelos 300, San Pedro Garza García, NL',
        ]);

        $provider2 = User::create([
            'name' => 'Fernanda Treviño',
            'email' => 'provider2@pubads.test',
            'password' => Hash::make('password'),
            'role' => 'provider',
            'phone' => '+5218115556677',
            'company_name' => 'Treviño Publicidad',
            'address' => 'Blvd. Antonio L. Rodríguez 1000, Monterrey, NL',
        ]);

        // ── Clients ───────────────────────────────────────────────────
        $client1 = User::create([
            'name' => 'Carlos Mendez',
            'email' => 'client1@pubads.test',
            'password' => Hash::make('password'),
            'role' => 'client',
            'phone' => '+5218181234567',
            'company_name' => 'Mendez Marketing',
            'address' => 'Av. Humberto Lobo 555, San Pedro Garza García, NL',
        ]);

        $client2 = User::create([
            'name' => 'Sofía Ramírez',
            'email' => 'client2@pubads.test',
            'password' => Hash::make('password'),
            'role' => 'client',
            'phone' => '+5218189876543',
            'company_name' => 'Ramírez Digital',
            'address' => 'Av. Eugenio Garza Sada 427, Monterrey, NL',
        ]);

        // ── Collaborators (design.json §3 · UC-19) ────────────────────
        // ACCOUNT-scoped, one grant per person per account. Two states on purpose:
        // an ACCEPTED manager who already has a login (and, being a person with an
        // email, an account of her own — §3: "an EMAIL is the account identity"),
        // and a PENDING publicist who was invited but has not registered yet, which
        // is why `user_id` is nullable.
        $collaborator1 = User::create([
            'name' => 'Ana Torres',
            'email' => 'colab1@pubads.test',
            'password' => Hash::make('password'),
            'role' => 'client',
            'phone' => '+5218114445566',
        ]);

        Collaborator::create([
            'account_id' => $client1->account_id,
            'invited_by_user_id' => $client1->id,
            'user_id' => $collaborator1->id,
            'email' => $collaborator1->email,
            'role' => 'manager',
            'status' => 'accepted',
        ]);

        Collaborator::create([
            'account_id' => $client1->account_id,
            'invited_by_user_id' => $client1->id,
            'email' => 'publicista@pubads.test',
            'role' => 'publicist',
            'status' => 'pending',
        ]);

        // ── Spaces near San Pedro Garza García, NL ───────────────────
        // Provider 1 spaces
        $space1 = Space::create([
            'user_id' => $provider1->id,
            'name' => 'Espectacular Av. Vasconcelos',
            'type' => 'billboard',
            'latitude' => 25.6573,
            'longitude' => -100.4021,
            'price_per_day' => 2500.00,
            'price_per_month' => 55000.00,
            'pricing_unit' => 'day',
            'description' => 'Espectacular de alto impacto sobre Av. Vasconcelos, frente a Plaza Fiesta San Agustín. Alta afluencia vehicular.',
            'location_text' => 'Av. Vasconcelos, San Pedro Garza García',
            'width' => 12.00,
            'height' => 4.00,
        ]);

        $space2 = Space::create([
            'user_id' => $provider1->id,
            'name' => 'Pantalla Digital Valle Oriente',
            'type' => 'big_screen',
            'latitude' => 25.6502,
            'longitude' => -100.3688,
            'price_per_day' => 1800.00,
            'price_per_month' => 40000.00,
            'pricing_unit' => 'day',
            'description' => 'Pantalla LED full color en Torre KOI, Valle Oriente. Visible desde la Autopista Monterrey-Aeropuerto.',
            'location_text' => 'Valle Oriente, San Pedro Garza García',
            'width' => 6.00,
            'height' => 3.00,
        ]);

        $space3 = Space::create([
            'user_id' => $provider1->id,
            'name' => 'Mural Av. Humberto Lobo',
            'type' => 'billboard',
            'latitude' => 25.6618,
            'longitude' => -100.3958,
            'price_per_day' => 1200.00,
            'pricing_unit' => 'day',
            'description' => 'Mural publicitario en edificio corporativo sobre Humberto Lobo, zona financiera de San Pedro.',
            'location_text' => 'Av. Humberto Lobo, San Pedro Garza García',
            'width' => 8.00,
            'height' => 5.00,
        ]);

        // Provider 2 spaces
        $space4 = Space::create([
            'user_id' => $provider2->id,
            'name' => 'Pantalla Centro Comercial Galerías',
            'type' => 'little_screen',
            'latitude' => 25.6766,
            'longitude' => -100.3694,
            'price_per_day' => 800.00,
            'pricing_unit' => 'day',
            'description' => 'Pantalla interior en acceso principal de Galerías Monterrey. Alto tráfico peatonal.',
            'location_text' => 'Galerías Monterrey, Monterrey, NL',
            'width' => 2.50,
            'height' => 1.50,
        ]);

        $space5 = Space::create([
            'user_id' => $provider2->id,
            'name' => 'Espectacular Constitución / Morones',
            'type' => 'billboard',
            'latitude' => 25.6866,
            'longitude' => -100.3161,
            'price_per_day' => 3200.00,
            'price_per_month' => 70000.00,
            'pricing_unit' => 'month',
            'description' => 'Espectacular en intersección de alta visibilidad, Av. Constitución y Morones Prieto.',
            'location_text' => 'Av. Constitución, Monterrey, NL',
            'width' => 14.00,
            'height' => 5.00,
        ]);

        $space6 = Space::create([
            'user_id' => $provider2->id,
            'name' => 'Spot Radio TEC FM',
            'type' => 'radio_station',
            'latitude' => 25.6514,
            'longitude' => -100.2895,
            'price_per_day' => 500.00,
            'price_per_month' => 10000.00,
            'pricing_unit' => 'day',
            'description' => 'Spot de 30 segundos en TEC FM 94.9, emisora del ITESM con audiencia universitaria regiomontana.',
            'location_text' => 'TEC FM 94.9, Monterrey, NL',
            'width' => null,
            'height' => null,
        ]);

        // ── Availabilities ────────────────────────────────────────────
        foreach ([$space1, $space2, $space3, $space4, $space5, $space6] as $space) {
            SpaceAvailability::create([
                'space_id' => $space->id,
                'start_date' => '2026-03-01',
                'end_date' => '2026-09-30',
                'status' => 'available',
                'source' => 'manual',
            ]);
        }

        // ── Client 1 — Campaign 1 (active) ───────────────────────────
        $campaign1 = Campaign::create([
            'user_id' => $client1->id,
            'name' => 'Lanzamiento Verano 2026',
            'description' => 'Campaña de lanzamiento de producto para el verano. Enfoque en zona San Pedro / Valle.',
            'status' => 'active',
            'start_date' => '2026-03-15',
            'end_date' => '2026-06-15',
            'budget' => 80000.00,
        ]);

        $adset1 = Adset::create([
            'campaign_id' => $campaign1->id,
            'name' => 'San Pedro Centro',
            'latitude' => 25.6573,
            'longitude' => -100.4021,
            'location_name' => 'San Pedro Garza García, NL',
            'radius_km' => 5.00,
        ]);

        $ad1 = Ad::create([
            'adset_id' => $adset1->id,
            'space_id' => $space1->id,
            'provider_user_id' => $provider1->id,
            'name' => 'Banner Verano — Vasconcelos',
            'media_type' => 'image',
            'price' => 2500.00,
            'pricing_unit' => 'day',
            'start_date' => '2026-03-15',
            'end_date' => '2026-04-15',
            'status' => 'active',
            'proof_deadline' => '2026-04-20',
        ]);

        $ad2 = Ad::create([
            'adset_id' => $adset1->id,
            'space_id' => $space2->id,
            'provider_user_id' => $provider1->id,
            'name' => 'Video Verano — Valle Oriente',
            'media_type' => 'video',
            'price' => 1800.00,
            'pricing_unit' => 'day',
            'start_date' => '2026-03-15',
            'end_date' => '2026-04-15',
            'status' => 'pending_approval',
        ]);

        // ── Client 1 — Campaign 2 (draft) ────────────────────────────
        $campaign2 = Campaign::create([
            'user_id' => $client1->id,
            'name' => 'Branding Q2 — Zona Valle',
            'description' => 'Refuerzo de marca en corredor Valle Oriente y Galerías para Q2.',
            'status' => 'draft',
            'start_date' => '2026-05-01',
            'end_date' => '2026-06-30',
            'budget' => 45000.00,
        ]);

        $adset2 = Adset::create([
            'campaign_id' => $campaign2->id,
            'name' => 'Valle Oriente',
            'latitude' => 25.6502,
            'longitude' => -100.3688,
            'location_name' => 'Valle Oriente, San Pedro Garza García',
            'radius_km' => 3.00,
        ]);

        // ── Client 2 — Campaign ───────────────────────────────────────
        $campaign3 = Campaign::create([
            'user_id' => $client2->id,
            'name' => 'Conciencia de Marca Q1',
            'description' => 'Aumentar visibilidad en corredores de Monterrey y San Pedro.',
            'status' => 'active',
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'budget' => 25000.00,
        ]);

        Adset::create([
            'campaign_id' => $campaign3->id,
            'name' => 'Monterrey Norte',
            'latitude' => 25.6866,
            'longitude' => -100.3161,
            'location_name' => 'Monterrey Centro',
            'radius_km' => 8.00,
        ]);

        // ── Booking + Payment ─────────────────────────────────────────
        $booking1 = Booking::create([
            'client_user_id' => $client1->id,
            'space_id' => $space1->id,
            'ad_id' => $ad1->id,
            'adset_id' => $adset1->id,
            'start_date' => '2026-03-15',
            'end_date' => '2026-04-15',
            'total_price' => 77500.00,
            'status' => 'active',
        ]);

        $booking1->payment()->create([
            'amount' => 77500.00,
            'status' => 'completed',
            'payment_method' => 'mocked',
            'transaction_id' => 'TXN-' . Str::upper(Str::random(10)),
            'approved_by_payments' => true,
            'approved_by_user_id' => $payments->id,
        ]);

        // ── Invoices ──────────────────────────────────────────────────
        Invoice::create([
            'campaign_id' => $campaign1->id,
            'invoice_number' => 'INV-2026-0001',
            'total_amount' => 77500.00,
            'status' => 'paid',
            'issued_at' => '2026-03-10',
            'due_at' => '2026-03-20',
        ]);

        Invoice::create([
            'campaign_id' => $campaign1->id,
            'invoice_number' => 'INV-2026-0002',
            'total_amount' => 18000.00,
            'status' => 'issued',
            'issued_at' => '2026-03-20',
            'due_at' => '2026-03-31',
        ]);

        Invoice::create([
            'campaign_id' => $campaign3->id,
            'invoice_number' => 'INV-2026-0003',
            'total_amount' => 25000.00,
            'status' => 'issued',
            'issued_at' => '2026-03-25',
            'due_at' => '2026-04-05',
        ]);

        // ── Chats + Messages (design.json §10 — ONE primitive; space = a chat_object) ──
        $this->seedChat($client1, $provider1, $space1, [
            [$client1->id, 'Hola Roberto, me interesa el espectacular de Vasconcelos para marzo. ¿Está disponible del 15 al 30?', true],
            [$provider1->id, 'Hola Carlos, sí está disponible esas fechas. El precio es $2,500 MXN por día. ¿Deseas apartar?', true],
            [$client1->id, 'Perfecto, vamos a proceder con la reserva. ¿Qué formatos de imagen aceptan?', true],
            [$provider1->id, 'Aceptamos JPG y PNG en alta resolución, mínimo 300 DPI. Dimensiones del espectacular: 12x4 metros.', false],
        ]);

        $this->seedChat($client1, $provider1, $space2, [
            [$client1->id, 'Buenos días, quisiera cotizar la pantalla digital de Valle Oriente para una campaña de video.', true],
            [$provider1->id, 'Claro, la pantalla tiene disponibilidad para abril. El video debe ser MP4, máximo 30 segundos y resolución 1920x1080.', false],
        ]);

        $this->seedChat($client2, $provider2, $space5, [
            [$client2->id, 'Hola Fernanda, vi el espectacular en Constitución y me parece ideal para nuestra campaña de abril.', true],
            [$provider2->id, 'Hola Sofía, ese espectacular tiene mucha visibilidad. El precio mensual es $70,000 MXN. ¿Te mando el contrato?', true],
            [$client2->id, 'Sí, por favor envíame los detalles al correo.', false],
        ]);
    }

    /** design.json §10 — a client↔provider chat with the space attached and messages. */
    private function seedChat(User $client, User $provider, Space $space, array $messages): void
    {
        $chat = Chat::create([
            'opened_by_user_id' => $client->id,
            'client_user_id' => $client->id,
            'provider_user_id' => $provider->id,
            'status' => Chat::STATUS_OPEN,
            'last_message_at' => now(),
        ]);
        $chat->participants()->create(['user_id' => $client->id, 'side' => ChatParticipant::SIDE_CLIENT, 'joined_at' => now()]);
        $chat->participants()->create(['user_id' => $provider->id, 'side' => ChatParticipant::SIDE_PROVIDER, 'joined_at' => now()]);
        $chat->objects()->create(['objectable_type' => $space->getMorphClass(), 'objectable_id' => $space->id, 'attached_by_user_id' => $client->id]);

        foreach ($messages as [$senderId, $body, $isRead]) {
            $chat->messages()->create(['sender_user_id' => $senderId, 'body' => $body, 'is_read' => $isRead, 'kind' => 'user']);
        }
    }
}
