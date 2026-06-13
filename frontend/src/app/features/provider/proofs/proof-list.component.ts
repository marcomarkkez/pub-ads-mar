import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';
import { Proof } from '../../../core/models';

@Component({
  selector: 'app-proof-list',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  template: `
    <div class="page-header">
      <h1>Proofs of Display</h1>
      <button class="btn" (click)="showUploadForm.set(!showUploadForm())">
        {{ showUploadForm() ? 'Cancel' : '+ Upload Proof' }}
      </button>
    </div>

    <!-- Upload Form -->
    @if (showUploadForm()) {
      <div class="card upload-card">
        <div class="card-header"><h2>Upload New Proof</h2></div>

        @if (uploadError()) {
          <div class="alert alert-error">{{ uploadError() }}</div>
        }

        <form (ngSubmit)="onUpload()">
          <div class="form-group">
            <label for="booking_id">Booking ID *</label>
            <input id="booking_id" name="booking_id" type="number" min="1"
                   [(ngModel)]="uploadForm.booking_id" required placeholder="Enter booking ID">
          </div>

          <div class="form-group">
            <label for="proof_file">Proof File *</label>
            <input id="proof_file" type="file" accept="image/*,video/*"
                   (change)="onFileSelected($event)" class="file-input">
            @if (selectedFileName()) {
              <span class="file-name">{{ selectedFileName() }}</span>
            }
            <span class="hint">Radio/audio ads must be proven with a short video of the ad being aired.</span>
          </div>

          <div class="form-group">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" [(ngModel)]="uploadForm.notes"
                      placeholder="Optional notes about this proof..."></textarea>
          </div>

          <div class="form-actions">
            <button type="button" class="btn" (click)="showUploadForm.set(false)">Cancel</button>
            <button type="submit" class="btn btn-primary" style="width:auto"
                    [disabled]="uploading() || !uploadForm.booking_id || !selectedFile()">
              @if (uploading()) {
                <span class="spinner"></span> Uploading...
              } @else {
                Upload Proof
              }
            </button>
          </div>
        </form>
      </div>
    }

    <!-- Proofs Table -->
    @if (loading()) {
      <div class="loading-container"><span class="spinner"></span></div>
    } @else if (error()) {
      <div class="alert alert-error">{{ error() }}</div>
    } @else if (proofs().length === 0) {
      <div class="empty-state">
        <div class="empty-icon">&#x1F4F8;</div>
        <p>No proofs uploaded yet. Upload proof of display for your bookings.</p>
      </div>
    } @else {
      <div class="card">
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Booking</th>
                <th>File</th>
                <th>Status</th>
                <th>Notes</th>
                <th>Uploaded At</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @for (proof of proofs(); track proof.id) {
                <tr>
                  <td>Booking #{{ proof.booking_id }}</td>
                  <td>
                    <a [href]="proof.file_url" target="_blank" class="link">{{ proof.file_name }}</a>
                  </td>
                  <td>
                    <span class="badge" [class]="'badge-' + proof.status">
                      {{ proof.status | titlecase }}
                    </span>
                  </td>
                  <td class="notes-cell">{{ proof.notes || '---' }}</td>
                  <td>{{ proof.created_at | date:'mediumDate' }}</td>
                  <td>
                    <a [href]="proof.file_url" target="_blank" class="btn btn-sm">View</a>
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
    .upload-card {
      margin-bottom: 24px;
    }
    .link {
      color: var(--primary);
      text-decoration: none;
      font-weight: 600;
      &:hover { text-decoration: underline; }
    }
    .file-input {
      padding: 8px 0;
      border: none !important;
      box-shadow: none !important;
    }
    .file-name {
      display: block;
      font-size: 12px;
      color: var(--text-muted);
      margin-top: 4px;
    }
    .hint {
      display: block;
      font-size: 12px;
      color: var(--text-muted);
      margin-top: 6px;
      font-style: italic;
    }
    .notes-cell {
      max-width: 200px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .form-actions {
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      margin-top: 20px;
      padding-top: 16px;
      border-top: 1px solid var(--border);
    }
  `],
})
export class ProofListComponent implements OnInit {
  proofs = signal<Proof[]>([]);
  loading = signal(false);
  error = signal('');
  showUploadForm = signal(false);
  uploading = signal(false);
  uploadError = signal('');
  selectedFile = signal<File | null>(null);
  selectedFileName = signal('');

  uploadForm = {
    booking_id: null as number | null,
    notes: '',
  };

  private readonly api = environment.apiUrl;

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

    this.http.get<Proof[]>(`${this.api}/provider/proofs`).subscribe({
      next: (res) => {
        this.proofs.set(res);
        this.loading.set(false);
      },
      error: (err) => {
        this.error.set(err.error?.message || 'Failed to load proofs.');
        this.loading.set(false);
      },
    });
  }

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (input.files?.length) {
      this.selectedFile.set(input.files[0]);
      this.selectedFileName.set(input.files[0].name);
    }
  }

  onUpload(): void {
    const file = this.selectedFile();
    if (!file || !this.uploadForm.booking_id) return;

    this.uploading.set(true);
    this.uploadError.set('');

    const formData = new FormData();
    formData.append('file', file);
    formData.append('booking_id', this.uploadForm.booking_id.toString());
    if (this.uploadForm.notes) {
      formData.append('notes', this.uploadForm.notes);
    }

    this.http.post<Proof>(`${this.api}/provider/proofs`, formData).subscribe({
      next: (res) => {
        this.proofs.update(list => [res, ...list]);
        this.notify.success('Proof uploaded successfully.');
        this.resetUploadForm();
        this.uploading.set(false);
        this.showUploadForm.set(false);
      },
      error: (err) => {
        this.uploadError.set(err.error?.message || 'Failed to upload proof.');
        this.uploading.set(false);
      },
    });
  }

  private resetUploadForm(): void {
    this.uploadForm = { booking_id: null, notes: '' };
    this.selectedFile.set(null);
    this.selectedFileName.set('');
    this.uploadError.set('');
  }
}
