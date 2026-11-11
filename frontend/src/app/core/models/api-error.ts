import { HttpErrorResponse } from '@angular/common/http';

// Mirrors backend/app/Enums/ApiErrorCode.php. NETWORK_ERROR has no backend
// counterpart: it means the request never reached the server at all (CORS
// rejection, wrong API URL, connection refused), as opposed to the server
// responding with an actual error.
export enum ApiErrorCode {
  AuthInvalidCredentials = 'AUTH_INVALID_CREDENTIALS',
  AuthAccountDeactivated = 'AUTH_ACCOUNT_DEACTIVATED',
  Unauthenticated = 'UNAUTHENTICATED',
  Forbidden = 'FORBIDDEN',
  ValidationFailed = 'VALIDATION_FAILED',
  NotFound = 'NOT_FOUND',
  ServerError = 'SERVER_ERROR',
  NetworkError = 'NETWORK_ERROR',

  // ── 409 Conflict (owner 2026-08-03) — "in use / conflicting state is 409".
  // These were missing here while the backend had been returning them for a while,
  // which forced screens to branch on `err.status === 409` and then guess WHICH of the
  // four refusals it was. A 409 that asks for a confirmation and a 409 that refuses
  // outright need opposite UIs.
  /** The row already exists (e.g. a deletion is already programmed). */
  AlreadyExists = 'ALREADY_EXISTS',
  /** Other rows still depend on this one — the body carries `blockers[]`. */
  ObjectInUse = 'OBJECT_IN_USE',
  /** The action destroys something and needs an explicit acknowledgement flag. */
  ConfirmationRequired = 'CONFIRMATION_REQUIRED',
  /** The object's STATE forbids the action (taken down, frozen, past the stop window). */
  ConflictingState = 'CONFLICTING_STATE',

  /** 403 · UC-29 — frozen by moderation, which is not the same as deactivated. */
  AuthAccountFrozen = 'AUTH_ACCOUNT_FROZEN',
}

/**
 * One reason an object cannot be deleted, as returned inside a 409 `blockers[]`
 * (Space::deletionBlockers, Account::disputeBlockers/inUseBlockers).
 *
 * BR-10 — the refusal travels WITH its reasons and the UI has to show them: "no"
 * without a reason leaves the owner guessing which of their clients is holding the
 * object, and guessing is not a state a person can act from.
 *
 * Same shape, one place it is NOT a refusal: since the owner's 2026-09-05 correction,
 * DELETE /provider/spaces/{id} SUCCEEDS on a busy listing and returns this list on the
 * 200 as `purge_blockers` — the reasons the row cannot be destroyed on its due date yet.
 * Read the key, not the type, to know whether you are looking at a "no" or at a "later".
 */
export interface Blocker {
  kind: string;
  count: number;
  message: string;
  /**
   * `true` = waiting will never clear this one (Space::deletionBlockers' `booking_history`:
   * past bookings reference the listing and their invoices, payments and proofs are argued
   * from them, so the row is unpublished forever and never destroyed). Absent or `false` =
   * a "later" that a finishing booking or a settling payout really does end.
   *
   * A screen that cannot tell the two apart promises a deletion date that will never
   * arrive, which is the one thing worse than saying no.
   */
  permanent?: boolean;
}

/** The `blockers[]` of a 409 body, or [] when the refusal carried none. */
export function parseBlockers(err: { error?: unknown }): Blocker[] {
  const body = err.error as { blockers?: unknown } | null | undefined;
  return Array.isArray(body?.blockers) ? (body!.blockers as Blocker[]) : [];
}

export interface ApiError {
  code: ApiErrorCode | string;
  message: string;
}

export function parseApiError(err: HttpErrorResponse): ApiError {
  if (err.status === 0) {
    return {
      code: ApiErrorCode.NetworkError,
      message: networkErrorMessage(typeof window === 'undefined' ? '' : window.location.hostname),
    };
  }

  const code = err.error?.error_code;
  const message = err.error?.message;
  if (code && message) {
    return { code, message };
  }

  return {
    code: ApiErrorCode.ServerError,
    message: message || `Unexpected error (HTTP ${err.status}).`,
  };
}

/**
 * What "could not reach the API" means depends on where the app is running (2026-11-11).
 * In a Codespace the API is another forwarded port, `<name>-8000.app.github.dev`, and when
 * that port is PRIVATE GitHub answers the browser with its sign-in redirect instead of the
 * API: the browser reports it as a network error, while `curl localhost:8000` from the
 * terminal still answers fine. Marco hit exactly that after recreating the containers. The
 * generic sentence sent him to check the backend, which was healthy; this one names the fix.
 * `mvp-init.py` also sets the port public on every run, so this is the fallback.
 */
export function networkErrorMessage(hostname: string): string {
  if (/-\d+\.(app\.github\.dev|githubpreview\.dev)$/.test(hostname)) {
    return 'Could not reach the API on port 8000. In a Codespace this is almost always port 8000 being Private: '
      + 'Ports tab → right-click 8000 → Port Visibility → Public, then reload (or run python3 mvp-init.py).';
  }
  return 'Could not reach the API. Check the backend URL, CORS, and that it is running.';
}
