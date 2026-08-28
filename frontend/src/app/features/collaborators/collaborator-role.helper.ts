/**
 * design.json §3 — client-side and provider-side are TWO SEPARATE ecosystems with
 * DIFFERENT subrole sets; the sets share NO names and a subrole on one side is
 * unrelated to the other.
 *
 * [owner 2026-07-17] `installator` is PROVIDER-side ONLY. It used to be offered in the
 * client collaborator form, which minted a client collaborator that the chat ACL then
 * refused: the person could see campaigns but could never open a chat, with nothing
 * explaining why. The client form offers publicist/manager and nothing else.
 */
export interface CollaboratorRoleOption {
  value: string;
  label: string;
  /** What the subrole may do — shown next to the picker so the owner picks knowingly. */
  description: string;
}

/** §3 CLIENT-side subroles — exactly these two. */
export const CLIENT_COLLABORATOR_ROLES: CollaboratorRoleOption[] = [
  {
    value: 'publicist',
    label: 'Publicist',
    description: 'Operational: campaigns, adsets, ads, proof review, chats with providers. Cannot see money or billing.',
  },
  {
    value: 'manager',
    label: 'Manager',
    description: 'Everything the account owner can do, including money and billing.',
  },
];

/**
 * §3 PROVIDER-side subroles. Kept here only so a role value coming back from the API can
 * be labelled; the provider-side collaborator screen is deferred (F02 note).
 * Installator uploads proofs and sees new-space requests — it has NO chat access.
 */
export const PROVIDER_COLLABORATOR_ROLES: CollaboratorRoleOption[] = [
  {
    value: 'installator',
    label: 'Installator',
    description: 'Sees new-space requests and uploads proofs. No chats, no money, no listings.',
  },
  {
    value: 'sales',
    label: 'Sales',
    description: 'Replies to client messages. Cannot remove people, edit spaces, or touch money.',
  },
  {
    value: 'supervisor',
    label: 'Supervisor',
    description: 'Edits almost everything in the provider account except removing people, account configuration, deleting spaces and money.',
  },
];

const ALL_ROLES = [...CLIENT_COLLABORATOR_ROLES, ...PROVIDER_COLLABORATOR_ROLES];

export function roleBadgeLabel(role: string | undefined | null): string {
  if (!role) return '';
  return ALL_ROLES.find((r) => r.value === role)?.label ?? role;
}

export function roleDescription(role: string | undefined | null): string {
  if (!role) return '';
  return ALL_ROLES.find((r) => r.value === role)?.description ?? '';
}
