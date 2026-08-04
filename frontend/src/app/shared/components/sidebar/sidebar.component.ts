import { Component, computed, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';

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
  role = this.auth.userRole;
  menuOpen = false;

  /**
   * Owner-only surfaces. §3: the account OWNER manages collaborators (UC-19); a
   * collaborator never can. The permission map is the only owner signal the SPA has
   * today (there is no `is_owner` on /me yet) — the API re-checks ownership anyway.
   */
  canManageCollaborators = computed(() => this.auth.hasPermission('collaborators', 'create'));

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
        ];
      case 'provider':
        return [
          { label: 'Dashboard', icon: '📊', route: '/dashboard' },
          { label: 'My Spaces', icon: '📍', route: '/provider/spaces' },
          { label: 'Bookings', icon: '📅', route: '/provider/bookings' },
          { label: 'Proofs', icon: '📸', route: '/provider/proofs' },
          { label: 'Messages', icon: '💬', route: '/messages' },
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
