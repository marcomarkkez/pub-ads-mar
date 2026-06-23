import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';

interface WalletEntry {
  id: number;
  amount: number;
  type: string;
  ref_type: string | null;
  ref_id: number | null;
  created_at: string;
}

interface WalletResponse {
  balance: number;
  entries: WalletEntry[];
}

@Component({
  selector: 'app-wallet',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="page-header">
      <h1>Wallet</h1>
    </div>

    @if (loading()) {
      <div class="loading-container"><span class="spinner"></span></div>
    } @else {
      <div class="card">
        <div class="card-header"><h2>Balance</h2></div>
        <p style="font-size:28px;font-weight:700;">{{ balance() | currency:'MXN' }}</p>
      </div>

      <div class="card section-card">
        <div class="card-header"><h2>Ledger</h2></div>
        @if (entries().length === 0) {
          <p class="text-muted">No wallet activity yet.</p>
        } @else {
          <div class="table-container">
            <table>
              <thead>
                <tr><th>Date</th><th>Type</th><th>Reference</th><th>Amount</th></tr>
              </thead>
              <tbody>
                @for (entry of entries(); track entry.id) {
                  <tr>
                    <td>{{ entry.created_at | date:'short' }}</td>
                    <td>{{ entry.type }}</td>
                    <td>{{ entry.ref_type ? (entry.ref_type + ' #' + entry.ref_id) : '—' }}</td>
                    <td [style.color]="entry.amount < 0 ? 'var(--danger)' : 'var(--success)'">
                      {{ entry.amount | currency:'MXN' }}
                    </td>
                  </tr>
                }
              </tbody>
            </table>
          </div>
        }
      </div>
    }
  `,
})
export class WalletComponent implements OnInit {
  balance = signal(0);
  entries = signal<WalletEntry[]>([]);
  loading = signal(false);

  private readonly api = environment.apiUrl;

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.loading.set(true);
    this.http.get<WalletResponse>(`${this.api}/client/wallet`).subscribe({
      next: (res) => {
        this.balance.set(res.balance ?? 0);
        this.entries.set(res.entries ?? []);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }
}
