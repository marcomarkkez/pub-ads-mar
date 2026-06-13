<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;

/**
 * Render-time PII masking (C06 / packet P6).
 *
 * Non-destructive: messages.body is persisted RAW by MessageController::store.
 * This service masks phone / email / URL / street-address occurrences only when
 * a message is serialized for a NON-privileged viewer (see index()).
 *
 * Privileged viewers (admin / support / payments) — and conversations that are
 * flagged internal or have had support join — receive the raw body untouched so
 * staff can read originals for moderation, disputes and payouts.
 *
 * Stateless and dependency-free: instantiate inline or via app(...).
 */
class PiiMaskingService
{
    /**
     * Roles that always see the raw, unmasked message body.
     */
    private const PRIVILEGED_ROLES = ['admin', 'support', 'payments'];

    /**
     * Return the body masked for the given viewer, or raw when privileged /
     * the conversation is relaxed (internal type or support has joined).
     */
    public function mask(string $body, User $viewer, Conversation $convo): string
    {
        if ($this->isPrivileged($viewer) || $this->isRelaxed($convo)) {
            return $body;
        }

        // Whitelist the booked space's own stored address: protect exact-match
        // occurrences with a placeholder before masking other addresses, then
        // restore them afterwards.
        $whitelist = $this->spaceAddress($convo);
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

        // Strip street addresses (number + street name + common suffix), e.g.
        // "123 Main St", "45 Avenida Juarez Avenue", "10 Calle 5 Blvd".
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
     * Conversation is "relaxed" when explicitly internal or once support joins.
     * Columns are added by the shared packet — null-safe checked here.
     */
    private function isRelaxed(Conversation $convo): bool
    {
        return ($convo->type ?? null) === 'internal'
            || ($convo->support_joined_at ?? null) !== null;
    }

    /**
     * The booked space's stored address to whitelist. Prefers an `address`
     * column (per spec) and falls back to `location_text` in the current schema.
     */
    private function spaceAddress(Conversation $convo): ?string
    {
        $space = $convo->space ?? null;

        if ($space === null) {
            return null;
        }

        $address = $space->address ?? $space->location_text ?? null;

        return $address !== null ? trim((string) $address) : null;
    }
}
