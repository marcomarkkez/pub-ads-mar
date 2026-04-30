import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { NotificationService } from '../../core/services/notification.service';
import { Payment } from '../../core/models';

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
                    <span class="badge" [ngClass]="'badge-' + payment.status">
                      {{ payment.status }}
                    </span>
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
                        <span class="text-muted">--</span>
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
    .amount {
      color: var(--text);
      font-size: 14px;
    }
    .transaction-id {
      font-size: 12px;
      font-family: monospace;
      color: var(--text-muted);
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

    this.http.get<PaymentWithBooking[]>(`${this.api}/payments/payments`).subscribe({
      next: (res) => {
        this.payments.set(res);
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

    this.http.post<{ data: PaymentWithBooking }>(
      `${this.api}/payments/payments/${payment.id}/approve`,
      {}
    ).subscribe({
      next: (res) => {
        this.payments.update(list =>
          list.map(p => p.id === payment.id ? { ...p, ...res.data } : p)
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

    this.http.post<{ data: PaymentWithBooking }>(
      `${this.api}/payments/payments/${payment.id}/reject`,
      {}
    ).subscribe({
      next: (res) => {
        this.payments.update(list =>
          list.map(p => p.id === payment.id ? { ...p, ...res.data } : p)
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
}
