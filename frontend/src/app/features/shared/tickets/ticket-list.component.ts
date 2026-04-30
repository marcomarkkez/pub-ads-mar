import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';
import { Ticket } from '../../../core/models';

@Component({
  selector: 'app-ticket-list',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="page-header">
      <h1>My Tickets</h1>
      <a routerLink="/tickets/new" class="btn btn-primary new-btn">New Ticket</a>
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
        <p>No tickets yet. Create one if you need help!</p>
      </div>
    }

    @if (!loading() && tickets().length > 0) {
      <div class="card">
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Subject</th>
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
                    <a [routerLink]="['/tickets', ticket.id]" class="ticket-link">
                      {{ ticket.subject }}
                    </a>
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
                    <a [routerLink]="['/tickets', ticket.id]" class="btn btn-sm">View</a>
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
    .new-btn {
      width: auto;
    }
    .ticket-link {
      color: var(--primary);
      text-decoration: none;
      font-weight: 600;
      &:hover { text-decoration: underline; }
    }
    .badge-low { background: #dbeafe; color: #1e40af; }
    .badge-medium { background: #fef3c7; color: #92400e; }
    .badge-high { background: #fee2e2; color: #991b1b; }
  `],
})
export class TicketListComponent implements OnInit {
  private readonly api = environment.apiUrl;

  tickets = signal<Ticket[]>([]);
  loading = signal(false);
  error = signal('');

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

    this.http.get<Ticket[]>(`${this.api}/tickets`).subscribe({
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
