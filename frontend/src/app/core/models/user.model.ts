export interface User {
  id: number;
  name: string;
  email: string;
  role: 'client' | 'provider' | 'admin' | 'support' | 'payments';
  phone: string | null;
  company_name: string | null;
  address: string | null;
  is_active: boolean;
  email_verified_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface LoginRequest {
  email: string;
  password: string;
}

export interface RegisterRequest {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  role: 'client' | 'provider';
}

/**
 * POST /api/login and POST /api/register.
 *
 * BR-8 · §3 — these now answer with the SAME account block as GET /me
 * (`account_id` / `is_owner` / `collaborating_on`), so a session can start complete
 * from one round trip instead of guessing until /me lands.
 *
 * The three are OPTIONAL here on purpose: the backend change ships alongside this one,
 * and a frontend that hard-required them would blank out every account signal against
 * an older API. AuthService treats "absent" as "ask /me" and "present" as "use it" —
 * see AuthService.startSession.
 */
export interface AuthResponse {
  user: User;
  token: string;
  permissions: Record<string, boolean>;
  account_id?: number | null;
  is_owner?: boolean;
  collaborating_on?: number[];
}

/**
 * GET /api/me — the ACCOUNT CONTEXT (BR-8), not just the user row.
 *
 * §3 — read these three together, because each one alone lies:
 *  - `account_id`      the caller's own account, null for staff (accounts.type is
 *                      client|provider, so support/payments/admin have none).
 *  - `is_owner`        `account_id !== null`. Under the MVP unique index on
 *                      users.account_id EVERY registered client/provider owns exactly
 *                      one account, so this separates "has an account" from "staff" —
 *                      it does NOT separate an owner from a collaborator.
 *  - `collaborating_on` the accounts of OTHER people this user has an accepted
 *                      collaborator grant on. Non-empty does not stop them owning
 *                      their own account: the two roles stack on one human.
 */
export interface MeResponse {
  user: User;
  account_id: number | null;
  is_owner: boolean;
  collaborating_on: number[];
  permissions: Record<string, boolean>;
}

export type Permissions = Record<string, boolean>;
