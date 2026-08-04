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
                <!-- No "Paid At": the invoices table has no such column, so the cell
                     rendered "-" forever. The status "paid" carries that fact. -->
                <th>Issued At</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              @for (invoice of invoices(); track invoice.id) {
                <tr>
                  <td><strong>{{ invoice.invoice_number }}</strong></td>
                  <td>{{ invoice.campaign?.name || 'Campaign #' + invoice.campaign_id }}</td>
                  <td><strong>\${{ invoice.total_amount }}</strong></td>
                  <td>
                    <span class="badge" [class]="'badge badge-' + invoice.status">{{ invoice.status }}</span>
                  </td>
                  <td>{{ invoice.issued_at ? (invoice.issued_at | date:'mediumDate') : '-' }}</td>
                  <td>
                    <button class="btn btn-sm" [disabled]="downloadingId() === invoice.id"
                      (click)="downloadPdf(invoice)" title="Download PDF">
                      @if (downloadingId() === invoice.id) {
                        <span class="spinner"></span>
                      } @else {
                        ⬇ PDF
                      }
                    </button>
                  </td>
                </tr>
              }
            </tbody>
          </table>
        </div>
      </div>
    }
  `,
})
export class InvoiceListComponent implements OnInit {
  private readonly api = environment.apiUrl;

  invoices = signal<Invoice[]>([]);
  loading = signal(true);
  downloadingId = signal<number | null>(null);

  constructor(
    private http: HttpClient,
    private notify: NotificationService,
  ) {}

  ngOnInit(): void {
    this.load();
  }

  // GET /client/invoices returns a PLAIN ARRAY (InvoiceController::index -> ->get()).
  // The Prev/Next controls and the page signals that used to live here could never
  // render, because `lastPage` was pinned at 1 forever: paging UI for an endpoint that
  // does not paginate. If invoices ever paginate, the endpoint changes first.
  load(): void {
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

  downloadPdf(invoice: Invoice): void {
    this.downloadingId.set(invoice.id);
    // Bearer token is added by the auth interceptor; fetch the PDF as a blob.
    this.http.get(`${this.api}/client/invoices/${invoice.id}/pdf`, { responseType: 'blob' }).subscribe({
      next: (blob) => {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `invoice-${invoice.invoice_number}.pdf`;
        a.click();
        URL.revokeObjectURL(url);
        this.downloadingId.set(null);
      },
      error: () => {
        this.notify.error('Failed to download the invoice PDF.');
        this.downloadingId.set(null);
      },
    });
  }
}
