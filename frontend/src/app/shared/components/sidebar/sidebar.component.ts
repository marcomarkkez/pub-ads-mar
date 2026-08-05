import { Component, computed, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';
import { CollaborationService } from '../../../core/services/collaboration.service';

interface NavItem {
  label: string;
  icon: string;
  route: string;
}

@Component({
  selector: 'app-sidebar',
  standalone: true,
  imports: [CommonModule, RouterLink, RouterLinkActive],
  templateUrl: './sidebar.component.html',
  styleUrl: './sidebar.component.scss',
})
export class SidebarComponent {
  private auth = inject(AuthService);
  private collabs = inject(CollaborationService);
  role = this.auth.userRole;
  menuOpen = false;

  constructor() {
    // §3 · UC-20 — the menu has to know whether there is anything to answer BEFORE the
    // screen is opened, so the list is fetched once when the authenticated shell mounts
    // (this component lives inside MainLayout). The service no-ops for staff roles and
    // caches, so this is one request per session, not one per navigation.
    this.collabs.refresh();
  }

  /**
   * §3 · UC-19/UC-20 (WALK-6 step 3) — the Invitations entry appears ONLY when there is
   * something in it. An always-present tab that is empty for almost every user is noise,
   * and noise is what makes people stop reading the menu; but with no entry at all the
   * invitation flow had no way in and `collaborating_on` could never be filled, which is
   * the dead end this closes.
   */
  hasInvitations = this.collabs.hasAny;

  /** Pending ones only: an accepted collaboration is a fact to look at, not a task. */
  pendingInvitations = this.collabs.pendingCount;

  /**
   * The count travels in the LABEL, built here in TypeScript. Deliberately not a badge
   * interpolated in the template next to the icon: this sidebar renders its icons through
   * {{ item.icon }}, and mixing an emoji and a computed number inside one interpolation is
   * the exact shape that has silently rendered empty in this codebase before.
   */
  private invitationsItem(): NavItem[] {
    if (!this.hasInvitations()) return [];
    const n = this.pendingInvitations();

    return [{
      label: n > 0 ? 'Invitations (' + n + ')' : 'Invitations',
      icon: '📨',
      route: '/collaborations',
    }];
  }

  /**
   * BR-8 · §3 (UC-19) — Collaborators is the account OWNER's screen.
   *
   * The approximation this replaces was `hasPermission('collaborators','create')`, which is
   * a ROLE fact, not an account fact: it is true for every client alike and could never
   * tell two clients apart. /me now carries the real account context.
   *
   * `is_owner` is the field that matches what the endpoint actually accepts:
   * Client\CollaboratorController scopes every read and write to
   * `$request->user()->account_id`, so the question the screen asks is "do I have an
   * account of my own?" — exactly `is_owner`.
   *
   * `collaborating_on` is deliberately NOT the gate. Under the MVP unique index on
   * users.account_id every registered client owns exactly one account, INCLUDING a person
   * who also holds a collaborator grant on someone else's; the two roles stack on one
   * human. Gating on `collaborating_on.length === 0` would hide a person's own
   * collaborators screen from them merely because they also help on a friend's account,
   * and the API would have served it. It is surfaced on AuthService as `isCollaborator()`
   * for labelling the OTHER accounts, never for locking this one.
   */
  canManageCollaborators = computed(() => this.auth.isAccountOwner());

  /**
   * §3 · UC-37 — the same fact under the name the Account screen needs it by:
   * GET/DELETE /account are gated `role:client,provider` and 404 when the caller has no
   * account, which is `account_id !== null`, which is `is_owner`. Kept as its own
   * computed rather than reusing the collaborators one so that when the two questions
   * stop having the same answer (multi-owner accounts), only one of them moves.
   */
  hasOwnAccount = computed(() => this.auth.isAccountOwner());

  // design.json §10 — ONE "Messages" area for ALL roles; no separate Support/Tickets menu.
  navItems = computed<NavItem[]>(() => {
    const role = this.role();
    switch (role) {
      case 'client':
        return [
          // design.json §1/§13: the client has NO dashboard — lands on the Spaces map.
          { label: 'Explore Spaces', icon: '🗺️', route: '/client/spaces' },
          { label: 'Campaigns', icon: '📢', route: '/client/campaigns' },
          { label: 'Bookings', icon: '📅', route: '/client/bookings' },
          { label: 'Invoices', icon: '📄', route: '/client/invoices' },
          { label: 'Wallet', icon: '👛', route: '/client/wallet' },
          { label: 'Messages', icon: '💬', route: '/messages' },
          // design.json §2/§3 (UC-19) — Collaborators is its OWN account-scoped tab under
          // Configurations, shown only to the account OWNER (the one who may invite/revoke:
          // `collaborators.create`). It is no longer a card inside campaign-detail.
          ...(this.canManageCollaborators()
            ? [{ label: 'Collaborators', icon: '👥', route: '/client/collaborators' }]
            : []),
          // design.json §3 · UC-37 — GET/DELETE /account answers exactly "I have an
          // account of my own", i.e. `is_owner`, so the tab is gated on the same signal
          // as Collaborators and for the same reason.
          ...(this.hasOwnAccount()
            ? [{ label: 'Account', icon: '⚙️', route: '/account' }]
            : []),
        ];
      case 'provider':
        return [
          { label: 'Dashboard', icon: '📊', route: '/dashboard' },
          { label: 'My Spaces', icon: '📍', route: '/provider/spaces' },
          { label: 'Bookings', icon: '📅', route: '/provider/bookings' },
          { label: 'Proofs', icon: '📸', route: '/provider/proofs' },
          { label: 'Messages', icon: '💬', route: '/messages' },
          // Same account screen, same gate. A provider deleting themselves is the case
          // the dispute guardrail exists for (§3 · AD-delguard-09), so they need the
          // screen that shows the refusal at least as much as a client does.
          ...(this.hasOwnAccount()
            ? [{ label: 'Account', icon: '⚙️', route: '/account' }]
            : []),
        ];
      case 'admin':
        return [
          { label: 'Dashboard', icon: '📊', route: '/dashboard' },
          { label: 'Users', icon: '👥', route: '/admin/users' },
          { label: 'Permissions', icon: '🔐', route: '/admin/permissions' },
          // design.json §12 — admin's Messages surface is the read-only oversight screen.
          { label: 'Messages', icon: '💬', route: '/admin/oversight' },
          // design.json §12/§8 (UC-29, UC-32) — takedown/freeze + payout stop/clawback:
          // the only actions Admin may take on the platform, every one audited.
          { label: 'Moderation', icon: '🛑', route: '/admin/moderation' },
          // design.json §12 (UC-31) — the append-only log; Admin is its only reader.
          { label: 'Audit Log', icon: '📜', route: '/admin/audit' },
          { label: 'Configurations', icon: '⚙️', route: '/admin/config' },
        ];
      case 'support':
        return [
          { label: 'Dashboard', icon: '📊', route: '/dashboard' },
          { label: 'Messages', icon: '💬', route: '/messages' },
        ];
      case 'payments':
        return [
          { label: 'Dashboard', icon: '📊', route: '/dashboard' },
          { label: 'Payments', icon: '💳', route: '/payments/review' },
          { label: 'Messages', icon: '💬', route: '/messages' },
        ];
      default:
        return [];
    }
  });
}
