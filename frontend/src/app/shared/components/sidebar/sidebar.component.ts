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

  // design.md §10 — ONE "Messages" area for ALL roles; no separate Support/Tickets menu.
  navItems = computed<NavItem[]>(() => {
    const role = this.role();
    switch (role) {
      case 'client':
        return [
          // design.md §1/§13: the client has NO dashboard — lands on the Spaces map.
          { label: 'Explore Spaces', icon: '🗺️', route: '/client/spaces' },
          { label: 'Campaigns', icon: '📢', route: '/client/campaigns' },
          { label: 'Bookings', icon: '📅', route: '/client/bookings' },
          { label: 'Invoices', icon: '📄', route: '/client/invoices' },
          { label: 'Wallet', icon: '👛', route: '/client/wallet' },
          { label: 'Messages', icon: '💬', route: '/messages' },
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
          // design.md §12 — admin's Messages surface is the read-only oversight screen.
          { label: 'Messages', icon: '💬', route: '/admin/oversight' },
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
