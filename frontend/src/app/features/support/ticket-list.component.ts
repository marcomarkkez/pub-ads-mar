import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { NotificationService } from '../../core/services/notification.service';
import { Ticket } from '../../core/models';

@Component({
  selector: 'app-support-ticket-list',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  template: `
    <div class="page-header">
      <h1>Support Tickets</h1>
      <div class="filter-group">
        <label for="statusFilter">Status:</label>
        <select id="statusFilter" [(ngModel)]="statusFilter" (ngModelChange)="loadTickets()">
          <option value="">All</option>
          <option value="open">Open</option>
          <option value="in_progress">In Progress</option>
          <option value="resolved">Resolved</option>
          <option value="closed">Closed</option>
        </select>
      </div>
    </div>

    @if (loading()) {
      <div class="loading-container">
        <span class="spinner"></span>
      </div>
    }

    @if (error()) {
      <div class="alert alert-error">{{ error() }}</div>
    }

    @if (!loading() && tickets().length === 0 && !error()) {
      <div class="empty-state">
        <div class="empty-icon">🎫</div>
        <p>No tickets found.</p>
      </div>
    }

    @if (!loading() && tickets().length > 0) {
      <div class="card">
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Subject</th>
                <th>User</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @for (ticket of tickets(); track ticket.id) {
                <tr>
                  <td>
                    <a [routerLink]="['/support/tickets', ticket.id]" class="ticket-link">
                      {{ ticket.subject }}
                    </a>
                  </td>
                  <td>
                    <span class="user-name">{{ ticket.user?.name || 'User #' + ticket.user_id }}</span>
                    @if (ticket.user) {
                      <span class="user-email">{{ ticket.user.email }}</span>
                    }
                  </td>
                  <td>
                    <span class="badge" [ngClass]="'badge-' + ticket.priority">
                      {{ ticket.priority }}
                    </span>
                  </td>
                  <td>
                    <span class="badge" [ngClass]="'badge-' + ticket.status">
                      {{ ticket.status }}
                    </span>
                  </td>
                  <td>{{ ticket.created_at | date:'mediumDate' }}</td>
                  <td>
                    <a [routerLink]="['/support/tickets', ticket.id]" class="btn btn-sm">View</a>
                  </td>
                </tr>
              }
            </tbody>
          </table>
        </div>
      </div>
    }
  `,
  styles: [`
    .filter-group {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      label { font-weight: 600; color: var(--text-muted); }
      select {
        padding: 6px 10px;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 13px;
        background: var(--surface);
        color: var(--text);
      }
    }
    .ticket-link {
      color: var(--primary);
      text-decoration: none;
      font-weight: 600;
      &:hover { text-decoration: underline; }
    }
    .user-name {
      display: block;
      font-weight: 600;
      font-size: 13px;
    }
    .user-email {
      display: block;
      font-size: 11px;
      color: var(--text-muted);
    }
    .badge-low { background: #dbeafe; color: #1e40af; }
    .badge-medium { background: #fef3c7; color: #92400e; }
    .badge-high { background: #fee2e2; color: #991b1b; }
  `],
})
export class SupportTicketListComponent implements OnInit {
  private readonly api = environment.apiUrl;

  tickets = signal<Ticket[]>([]);
  loading = signal(false);
  error = signal('');
  statusFilter = '';

  constructor(
    private http: HttpClient,
    private notify: NotificationService,
  ) {}

  ngOnInit(): void {
    this.loadTickets();
  }

  loadTickets(): void {
    this.loading.set(true);
    this.error.set('');

    let url = `${this.api}/support/tickets`;
    if (this.statusFilter) {
      url += `?status=${this.statusFilter}`;
    }

    this.http.get<Ticket[]>(url).subscribe({
      next: (res) => {
        this.tickets.set(res);
        this.loading.set(false);
      },
      error: (err) => {
        this.loading.set(false);
        this.error.set(err.error?.message || 'Failed to load tickets.');
        this.notify.error('Failed to load tickets.');
      },
    });
  }
}
