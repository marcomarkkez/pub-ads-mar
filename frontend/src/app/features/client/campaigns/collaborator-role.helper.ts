export interface CollaboratorRoleOption {
  value: string;
  label: string;
}

export const COLLABORATOR_ROLES: CollaboratorRoleOption[] = [
  { value: 'installator', label: 'Installator (proof upload only)' },
  { value: 'publicist', label: 'Publicist (campaigns + chats)' },
  { value: 'manager', label: 'Manager (full access)' },
];

export function roleBadgeLabel(role: string): string {
  return COLLABORATOR_ROLES.find((r) => r.value === role)?.label ?? role;
}
