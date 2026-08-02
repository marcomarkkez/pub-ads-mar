import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';
import { Booking } from '../../../core/models';

@Component({
  selector: 'app-provider-booking-list',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
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
                    <!-- design.json §5 [F06]: provider acts with Approve or Reject (reason OPTIONAL); no counter-offers. -->
                    @if (rejectingId() === booking.id) {
                      <input type="text" class="reject-reason" [(ngModel)]="rejectReason"
                             name="rejectReason" placeholder="Reason (optional)" />
                      <button class="btn btn-sm btn-danger" (click)="confirmReject(booking)"
                              [disabled]="updatingId() === booking.id">
                        @if (updatingId() === booking.id) { <span class="spinner"></span> } @else { Confirm reject }
                      </button>
                      <button class="btn btn-sm" (click)="cancelReject()">Back</button>
                    } @else {
                      @if (booking.status === 'pending') {
                        <button class="btn btn-sm btn-success"
                                (click)="updateStatus(booking, 'confirmed')"
                                [disabled]="updatingId() === booking.id">
                          @if (updatingId() === booking.id) {
                            <span class="spinner"></span>
                          } @else {
                            Approve
                          }
                        </button>
                        <button class="btn btn-sm btn-danger"
                                (click)="openReject(booking)"
                                [disabled]="updatingId() === booking.id">
                          Reject
                        </button>
                      }
                      @if (booking.status === 'confirmed') {
                        <button class="btn btn-sm btn-danger"
                                (click)="updateStatus(booking, 'cancelled')"
                                [disabled]="updatingId() === booking.id">
                          Cancel
                        </button>
                        <span class="confirmed-label">Confirmed</span>
                      }
                    }
                    <!-- [todo B2] Provider needs a quick path to add proof for a booking awaiting it. -->
                    @if (booking.status === 'waiting_proof' || booking.status === 'active' || booking.status === 'confirmed') {
                      <a class="btn btn-sm btn-primary" style="width:auto"
                         [routerLink]="['/provider/proofs']" [queryParams]="{ booking_id: booking.id }">
                        📸 Add proof
                      </a>
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
    .reject-reason {
      font-size: 12px;
      padding: 4px 8px;
      max-width: 180px;
    }
  `],
})
export class ProviderBookingListComponent implements OnInit {
  bookings = signal<Booking[]>([]);
  loading = signal(false);
  error = signal('');
  updatingId = signal<number | null>(null);
  rejectingId = signal<number | null>(null);
  rejectReason = '';

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

  // design.json §5 [F06]: Reject with an OPTIONAL reason.
  openReject(booking: Booking): void {
    this.rejectingId.set(booking.id);
    this.rejectReason = '';
  }

  cancelReject(): void {
    this.rejectingId.set(null);
    this.rejectReason = '';
  }

  confirmReject(booking: Booking): void {
    this.updatingId.set(booking.id);
    const payload = { status: 'rejected', rejection_reason: this.rejectReason.trim() || null };
    this.http.put<{ data: Booking }>(`${this.api}/provider/bookings/${booking.id}`, payload).subscribe({
      next: (res) => {
        this.bookings.update(list => list.map(b => b.id === booking.id ? res.data : b));
        this.notify.success('Booking rejected.');
        this.updatingId.set(null);
        this.rejectingId.set(null);
        this.rejectReason = '';
      },
      error: (err) => {
        this.notify.error(err.error?.message || 'Failed to reject booking.');
        this.updatingId.set(null);
      },
    });
  }
}
