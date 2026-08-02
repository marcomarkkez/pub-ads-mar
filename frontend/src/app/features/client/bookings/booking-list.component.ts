import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';
import { Booking, Proof } from '../../../core/models';

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

                      <!-- design.json §7 [F07]: the CLIENT reviews the provider's proof -->
                      @if (proofOf(booking); as proof) {
                        <div style="margin-top:16px;border-top:1px solid var(--border);padding-top:12px;">
                          <span style="color:var(--text-muted);display:block;font-size:12px;margin-bottom:8px;">Proof of display</span>
                          <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap;">
                            @if (proof.media_type === 'video') {
                              <video [src]="proof.file_url" controls style="max-width:240px;max-height:180px;border-radius:6px;background:#000;"></video>
                            } @else {
                              <img [src]="proof.file_url" [alt]="proof.file_name" style="max-width:240px;max-height:180px;border-radius:6px;object-fit:cover;" />
                            }
                            <div style="flex:1;min-width:220px;">
                              <span class="badge" [class]="'badge badge-' + proof.status">{{ proofLabel(proof.status) }}</span>
                              @if (proof.notes) {
                                <p style="font-size:12px;color:var(--text-muted);margin:8px 0 0;">{{ proof.notes }}</p>
                              }
                              @if (proof.status === 'pending_review') {
                                <p style="font-size:12px;margin:8px 0;">Review the provider's proof and accept it (payout becomes releasable) or reject it (payout is held and Support reviews the case).</p>
                                <div style="display:flex;gap:8px;">
                                  <button class="btn btn-sm btn-success" [disabled]="actionLoading()" (click)="acceptProof(proof)">Accept</button>
                                  <button class="btn btn-sm btn-danger" [disabled]="actionLoading()" (click)="rejectProof(proof)">Reject</button>
                                </div>
                              } @else if (proof.status === 'client_accepted') {
                                <p style="font-size:12px;color:var(--success,#2e7d32);margin:8px 0 0;">You accepted this proof — Payments can now release the payout.</p>
                              } @else if (proof.status === 'client_rejected') {
                                <p style="font-size:12px;color:var(--danger,#b91c1c);margin:8px 0 0;">You rejected this proof — the payout is held and Support is reviewing the case.</p>
                              }
                            </div>
                          </div>
                        </div>
                      }
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
  actionLoading = signal(false);

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

  /** The latest proof on a booking (backend loads `proofs`; fall back to `proof`). */
  proofOf(booking: Booking): Proof | null {
    const proofs = booking.proofs ?? (booking.proof ? [booking.proof] : []);
    if (!proofs.length) return null;
    return proofs.reduce((a, b) => (b.id > a.id ? b : a));
  }

  proofLabel(status: string): string {
    switch (status) {
      case 'pending_review': return 'Awaiting your review';
      case 'client_accepted': return 'Accepted';
      case 'client_rejected': return 'Rejected';
      default: return status;
    }
  }

  acceptProof(proof: Proof): void {
    if (!confirm('Accept this proof of display? The payout becomes releasable.')) return;
    this.proofAction(`proofs/${proof.id}/accept`, {}, 'Proof accepted.');
  }

  rejectProof(proof: Proof): void {
    if (!confirm('Reject this proof? The payout will be held and Support will review the case.')) return;
    const reason = prompt('Why are you rejecting this proof? (optional)') ?? '';
    this.proofAction(`proofs/${proof.id}/reject`, { reason }, 'Proof rejected — Support will review.');
  }

  private proofAction(path: string, body: Record<string, unknown>, ok: string): void {
    this.actionLoading.set(true);
    this.http.post(`${this.api}/client/${path}`, body).subscribe({
      next: () => {
        this.actionLoading.set(false);
        this.notify.success(ok);
        this.loadPage(this.currentPage());
      },
      error: (err) => {
        this.actionLoading.set(false);
        this.notify.error(err.error?.message || 'Action failed.');
      },
    });
  }
}
