import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';
import { Booking } from '../../../core/models';

@Component({
  selector: 'app-provider-booking-list',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="page-header">
      <h1>Bookings</h1>
    </div>

    @if (loading()) {
      <div class="loading-container"><span class="spinner"></span></div>
    } @else if (error()) {
      <div class="alert alert-error">{{ error() }}</div>
    } @else if (bookings().length === 0) {
      <div class="empty-state">
        <div class="empty-icon">&#x1F4C5;</div>
        <p>No bookings yet. When clients book your spaces, they will appear here.</p>
      </div>
    } @else {
      <div class="card">
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Space</th>
                <th>Client</th>
                <th>Dates</th>
                <th>Total Price</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @for (booking of bookings(); track booking.id) {
                <tr>
                  <td>
                    @if (booking.space) {
                      <a [routerLink]="['/provider/spaces', booking.space_id]" class="link">
                        {{ booking.space.name }}
                      </a>
                    } @else {
                      Space #{{ booking.space_id }}
                    }
                  </td>
                  <td>
                    @if (booking.client) {
                      {{ booking.client.name }}
                      <span class="text-muted">({{ booking.client.email }})</span>
                    } @else {
                      Client #{{ booking.client_user_id }}
                    }
                  </td>
                  <td>{{ booking.start_date }} &mdash; {{ booking.end_date }}</td>
                  <td>{{ booking.total_price | currency:'EUR' }}</td>
                  <td>
                    <span class="badge" [class]="'badge-' + booking.status">
                      {{ booking.status | titlecase }}
                    </span>
                  </td>
                  <td class="actions">
                    @if (booking.status === 'pending') {
                      <button class="btn btn-sm btn-success"
                              (click)="updateStatus(booking, 'confirmed')"
                              [disabled]="updatingId() === booking.id">
                        @if (updatingId() === booking.id) {
                          <span class="spinner"></span>
                        } @else {
                          Confirm
                        }
                      </button>
                    }
                    @if (booking.status === 'pending' || booking.status === 'confirmed') {
                      <button class="btn btn-sm btn-danger"
                              (click)="updateStatus(booking, 'cancelled')"
                              [disabled]="updatingId() === booking.id">
                        Cancel
                      </button>
                    }
                    @if (booking.status === 'confirmed') {
                      <span class="confirmed-label">Confirmed</span>
                    }
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
    .link {
      color: var(--primary);
      text-decoration: none;
      font-weight: 600;
      &:hover { text-decoration: underline; }
    }
    .text-muted {
      color: var(--text-muted);
      font-size: 12px;
    }
    .actions {
      display: flex;
      gap: 6px;
      align-items: center;
      white-space: nowrap;
    }
    .confirmed-label {
      font-size: 12px;
      font-weight: 600;
      color: var(--success);
    }
  `],
})
export class ProviderBookingListComponent implements OnInit {
  bookings = signal<Booking[]>([]);
  loading = signal(false);
  error = signal('');
  updatingId = signal<number | null>(null);

  private readonly api = environment.apiUrl;

  constructor(
    private http: HttpClient,
    private notify: NotificationService,
  ) {}

  ngOnInit(): void {
    this.loadBookings();
  }

  loadBookings(): void {
    this.loading.set(true);
    this.error.set('');

    this.http.get<Booking[]>(`${this.api}/provider/bookings`).subscribe({
      next: (res) => {
        this.bookings.set(res);
        this.loading.set(false);
      },
      error: (err) => {
        this.error.set(err.error?.message || 'Failed to load bookings.');
        this.loading.set(false);
      },
    });
  }

  updateStatus(booking: Booking, status: 'confirmed' | 'cancelled'): void {
    if (status === 'cancelled' && !confirm('Are you sure you want to cancel this booking?')) {
      return;
    }

    this.updatingId.set(booking.id);

    this.http.put<{ data: Booking }>(`${this.api}/provider/bookings/${booking.id}`, { status }).subscribe({
      next: (res) => {
        this.bookings.update(list =>
          list.map(b => b.id === booking.id ? res.data : b)
        );
        this.notify.success(`Booking ${status}.`);
        this.updatingId.set(null);
      },
      error: (err) => {
        this.notify.error(err.error?.message || `Failed to ${status} booking.`);
        this.updatingId.set(null);
      },
    });
  }
}
