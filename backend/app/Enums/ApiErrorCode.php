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
}
