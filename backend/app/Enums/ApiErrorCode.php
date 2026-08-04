<?php

namespace App\Enums;

/**
 * Stable machine-readable codes returned alongside every API error response,
 * in a top-level "error_code" field. Frontend code should branch on these
 * instead of matching human-readable "message" strings, which are free to
 * change wording without breaking callers.
 */
enum ApiErrorCode: string
{
    case AuthInvalidCredentials = 'AUTH_INVALID_CREDENTIALS';
    case AuthAccountDeactivated = 'AUTH_ACCOUNT_DEACTIVATED';
    case Unauthenticated = 'UNAUTHENTICATED';
    case Forbidden = 'FORBIDDEN';
    case ValidationFailed = 'VALIDATION_FAILED';
    case NotFound = 'NOT_FOUND';
    case ServerError = 'SERVER_ERROR';

    // ── 409 Conflict (owner 2026-08-03) ──────────────────────────────────────
    // "When the API refuses because the object is IN USE or in a conflicting
    // STATE, the status is 409." 422 stays for genuinely malformed payloads —
    // these three describe a valid request the current state cannot accept.

    /** The row already exists; creating it again would duplicate a person/fact. */
    case AlreadyExists = 'ALREADY_EXISTS';

    /** Other rows still depend on this one, so the database refused to delete it. */
    case ObjectInUse = 'OBJECT_IN_USE';

    /** The action destroys something; it needs an explicit acknowledgement flag. */
    case ConfirmationRequired = 'CONFIRMATION_REQUIRED';

    /**
     * The object is in a STATE that forbids this action — a listing already taken
     * down, an account already frozen, a payout past its stop window (§12/§8).
     * The payload is valid; the object is not ready for it.
     */
    case ConflictingState = 'CONFLICTING_STATE';

    // ── 403 ──────────────────────────────────────────────────────────────────
    /** UC-29 · §12 — the account is frozen by admin moderation, not merely inactive. */
    case AuthAccountFrozen = 'AUTH_ACCOUNT_FROZEN';
}
