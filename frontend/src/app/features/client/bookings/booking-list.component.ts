import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';
import { Booking } from '../../../core/models';

@Component({
  selector: 'app-booking-list',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="page-header">
      <h1>My Bookings</h1>
    </div>

    @if (loading()) {
      <div class="loading-container"><span class="spinner"></span></div>
    } @else if (bookings().length === 0) {
      <div class="empty-state">
        <div class="empty-icon">&#128197;</div>
        <p>No bookings yet. Browse spaces and make your first booking.</p>
      </div>
    } @else {
      <div class="card">
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Space</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Total Price</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @for (booking of bookings(); track booking.id) {
                <tr>
                  <td>
                    <div>
                      <strong>{{ booking.space?.name || 'Space #' + booking.space_id }}</strong>
                      @if (booking.space?.type) {
                        <span style="display:block;font-size:11px;color:var(--text-muted);">{{ booking.space!.type }}</span>
                      }
                      @if (booking.space?.location_name) {
                        <span style="display:block;font-size:11px;color:var(--text-muted);">{{ booking.space!.location_name }}</span>
                      }
                    </div>
                  </td>
                  <td>{{ booking.start_date }}</td>
                  <td>{{ booking.end_date }}</td>
                  <td><strong>\${{ booking.total_price }}</strong></td>
                  <td>
                    <span class="badge" [class]="'badge badge-' + booking.status">{{ booking.status }}</span>
                  </td>
                  <td>
                    <button class="btn btn-sm" (click)="toggleDetail(booking.id)">
                      {{ expandedId() === booking.id ? 'Hide' : 'Details' }}
                    </button>
                  </td>
                </tr>
                @if (expandedId() === booking.id) {
                  <tr>
                    <td colspan="6" style="background:var(--hover);padding:16px;">
                      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;font-size:13px;">
                        <div>
                          <span style="color:var(--text-muted);display:block;">Booking ID</span>
                          <strong>#{{ booking.id }}</strong>
                        </div>
                        @if (booking.notes) {
                          <div>
                            <span style="color:var(--text-muted);display:block;">Notes</span>
                            <span>{{ booking.notes }}</span>
                          </div>
                        }
                        <div>
                          <span style="color:var(--text-muted);display:block;">Created</span>
                          <span>{{ booking.created_at | date:'medium' }}</span>
                        </div>
                        @if (booking.payment) {
                          <div>
                            <span style="color:var(--text-muted);display:block;">Payment Status</span>
                            <span class="badge" [class]="'badge badge-' + booking.payment.status">
                              {{ booking.payment.status }}
                            </span>
                          </div>
                          <div>
                            <span style="color:var(--text-muted);display:block;">Payment Amount</span>
                            <strong>\${{ booking.payment.amount }}</strong>
                          </div>
                          @if (booking.payment.paid_at) {
                            <div>
                              <span style="color:var(--text-muted);display:block;">Paid At</span>
                              <span>{{ booking.payment.paid_at | date:'medium' }}</span>
                            </div>
                          }
                        }
                      </div>
                    </td>
                  </tr>
                }
              }
            </tbody>
          </table>
        </div>

        @if (lastPage() > 1) {
          <div class="pagination">
            <button [disabled]="currentPage() <= 1" (click)="loadPage(currentPage() - 1)">Prev</button>
            @for (p of pages(); track p) {
              <button [class.active]="p === currentPage()" (click)="loadPage(p)">{{ p }}</button>
            }
            <button [disabled]="currentPage() >= lastPage()" (click)="loadPage(currentPage() + 1)">Next</button>
          </div>
        }
      </div>
    }
  `,
})
export class BookingListComponent implements OnInit {
  private readonly api = environment.apiUrl;

  bookings = signal<Booking[]>([]);
  loading = signal(true);
  currentPage = signal(1);
  lastPage = signal(1);
  pages = signal<number[]>([]);
  expandedId = signal<number | null>(null);

  constructor(
    private http: HttpClient,
    private notify: NotificationService,
  ) {}

  ngOnInit(): void {
    this.loadPage(1);
  }

  loadPage(_page?: number): void {
    this.loading.set(true);
    this.http.get<Booking[]>(`${this.api}/client/bookings`).subscribe({
      next: (res) => {
        this.bookings.set(res);
        this.loading.set(false);
      },
      error: (err) => {
        this.notify.error(err.error?.message || 'Failed to load bookings.');
        this.loading.set(false);
      },
    });
  }

  toggleDetail(id: number): void {
    this.expandedId.set(this.expandedId() === id ? null : id);
  }
}
