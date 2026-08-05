import { Blocker } from './api-error';

/**
 * design.json §3 · AD-delguard-09 · UC-37 — the caller's OWN account (GET /account).
 *
 * `publication_status` has exactly two values, unlike a Space's three: an account has no
 * owner-facing pause lever, so `unpublished` here always means one thing — a deletion is
 * programmed, and every listing the account owns has left the catalog because of it.
 */
export interface Account {
  id: number;
  name: string;
  type: 'client' | 'provider';
  publication_status: 'published' | 'unpublished';
  /** Non-null = the earliest moment `accounts:purge` may destroy this account. */
  delete_scheduled_at: string | null;
  /** When the owner acknowledged that the purge destroys the proofs they uploaded. */
  proof_loss_confirmed_at: string | null;
  created_at: string;
  updated_at: string;
  collaborators_count?: number;
  campaigns_count?: number;
}

/**
 * DELETE /account answers 200 in TWO different shapes and the screen must tell them
 * apart: with `account` present the deletion was PROGRAMMED (objects still in use, and
 * `blockers` says which); without it, the account is gone and the session with it.
 */
export interface AccountDeletionResponse {
  message: string;
  account?: Account;
  blockers?: Blocker[];
}
