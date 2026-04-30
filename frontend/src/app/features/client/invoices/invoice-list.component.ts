import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';
import { Invoice } from '../../../core/models';

@Component({
  selector: 'app-invoice-list',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="page-header">
      <h1>Invoices</h1>
    </div>

    @if (loading()) {
      <div class="loading-container"><span class="spinner"></span></div>
    } @else if (invoices().length === 0) {
      <div class="empty-state">
        <div class="empty-icon">&#128196;</div>
        <p>No invoices yet.</p>
      </div>
    } @else {
      <div class="card">
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Invoice #</th>
                <th>Campaign</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Issued At</th>
                <th>Paid At</th>
              </tr>
            </thead>
            <tbody>
              @for (invoice of invoices(); track invoice.id) {
                <tr>
                  <td><strong>{{ invoice.invoice_number }}</strong></td>
                  <td>{{ invoice.campaign?.name || 'Campaign #' + invoice.campaign_id }}</td>
                  <td><strong>\${{ invoice.amount }}</strong></td>
                  <td>
                    <span class="badge" [class]="'badge badge-' + invoice.status">{{ invoice.status }}</span>
                  </td>
                  <td>{{ invoice.issued_at ? (invoice.issued_at | date:'mediumDate') : '-' }}</td>
                  <td>{{ invoice.paid_at ? (invoice.paid_at | date:'mediumDate') : '-' }}</td>
                </tr>
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
export class InvoiceListComponent implements OnInit {
  private readonly api = environment.apiUrl;

  invoices = signal<Invoice[]>([]);
  loading = signal(true);
  currentPage = signal(1);
  lastPage = signal(1);
  pages = signal<number[]>([]);

  constructor(
    private http: HttpClient,
    private notify: NotificationService,
  ) {}

  ngOnInit(): void {
    this.loadPage(1);
  }

  loadPage(_page?: number): void {
    this.loading.set(true);
    this.http.get<Invoice[]>(`${this.api}/client/invoices`).subscribe({
      next: (res) => {
        this.invoices.set(res);
        this.loading.set(false);
      },
      error: (err) => {
        this.notify.error(err.error?.message || 'Failed to load invoices.');
        this.loading.set(false);
      },
    });
  }
}
