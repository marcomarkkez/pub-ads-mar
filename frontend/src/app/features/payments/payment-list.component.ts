import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { NotificationService } from '../../core/services/notification.service';
import { Payment, paymentStatusLabel } from '../../core/models';

interface PaymentWithBooking extends Payment {
  booking?: {
    id: number;
    start_date: string;
    end_date: string;
    space?: { id: number; name: string };
    client?: { id: number; name: string };
  };
}

@Component({
  selector: 'app-payment-list',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="page-header">
      <h1>Payments</h1>
    </div>

    @if (loading()) {
      <div class="loading-container">
        <span class="spinner"></span>
      </div>
    }

    @if (error()) {
      <div class="alert alert-error">{{ error() }}</div>
    }

    @if (!loading() && payments().length === 0 && !error()) {
      <div class="empty-state">
        <div class="empty-icon">💳</div>
        <p>No payments to review.</p>
      </div>
    }

    @if (!loading() && payments().length > 0) {
      <div class="card">
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Booking ID</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Payment Method</th>
                <th>Transaction ID</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @for (payment of payments(); track payment.id) {
                <tr>
                  <td>
                    <strong>#{{ payment.booking_id }}</strong>
                    @if (payment.booking?.space) {
                      <span class="booking-info">{{ payment.booking!.space!.name }}</span>
                    }
                  </td>
                  <td>
                    <strong class="amount">{{ payment.amount | currency:'USD' }}</strong>
                  </td>
                  <td>
                    <!-- §8: free_payment = ready to pay, held = frozen in a dispute.
                         Distinct badge styles + labels; they must never read alike. -->
                    <span class="badge" [ngClass]="'badge-' + payment.status">
                      {{ statusLabel(payment.status) }}
                    </span>
                    <!-- design.json §10 [F09] -> §8 [F10]: show WHY money is held —
                         active flags on the dispute chats attached to this payment. -->
                    @for (t of flagNotes(payment); track t.id) {
                      <div class="flag-note" [title]="t.description">&#9873; {{ t.subject }}</div>
                    }
                  </td>
                  <td>{{ payment.payment_method || 'N/A' }}</td>
                  <td>
                    <span class="transaction-id">{{ payment.transaction_id || 'N/A' }}</span>
                  </td>
                  <td>
                    <div class="action-btns">
                      @if (payment.status === 'pending') {
                        <button
                          class="btn btn-sm btn-success"
                          [disabled]="actionLoading()"
                          (click)="approvePayment(payment)"
                        >
                          Approve
                        </button>
                        <button
                          class="btn btn-sm btn-danger"
                          [disabled]="actionLoading()"
                          (click)="rejectPayment(payment)"
                        >
                          Reject
                        </button>
                      } @else {
                        <!--
                          The buttons follow the SAME state machine the API enforces (§8).
                          They used to be one single else-branch, so a released payment offered
                          "Release payout" again and a payment frozen in a dispute offered
                          it too — the backend now answers 409, but a button that exists
                          only to be refused still reads as permission.
                        -->
                        @if (payment.status === 'free_payment' || payment.status === 'completed') {
                          <button
                            class="btn btn-sm btn-secondary"
                            [disabled]="actionLoading()"
                            (click)="refundPayment(payment)"
                          >
                            Refund
                          </button>
                        }
                        @if (payment.status === 'free_payment') {
                          <button
                            class="btn btn-sm btn-success"
                            [disabled]="actionLoading()"
                            (click)="releasePayout(payment)"
                          >
                            Release payout
                          </button>
                        }
                        @if (payment.status === 'free_payment' || payment.status === 'completed') {
                          <button
                            class="btn btn-sm btn-warning"
                            [disabled]="actionLoading()"
                            (click)="holdPayout(payment)"
                          >
                            Hold payout
                          </button>
                        }
                        @if (payment.status === 'held') {
                          <span class="action-note" title="Resolve the dispute in Messages first">
                            In dispute — resolve to settle
                          </span>
                        }
                      }
                    </div>
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
    .booking-info {
      display: block;
      font-size: 11px;
      color: var(--text-muted);
    }
    .flag-note {
      margin-top: 4px;
      font-size: 11px;
      color: var(--danger, #b91c1c);
      max-width: 220px;
      cursor: help;
    }
    .amount {
      color: var(--text);
      font-size: 14px;
    }
    .transaction-id {
      font-size: 12px;
      font-family: monospace;
      color: var(--text-muted);
    }
    /* Shown where the buttons used to be for a disputed payout: the row still needs to
       say WHY there is nothing to press, or it reads as a rendering bug. */
    .action-note {
      font-size: 11px;
      font-style: italic;
      color: var(--text-muted);
      white-space: nowrap;
      cursor: help;
    }
    .action-btns {
      display: flex;
      gap: 6px;
    }
    .text-muted {
      color: var(--text-muted);
      font-size: 13px;
    }
  `],
})
export class PaymentListComponent implements OnInit {
  private readonly api = environment.apiUrl;

  readonly statusLabel = paymentStatusLabel;

  payments = signal<PaymentWithBooking[]>([]);
  loading = signal(false);
  error = signal('');
  actionLoading = signal(false);

  constructor(
    private http: HttpClient,
    private notify: NotificationService,
  ) {}

  ngOnInit(): void {
    this.loadPayments();
  }

  loadPayments(): void {
    this.loading.set(true);
    this.error.set('');

    // Backend paginates → { data: [...] }. Read res.data (mirrors proof-review-list).
    this.http.get<{ data: PaymentWithBooking[] }>(`${this.api}/payments/payments`).subscribe({
      next: (res) => {
        this.payments.set(res.data ?? []);
        this.loading.set(false);
      },
      error: (err) => {
        this.loading.set(false);
        this.error.set(err.error?.message || 'Failed to load payments.');
        this.notify.error('Failed to load payments.');
      },
    });
  }

  approvePayment(payment: PaymentWithBooking): void {
    if (!confirm(`Approve payment of ${payment.amount} for booking #${payment.booking_id}?`)) return;

    this.actionLoading.set(true);

    this.http.post<PaymentWithBooking>(
      `${this.api}/payments/payments/${payment.id}/approve`,
      {}
    ).subscribe({
      next: (res) => {
        this.payments.update(list =>
          list.map(p => p.id === payment.id ? { ...p, ...res } : p)
        );
        this.actionLoading.set(false);
        this.notify.success('Payment approved.');
      },
      error: (err) => {
        this.actionLoading.set(false);
        this.notify.error(err.error?.message || 'Failed to approve payment.');
      },
    });
  }

  rejectPayment(payment: PaymentWithBooking): void {
    if (!confirm(`Reject payment of ${payment.amount} for booking #${payment.booking_id}?`)) return;

    this.actionLoading.set(true);

    this.http.post<PaymentWithBooking>(
      `${this.api}/payments/payments/${payment.id}/reject`,
      {}
    ).subscribe({
      next: (res) => {
        this.payments.update(list =>
          list.map(p => p.id === payment.id ? { ...p, ...res } : p)
        );
        this.actionLoading.set(false);
        this.notify.success('Payment rejected.');
      },
      error: (err) => {
        this.actionLoading.set(false);
        this.notify.error(err.error?.message || 'Failed to reject payment.');
      },
    });
  }

  // design.json §10 [F09] -> §8 [F10]: WHY the money is held. Disputes are now the ONE chat
  // primitive; each dispute chat carries an active flag (payment_held/refund/…) attached to
  // this payment. Derive flag notes from the payment's related chats' active flags.
  flagNotes(payment: PaymentWithBooking): { id: number; subject: string; description?: string }[] {
    const chats = (payment as { chats?: { flags?: { id: number; type: string; reason?: string | null; active?: boolean }[] }[] }).chats ?? [];
    const notes: { id: number; subject: string; description?: string }[] = [];
    for (const c of chats) {
      for (const f of c.flags ?? []) {
        if (f.active === false) continue;
        notes.push({ id: f.id, subject: f.type, description: f.reason ?? undefined });
      }
    }
    return notes;
  }

  refundPayment(payment: PaymentWithBooking): void {
    if (!confirm(`Refund payment of ${payment.amount} for booking #${payment.booking_id}?`)) return;
    this.payoutAction(payment, `payments/${payment.id}/refund`, 'Payment refunded.', 'Failed to refund payment.');
  }

  releasePayout(payment: PaymentWithBooking): void {
    if (!confirm(`Release payout for booking #${payment.booking_id}?`)) return;
    this.payoutAction(payment, `payments/${payment.id}/payout/release`, 'Payout released.', 'Failed to release payout.');
  }

  holdPayout(payment: PaymentWithBooking): void {
    if (!confirm(`Hold payout for booking #${payment.booking_id}?`)) return;
    this.payoutAction(payment, `payments/${payment.id}/payout/hold`, 'Payout held.', 'Failed to hold payout.');
  }

  private payoutAction(payment: PaymentWithBooking, path: string, ok: string, fail: string): void {
    this.actionLoading.set(true);

    this.http.post<PaymentWithBooking>(`${this.api}/payments/${path}`, {}).subscribe({
      next: (res) => {
        this.payments.update(list =>
          list.map(p => p.id === payment.id ? { ...p, ...res } : p)
        );
        this.actionLoading.set(false);
        this.notify.success(`${ok} Status: ${res.status}`);
      },
      error: (err) => {
        this.actionLoading.set(false);
        this.notify.error(err.error?.message || fail);
      },
    });
  }
}
