<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Chat;
use App\Models\Space;
use App\Models\User;

/**
 * UC-8/UC-21 · design.json §10 [F08] — render-time PII masking.
 *
 * Non-destructive: messages.body is persisted RAW by the chat controllers. This
 * service masks phone / email / URL / street-address occurrences only when a
 * message is serialized for a NON-privileged viewer of a client↔provider chat with
 * NO Support joined. Masking NEVER applies on any staff-only chat (Support↔Client /
 * ↔Provider / ↔Payments / Admin) and RELAXES the moment Support joins (announced).
 */
class PiiMaskingService
{
    /** Roles that always see the raw, unmasked message body. */
    private const PRIVILEGED_ROLES = ['admin', 'support', 'payments'];

    public function mask(string $body, User $viewer, Chat $chat): string
    {
        if ($this->isPrivileged($viewer) || $this->isRelaxed($chat)) {
            return $body;
        }

        // Whitelist the booked space's own stored address: protect exact-match
        // occurrences with a placeholder before masking other addresses, then restore.
        $whitelist = $this->spaceAddress($chat);
        $token = "\0SPACE_ADDR\0";
        $hasWhitelist = $whitelist !== null && $whitelist !== '' && str_contains($body, $whitelist);

        if ($hasWhitelist) {
            $body = str_replace($whitelist, $token, $body);
        }

        // Strip phone numbers (various formats)
        $body = preg_replace('/(\+?\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}/', '[phone removed]', $body);

        // Strip email addresses
        $body = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[email removed]', $body);

        // Strip URLs
        $body = preg_replace('/https?:\/\/[^\s]+/', '[link removed]', $body);
        $body = preg_replace('/www\.[^\s]+/', '[link removed]', $body);

        // Strip street addresses (number + street name + common suffix)
        $body = preg_replace(
            '/\b\d{1,5}\s+(?:[A-Za-z0-9.\'\-]+\s+){0,4}'
            . '(?:St(?:reet)?|Ave(?:nue)?|Avenida|Blvd|Boulevard|Rd|Road|Dr(?:ive)?|'
            . 'Ln|Lane|Ct|Court|Calle|Carrera|Camino|Way|Pl(?:ace)?|Sq(?:uare)?|'
            . 'Ter(?:race)?|Pkwy|Parkway|Hwy|Highway)\b\.?/i',
            '[address removed]',
            $body
        );

        if ($hasWhitelist) {
            $body = str_replace($token, $whitelist, $body);
        }

        return $body;
    }

    private function isPrivileged(User $viewer): bool
    {
        return in_array($viewer->role, self::PRIVILEGED_ROLES, true);
    }

    /**
     * UC-21 · design.json §10/§16 — a chat is "relaxed" (no masking) when it is a
     * staff-only chat (derived nature != client↔provider) OR once Support joins the
     * client↔provider chat (announced). Derived from participants/anchors, NOT a flag.
     */
    private function isRelaxed(Chat $chat): bool
    {
        if ($chat->isStaffOnly()) {
            return true;
        }

        return $chat->support_joined_at !== null;
    }

    /**
     * The booked space's stored address to whitelist. A space is now a chat_object
     * (the old space_id anchor is retired), so read it from an attached Space, or the
     * space of an attached Booking.
     */
    private function spaceAddress(Chat $chat): ?string
    {
        $space = $this->attachedSpace($chat);

        if ($space === null) {
            return null;
        }

        $address = $space->address ?? $space->location_text ?? null;

        return $address !== null ? trim((string) $address) : null;
    }

    private function attachedSpace(Chat $chat): ?Space
    {
        $chat->loadMissing('objects.objectable');

        foreach ($chat->objects as $object) {
            $model = $object->objectable;

            if ($model instanceof Space) {
                return $model;
            }

            if ($model instanceof Booking && $model->space) {
                return $model->space;
            }
        }

        return null;
    }
}
