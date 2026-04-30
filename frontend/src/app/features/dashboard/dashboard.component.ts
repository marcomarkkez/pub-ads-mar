import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="dashboard">
      <h1>Welcome, {{ user()?.name }}</h1>
      <p class="text-muted">You are logged in as <strong>{{ user()?.role }}</strong>.</p>

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
            <a routerLink="/payments/proofs" class="action-card">
              <span class="action-icon">📸</span>
              <span class="action-label">Review Proofs</span>
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
      margin-bottom: 32px;
    }
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
export class DashboardComponent {
  private auth = inject(AuthService);
  user = this.auth.user;
  role = this.auth.userRole;
}
