import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { NotificationService } from '../../core/services/notification.service';
import { Proof } from '../../core/models';

interface ProofWithBooking extends Proof {
  booking?: {
    id: number;
    start_date: string;
    end_date: string;
    space?: { id: number; name: string };
    client?: { id: number; name: string };
  };
  uploader?: { id: number; name: string };
}

@Component({
  selector: 'app-proof-review-list',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="page-header">
      <h1>Proof Review</h1>
    </div>

    @if (loading()) {
      <div class="loading-container">
        <span class="spinner"></span>
      </div>
    }

    @if (error()) {
      <div class="alert alert-error">{{ error() }}</div>
    }

    @if (!loading() && proofs().length === 0 && !error()) {
      <div class="empty-state">
        <div class="empty-icon">📸</div>
        <p>No proofs to review.</p>
      </div>
    }

    @if (!loading() && proofs().length > 0) {
      <div class="proof-grid">
        @for (proof of proofs(); track proof.id) {
          <div class="card proof-card">
            <div class="proof-header">
              <div>
                <strong>Booking #{{ proof.booking_id }}</strong>
                @if (proof.booking?.space) {
                  <span class="proof-space">{{ proof.booking!.space!.name }}</span>
                }
              </div>
              <span class="badge" [ngClass]="'badge-' + proof.status">
                {{ proof.status }}
              </span>
            </div>

            <div class="proof-file">
              @if (proof.file_url) {
                @if (isImage(proof.file_name)) {
                  <img [src]="proof.file_url" [alt]="proof.file_name" class="proof-image" />
                } @else {
                  <a [href]="proof.file_url" target="_blank" class="file-link">
                    📎 {{ proof.file_name }}
                  </a>
                }
              } @else {
                <span class="text-muted">No file</span>
              }
            </div>

            @if (proof.notes) {
              <div class="proof-notes">
                <span class="notes-label">Notes:</span> {{ proof.notes }}
              </div>
            }

            <div class="proof-meta">
              @if (proof.uploader) {
                <span>Uploaded by: {{ proof.uploader.name }}</span>
              }
              <span>{{ proof.created_at | date:'short' }}</span>
            </div>

            @if (proof.status === 'pending_review') {
              <div class="proof-actions">
                <button
                  class="btn btn-sm btn-success"
                  [disabled]="actionLoading()"
                  (click)="approveProof(proof)"
                >
                  Approve
                </button>
                <button
                  class="btn btn-sm btn-danger"
                  [disabled]="actionLoading()"
                  (click)="rejectProof(proof)"
                >
                  Reject
                </button>
              </div>
            }
          </div>
        }
      </div>
    }
  `,
  styles: [`
    .proof-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
      gap: 16px;
    }
    .proof-card {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .proof-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
    }
    .proof-space {
      display: block;
      font-size: 12px;
      color: var(--text-muted);
    }
    .proof-file {
      background: var(--hover);
      border-radius: 6px;
      padding: 12px;
      text-align: center;
      min-height: 60px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .proof-image {
      max-width: 100%;
      max-height: 200px;
      border-radius: 4px;
      object-fit: contain;
    }
    .file-link {
      color: var(--primary);
      text-decoration: none;
      font-weight: 600;
      &:hover { text-decoration: underline; }
    }
    .proof-notes {
      font-size: 13px;
      color: var(--text-muted);
      line-height: 1.4;
    }
    .notes-label {
      font-weight: 600;
      color: var(--text);
    }
    .proof-meta {
      display: flex;
      justify-content: space-between;
      font-size: 11px;
      color: var(--text-muted);
    }
    .proof-actions {
      display: flex;
      gap: 8px;
      padding-top: 8px;
      border-top: 1px solid var(--border);
    }
    .text-muted {
      color: var(--text-muted);
    }
  `],
})
export class ProofReviewListComponent implements OnInit {
  private readonly api = environment.apiUrl;

  proofs = signal<ProofWithBooking[]>([]);
  loading = signal(false);
  error = signal('');
  actionLoading = signal(false);

  constructor(
    private http: HttpClient,
    private notify: NotificationService,
  ) {}

  ngOnInit(): void {
    this.loadProofs();
  }

  loadProofs(): void {
    this.loading.set(true);
    this.error.set('');

    this.http.get<{ data: ProofWithBooking[] }>(`${this.api}/payments/proofs`).subscribe({
      next: (res) => {
        this.proofs.set(res.data ?? []);
        this.loading.set(false);
      },
      error: (err) => {
        this.loading.set(false);
        this.error.set(err.error?.message || 'Failed to load proofs.');
        this.notify.error('Failed to load proofs.');
      },
    });
  }

  approveProof(proof: ProofWithBooking): void {
    if (!confirm(`Approve proof for booking #${proof.booking_id}?`)) return;

    this.actionLoading.set(true);

    this.http.post<ProofWithBooking>(
      `${this.api}/payments/proofs/${proof.id}/approve`,
      {}
    ).subscribe({
      next: (res) => {
        this.proofs.update(list =>
          list.map(p => p.id === proof.id ? { ...p, ...res } : p)
        );
        this.actionLoading.set(false);
        this.notify.success('Proof approved.');
      },
      error: (err) => {
        this.actionLoading.set(false);
        this.notify.error(err.error?.message || 'Failed to approve proof.');
      },
    });
  }

  rejectProof(proof: ProofWithBooking): void {
    if (!confirm(`Reject proof for booking #${proof.booking_id}?`)) return;

    this.actionLoading.set(true);

    this.http.post<ProofWithBooking>(
      `${this.api}/payments/proofs/${proof.id}/reject`,
      {}
    ).subscribe({
      next: (res) => {
        this.proofs.update(list =>
          list.map(p => p.id === proof.id ? { ...p, ...res } : p)
        );
        this.actionLoading.set(false);
        this.notify.success('Proof rejected.');
      },
      error: (err) => {
        this.actionLoading.set(false);
        this.notify.error(err.error?.message || 'Failed to reject proof.');
      },
    });
  }

  isImage(fileName: string): boolean {
    if (!fileName) return false;
    const ext = fileName.toLowerCase().split('.').pop() || '';
    return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(ext);
  }
}
