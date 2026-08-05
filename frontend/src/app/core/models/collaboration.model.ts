import { Account } from './account.model';

/**
 * design.json §3 (UC-19/UC-20 · WALK-6 step 3) — an invitation addressed to ME, as
 * returned by `GET /collaborations`.
 *
 * EVERY field here was read off CollaborationController::index and the Collaborator model;
 * none is guessed (EH-2). The endpoint returns the Collaborator rows themselves, with two
 * relations eager-loaded:
 *
 *   `account`     the FULL Account model — this is somebody ELSE's account, the one I am
 *                 being invited into, so `account.name` is what the decision is about.
 *   `invited_by`  id + name ONLY, and deliberately so ("an endpoint that hands out a full
 *                 User model teaches the next screen to do the same"). Laravel serialises
 *                 the `invitedBy` relation under its snake_case name — $snakeAttributes is
 *                 true — hence `invited_by` here.
 *
 * `status` is only ever `pending` or `accepted` in this list: index() filters the other two
 * out, because a revoked or declined row grants nothing and offers nothing.
 */
export interface Collaboration {
  id: number;
  account_id: number;
  invited_by_user_id: number | null;
  user_id: number | null;
  email: string;
  role: string;
  status: 'pending' | 'accepted';
  created_at: string;
  updated_at: string;
  account?: Account;
  invited_by?: { id: number; name: string } | null;
}
