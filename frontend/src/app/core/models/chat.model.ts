// UC-8/UC-9 · design.json §10 — the ONE chat primitive (replaces Conversation + Ticket).
// A chat's nature is DERIVED (never a column) from participants + objects + flags.

/** Attachable object kinds (design.json §10 — zero..many polymorphic attachments). */
export type ChatObjectType = 'ad' | 'adset' | 'campaign' | 'space' | 'payment' | 'booking';

/** Persisted chat state; the user-facing UI collapses this to Abierto/Cerrado. */
export type ChatStatus = 'open' | 'in_progress' | 'resolved' | 'closed';

/** Participant side (design.json §10 — access is by MEMBERSHIP, never a flag). */
export type ChatSide = 'client' | 'provider' | 'support' | 'payments' | 'admin';

/** Flag types (design.json §10 — volatile human annotations; money STATE stays on payments.status). */
export type ChatFlagType =
  | 'payment_held'
  | 'refund'
  | 'payout_hold'
  | 'cancel'
  | 'mismatch'
  | string;

/** Derived nature, sent by the server for labels/filters — NEVER persisted (design.json §10). */
export type ChatNature =
  | 'client_provider'
  | 'support_client'
  | 'support_provider'
  | 'internal'
  | 'general'
  | string;

export interface ChatParticipant {
  id: number;
  chat_id?: number;
  user_id: number;
  side: ChatSide;
  announced: boolean;
  joined_at: string | null;
  left_at: string | null;
  user?: { id: number; name: string; role: string };
}

/** A proof preview the server hydrates onto a chat object (design.json §7 — dispute subject). */
export interface ChatObjectProof {
  id: number;
  media_type: string;   // 'image' | 'video' | …
  status: string;
  file_url: string;
}

export interface ChatObject {
  id: number;
  chat_id?: number;
  object_type: ChatObjectType;
  object_id: number;
  label?: string;
  // Compact preview the server hydrates (name + related proof media, when any).
  object?: { id: number; name?: string; proof?: ChatObjectProof; [key: string]: unknown };
}

export interface ChatFlag {
  id: number;
  chat_id?: number;
  type: ChatFlagType;
  reason: string | null;
  active: boolean;
  created_by: number | null;
  created_at: string;
  superseded_at: string | null;
}

export interface ChatMessage {
  id: number;
  chat_id: number;
  sender_user_id: number | null;
  body: string;
  kind?: 'user' | 'system' | string;
  is_read?: boolean;
  created_at: string;
  updated_at?: string;
  sender?: { id: number; name: string; role: string };
}

export interface Chat {
  id: number;
  status: ChatStatus;
  nature?: ChatNature;
  subject?: string | null;
  created_at: string;
  updated_at: string;
  participants?: ChatParticipant[];
  objects?: ChatObject[];
  // The server serializes the active-flags relation as `active_flags`; `flags` kept for compat.
  active_flags?: ChatFlag[];
  flags?: ChatFlag[];
  messages?: ChatMessage[];
  latest_message?: ChatMessage;
  messages_count?: number;
}

/** design.json §10 — the ONLY user-facing state legend. */
export function displayChatStatus(status: ChatStatus | string | undefined): 'Abierto' | 'Cerrado' {
  return status === 'closed' ? 'Cerrado' : 'Abierto';
}

/** Active (non-superseded) flags for a chat. Server sends them under `active_flags`. */
export function activeFlags(chat: Pick<Chat, 'flags'> & { active_flags?: ChatFlag[] }): ChatFlag[] {
  return (chat.active_flags ?? chat.flags ?? []).filter(f => f.active);
}

/**
 * design.json §10 — user-facing chat title DERIVED from nature. A client/provider chat
 * with support reads "Chat con soporte" (never the internal nature enum or "Chat #id").
 */
export function chatTitle(chat: Chat): string {
  if (chat.subject) return chat.subject;
  switch (chat.nature) {
    case 'support_client':
    case 'support_provider':
      return 'Chat con soporte';
    case 'internal':
      return 'Soporte ↔ Pagos';
  }
  const o = chat.objects?.[0];
  return o ? (o.label || `${o.object_type} #${o.object_id}`) : `Chat #${chat.id}`;
}

// ---------------------------------------------------------------------------
// Invoice & Collaborator models (moved here from the retired conversation.model.ts;
// unrelated to chats but kept in the models barrel that other features import).
// ---------------------------------------------------------------------------
export interface Invoice {
  id: number;
  campaign_id: number;
  invoice_number: string;
  amount: number;
  status: 'draft' | 'issued' | 'paid' | 'cancelled';
  issued_at: string | null;
  // No `paid_at`: the column does not exist on `invoices`; `status === 'paid'` is the fact.
  created_at: string;
  updated_at: string;
  campaign?: { id: number; name: string };
}

/**
 * §3 — client-side and provider-side are TWO SEPARATE ecosystems with DIFFERENT subrole
 * sets that share NO names. `installator` is PROVIDER-side only (owner 2026-07-17).
 */
export type ClientCollaboratorRole = 'publicist' | 'manager';
export type ProviderCollaboratorRole = 'installator' | 'sales' | 'supervisor';

export interface Collaborator {
  id: number;
  // §3 — collaborators are ACCOUNT-scoped (never campaign-scoped): one collaborator acts
  // across ALL of the account's campaigns/spaces.
  account_id?: number;
  user_id: number | null;
  email: string;
  role?: ClientCollaboratorRole | ProviderCollaboratorRole | string;
  status?: 'pending' | 'accepted' | 'revoked' | string;
  created_at: string;
  user?: { id: number; name: string; email: string };
}
