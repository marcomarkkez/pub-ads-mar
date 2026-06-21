import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../core/services/auth.service';

// [todo B8] One normalized stat tile (label + primary value + optional sub-line).
interface StatCard { label: string; value: string; sub?: string; }

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="dashboard">
      <h1>Welcome, {{ user()?.name }}</h1>
      <p class="text-muted">You are logged in as <strong>{{ user()?.role }}</strong>.</p>

      <!-- [todo B8] Stats: every non-client role shows live numbers for their end. -->
      @if (role() !== 'client') {
        @if (loadingStats()) {
          <div class="loading-container"><span class="spinner"></span></div>
        } @else if (stats().length > 0) {
          <div class="stats-grid">
            @for (s of stats(); track s.label) {
              <div class="stat-card">
                <span class="stat-value">{{ s.value }}</span>
                <span class="stat-label">{{ s.label }}</span>
                @if (s.sub) { <span class="stat-sub">{{ s.sub }}</span> }
              </div>
            }
          </div>
        }
      }

      <h2 class="section-title">Quick actions</h2>
      <div class="quick-actions">
        @switch (role()) {
          @case ('client') {
            <a routerLink="/client/spaces" class="action-card">
              <span class="action-icon">🗺️</span>
              <span class="action-label">Explore Spaces</span>
            </a>
            <a routerLink="/client/campaigns" class="action-card">
              <span class="action-icon">📢</span>
              <span class="action-label">My Campaigns</span>
            </a>
            <a routerLink="/client/bookings" class="action-card">
              <span class="action-icon">📅</span>
              <span class="action-label">My Bookings</span>
            </a>
          }
          @case ('provider') {
            <a routerLink="/provider/spaces" class="action-card">
              <span class="action-icon">📍</span>
              <span class="action-label">My Spaces</span>
            </a>
            <a routerLink="/provider/bookings" class="action-card">
              <span class="action-icon">📅</span>
              <span class="action-label">Bookings</span>
            </a>
            <a routerLink="/provider/proofs" class="action-card">
              <span class="action-icon">📸</span>
              <span class="action-label">Upload Proofs</span>
            </a>
          }
          @case ('admin') {
            <a routerLink="/admin/users" class="action-card">
              <span class="action-icon">👥</span>
              <span class="action-label">Manage Users</span>
            </a>
            <a routerLink="/admin/permissions" class="action-card">
              <span class="action-icon">🔐</span>
              <span class="action-label">Role Permissions</span>
            </a>
          }
          @case ('support') {
            <a routerLink="/support/tickets" class="action-card">
              <span class="action-icon">🎫</span>
              <span class="action-label">Support Tickets</span>
            </a>
          }
          @case ('payments') {
            <a routerLink="/payments/review" class="action-card">
              <span class="action-icon">💳</span>
              <span class="action-label">Review Payments</span>
            </a>
          }
        }
      </div>
    </div>
  `,
  styles: [`
    .dashboard h1 {
      font-size: 24px;
      margin-bottom: 4px;
    }
    .text-muted {
      color: var(--text-muted);
      margin-bottom: 24px;
    }
    .section-title {
      font-size: 16px;
      margin: 28px 0 12px;
    }
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
      gap: 16px;
    }
    .stat-card {
      display: flex;
      flex-direction: column;
      gap: 2px;
      padding: 18px 20px;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
    }
    .stat-value { font-size: 26px; font-weight: 700; color: var(--text); }
    .stat-label { font-size: 13px; color: var(--text-muted); }
    .stat-sub { font-size: 12px; color: var(--primary); font-weight: 600; margin-top: 2px; }
    .quick-actions {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      gap: 16px;
    }
    .action-card {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
      padding: 24px 16px;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      text-decoration: none;
      color: var(--text);
      transition: all 0.15s;
      &:hover {
        border-color: var(--primary);
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transform: translateY(-2px);
      }
    }
    .action-icon { font-size: 32px; }
    .action-label { font-weight: 600; font-size: 14px; }
  `],
})
export class DashboardComponent implements OnInit {
  private auth = inject(AuthService);
  private http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  user = this.auth.user;
  role = this.auth.userRole;
  stats = signal<StatCard[]>([]);
  loadingStats = signal(false);

  ngOnInit(): void {
    const role = this.role();
    if (!role || role === 'client') return;
    this.loadStats(role);
  }

  // [todo B8] Each non-client role fetches its own stats endpoint and maps to tiles.
  private loadStats(role: string): void {
    const endpoints: Record<string, string> = {
      provider: '/provider/dashboard',
      payments: '/payments/dashboard',
      support: '/support/dashboard',
      admin: '/admin/dashboard',
    };
    const url = endpoints[role];
    if (!url) return;

    this.loadingStats.set(true);
    this.http.get<any>(`${this.api}${url}`).subscribe({
      next: (d) => {
        this.stats.set(this.mapStats(role, d));
        this.loadingStats.set(false);
      },
      error: () => this.loadingStats.set(false),
    });
  }

  private money(n: number): string {
    return '$' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' MXN';
  }

  private mapStats(role: string, d: any): StatCard[] {
    switch (role) {
      case 'provider':
        return [
          { label: 'Active spaces', value: `${d.active_spaces ?? 0}`, sub: `${d.total_spaces ?? 0} total` },
          { label: 'Pending bookings', value: `${d.pending_bookings ?? 0}` },
          { label: 'Confirmed bookings', value: `${d.confirmed_bookings ?? 0}` },
          { label: 'Revenue paid', value: this.money(d.revenue_paid ?? d.total_revenue) },
          { label: 'On hold', value: this.money(d.revenue_held) },
          { label: 'Refunded', value: this.money(d.revenue_refunded) },
          { label: 'Unread messages', value: `${d.unread_messages ?? 0}` },
        ];
      case 'payments':
        return [
          { label: 'Paid', value: `${d.paid?.count ?? 0}`, sub: this.money(d.paid?.amount) },
          { label: 'On hold', value: `${d.on_hold?.count ?? 0}`, sub: this.money(d.on_hold?.amount) },
          { label: 'Pending', value: `${d.pending?.count ?? 0}`, sub: this.money(d.pending?.amount) },
          { label: 'Refunded', value: `${d.refunded?.count ?? 0}`, sub: this.money(d.refunded?.amount) },
        ];
      case 'support':
        return [
          { label: 'Waiting to review', value: `${d.waiting_review ?? 0}` },
          { label: 'Open', value: `${d.open ?? 0}` },
          { label: 'In progress', value: `${d.in_progress ?? 0}` },
          { label: 'Waiting on user', value: `${d.waiting_user ?? 0}` },
          { label: 'Solved', value: `${d.solved ?? 0}` },
          { label: 'Unassigned', value: `${d.unassigned ?? 0}` },
        ];
      case 'admin':
        return [
          { label: 'Users', value: `${d.users_total ?? 0}` },
          { label: 'Active spaces', value: `${d.active_spaces ?? 0}`, sub: `${d.total_spaces ?? 0} total` },
          { label: 'Active campaigns', value: `${d.active_campaigns ?? 0}`, sub: `${d.total_campaigns ?? 0} total` },
          { label: 'Open tickets', value: `${d.open_tickets ?? 0}` },
          { label: 'Payments held', value: `${d.payments_held?.count ?? 0}`, sub: this.money(d.payments_held?.amount) },
          { label: 'Revenue paid', value: `${d.revenue_paid?.count ?? 0}`, sub: this.money(d.revenue_paid?.amount) },
        ];
      default:
        return [];
    }
  }
}
