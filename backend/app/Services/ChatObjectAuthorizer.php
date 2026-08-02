<?php

namespace App\Services;

use App\Models\Ad;
use App\Models\Adset;
use App\Models\Booking;
use App\Models\Campaign;
use App\Models\Payment;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * UC-8 · design.json §10/§16 (R2) — the ownership guard for attaching objects to a
 * chat. A client/provider may attach only THEIR OWN objects; Support/Payments/Admin
 * may attach any. A chat must never expose an object its attacher could not see.
 */
class ChatObjectAuthorizer
{
    /** Short type (2-dropdown value) → FQCN. */
    public const TYPE_MAP = [
        'ad' => Ad::class,
        'adset' => Adset::class,
        'campaign' => Campaign::class,
        'space' => Space::class,
        'payment' => Payment::class,
        'booking' => Booking::class,
    ];

    public function resolveClass(string $type): ?string
    {
        return self::TYPE_MAP[$type] ?? null;
    }

    /** UC-8 · R2 — may this user attach (i.e. already see) this object? */
    public function canAttach(User $user, Model $object): bool
    {
        // A Space is a PUBLIC catalog listing — anyone may attach one to open an
        // inquiry (it is not a private object, so this is not an R2 leak).
        if ($object instanceof Space) {
            return true;
        }

        // Staff (support/payments/admin) may attach any object they are handling.
        if ($user->isStaff()) {
            return true;
        }

        if ($user->isClient()) {
            return $this->clientOwns($user, $object);
        }

        if ($user->isProvider()) {
            return $this->providerOwns($user, $object);
        }

        return false;
    }

    private function clientOwns(User $user, Model $object): bool
    {
        return match (true) {
            $object instanceof Campaign => $object->user_id === $user->id,
            $object instanceof Adset => $object->campaign?->user_id === $user->id,
            $object instanceof Ad => $object->adset?->campaign?->user_id === $user->id,
            $object instanceof Booking => $object->client_user_id === $user->id,
            $object instanceof Payment => $object->booking?->client_user_id === $user->id,
            default => false, // clients never own a Space
        };
    }

    private function providerOwns(User $user, Model $object): bool
    {
        return match (true) {
            $object instanceof Space => $object->user_id === $user->id,
            $object instanceof Ad => $object->provider_user_id === $user->id,
            $object instanceof Booking => $object->space?->user_id === $user->id,
            $object instanceof Payment => $object->booking?->space?->user_id === $user->id,
            default => false, // providers never own a Campaign/Adset
        };
    }

    /**
     * UC-8 · the provider user id implied by an object, when opening a client↔provider
     * chat FROM it. Client-only objects (campaign/adset) imply no single provider.
     */
    public function providerFor(Model $object): ?int
    {
        return match (true) {
            $object instanceof Space => $object->user_id,
            $object instanceof Ad => $object->provider_user_id,
            $object instanceof Booking => $object->space?->user_id,
            $object instanceof Payment => $object->booking?->space?->user_id,
            default => null,
        };
    }
}
