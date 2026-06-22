import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink, ActivatedRoute } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';
import { Space, SpacePhoto } from '../../../core/models';

@Component({
  selector: 'app-space-detail',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  template: `
    @if (loading()) {
      <div class="loading-container"><span class="spinner"></span></div>
    } @else if (error()) {
      <div class="alert alert-error">{{ error() }}</div>
    } @else if (space()) {
      <div class="page-header">
        <h1>{{ space()!.name }}</h1>
        <div class="header-actions">
          <a [routerLink]="['/provider/spaces', space()!.id, 'edit']" class="btn">Edit</a>
          <a routerLink="/tickets/new"
            [queryParams]="{ ref_type: 'space', ref_id: space()!.id, subject: 'Issue with space: ' + space()!.name }"
            class="btn">🎫 Support</a>
          <a routerLink="/provider/spaces" class="btn">Back to Spaces</a>
        </div>
      </div>

      <!-- Space Details Card -->
      <div class="card detail-card">
        <div class="card-header"><h2>Space Details</h2></div>
        <div class="detail-grid">
          <div class="detail-item">
            <span class="detail-label">Type</span>
            <span class="badge" [class]="'badge type-' + space()!.type">{{ formatType(space()!.type) }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Location</span>
            <span>{{ space()!.location_name || 'N/A' }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Coordinates</span>
            <span>{{ space()!.latitude }}, {{ space()!.longitude }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Price per Day</span>
            <span>{{ space()!.price_per_day | currency:'EUR' }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Dimensions</span>
            <span>{{ space()!.width ? (space()!.width + 'm x ' + space()!.height + 'm') : 'N/A' }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Status</span>
            <span class="badge" [class.badge-active]="space()!.is_active" [class.badge-cancelled]="!space()!.is_active">
              {{ space()!.is_active ? 'Active' : 'Inactive' }}
            </span>
          </div>
          @if (space()!.description) {
            <div class="detail-item full-width">
              <span class="detail-label">Description</span>
              <span>{{ space()!.description }}</span>
            </div>
          }
        </div>
      </div>

      <!-- Photos Section -->
      <div class="card section-card">
        <div class="card-header">
          <h2>Photos ({{ photos().length }})</h2>
          <label class="btn btn-sm" [class.disabled]="uploadingPhoto()">
            @if (uploadingPhoto()) {
              <span class="spinner"></span> Uploading...
            } @else {
              + Upload Photo
            }
            <input type="file" accept="image/*" (change)="onPhotoSelected($event)" hidden [disabled]="uploadingPhoto()">
          </label>
        </div>

        @if (photos().length === 0) {
          <p class="text-muted">No photos uploaded yet.</p>
        } @else {
          <div class="photo-grid">
            @for (photo of photos(); track photo.id) {
              <div class="photo-card">
                <img [src]="photo.file_url" [alt]="photo.file_name" loading="lazy">
                @if (photo.is_primary) {
                  <span class="badge badge-active photo-badge">Primary</span>
                }
                <button class="btn btn-sm btn-danger photo-delete" (click)="deletePhoto(photo)" [disabled]="deletingPhotoId() === photo.id">
                  @if (deletingPhotoId() === photo.id) {
                    <span class="spinner"></span>
                  } @else {
                    Delete
                  }
                </button>
              </div>
            }
          </div>
        }
      </div>

      <!-- [todo B2] Availability + Calendar moved to the Edit screen (owner decision 2026-06-20). -->
      <div class="card section-card">
        <div class="card-header"><h2>Availability & Calendar</h2></div>
        <p class="text-muted" style="margin-bottom:12px;">
          Availability date ranges and Google/iCal calendar sync are managed on the Edit screen.
        </p>
        <a [routerLink]="['/provider/spaces', space()!.id, 'edit']" class="btn btn-sm btn-primary" style="width:auto">
          Manage availability & calendar
        </a>
      </div>
    }
  `,
  styles: [`
    .header-actions {
      display: flex;
      gap: 8px;
    }
    .detail-card {
      margin-bottom: 24px;
    }
    .detail-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }
    .detail-item {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .detail-item.full-width {
      grid-column: 1 / -1;
    }
    .detail-label {
      font-size: 12px;
      font-weight: 600;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }
    .text-muted {
      color: var(--text-muted);
      font-size: 14px;
    }
    .section-card {
      margin-bottom: 24px;
    }

    /* Type badges */
    .type-billboard { background: #dbeafe; color: #1e40af; }
    .type-big_screen { background: #ede9fe; color: #5b21b6; }
    .type-little_screen { background: #fce7f3; color: #9d174d; }
    .type-radio_station { background: #fef3c7; color: #92400e; }
    .type-other { background: #f3f4f6; color: #374151; }

    /* Photos */
    .photo-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      gap: 16px;
    }
    .photo-card {
      position: relative;
      border: 1px solid var(--border);
      border-radius: 8px;
      overflow: hidden;

      img {
        width: 100%;
        height: 140px;
        object-fit: cover;
        display: block;
      }
    }
    .photo-badge {
      position: absolute;
      top: 8px;
      left: 8px;
    }
    .photo-delete {
      width: 100%;
      border-radius: 0;
      border: none;
      border-top: 1px solid var(--border);
    }

    /* Inline availability form */
    .inline-form {
      display: flex;
      align-items: flex-end;
      gap: 12px;
      flex-wrap: wrap;
      padding-top: 8px;

      .form-group {
        margin-bottom: 0;
        flex: 1;
        min-width: 140px;
      }
      button {
        margin-bottom: 0;
        white-space: nowrap;
      }
    }

    .disabled {
      opacity: 0.5;
      pointer-events: none;
    }

    .help-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 16px;
      height: 16px;
      margin-left: 6px;
      padding: 0;
      font-size: 11px;
      line-height: 1;
      border-radius: 50%;
      border: 1px solid var(--border);
      background: var(--bg-muted, #f3f4f6);
      color: var(--text-muted);
      cursor: pointer;
    }
    .help-text {
      margin-top: 6px;
      font-size: 12px;
      color: var(--text-muted);
      line-height: 1.5;
    }
  `],
})
export class SpaceDetailComponent implements OnInit {
  space = signal<Space | null>(null);
  photos = signal<SpacePhoto[]>([]);
  loading = signal(false);
  error = signal('');
  uploadingPhoto = signal(false);
  deletingPhotoId = signal<number | null>(null);

  private spaceId!: number;
  private readonly api = environment.apiUrl;

  constructor(
    private http: HttpClient,
    private route: ActivatedRoute,
    private notify: NotificationService,
  ) {}

  ngOnInit(): void {
    this.spaceId = +this.route.snapshot.paramMap.get('id')!;
    this.loadSpace();
  }

  private loadSpace(): void {
    this.loading.set(true);
    this.error.set('');

    this.http.get<Space>(`${this.api}/provider/spaces/${this.spaceId}`).subscribe({
      next: (res) => {
        this.space.set(res);
        this.photos.set((res as any).photos || []);
        this.loading.set(false);
      },
      error: (err) => {
        this.error.set(err.error?.message || 'Failed to load space.');
        this.loading.set(false);
      },
    });
  }

  // --- Photos ---

  onPhotoSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (!input.files?.length) return;

    const file = input.files[0];
    const formData = new FormData();
    formData.append('photo', file);

    this.uploadingPhoto.set(true);

    this.http.post<SpacePhoto>(`${this.api}/provider/spaces/${this.spaceId}/photos`, formData).subscribe({
      next: (res) => {
        this.photos.update(list => [...list, res]);
        this.notify.success('Photo uploaded.');
        this.uploadingPhoto.set(false);
        input.value = '';
      },
      error: (err) => {
        this.notify.error(err.error?.message || 'Failed to upload photo.');
        this.uploadingPhoto.set(false);
        input.value = '';
      },
    });
  }

  deletePhoto(photo: SpacePhoto): void {
    if (!confirm('Delete this photo?')) return;

    this.deletingPhotoId.set(photo.id);

    this.http.delete(`${this.api}/provider/spaces/${this.spaceId}/photos/${photo.id}`).subscribe({
      next: () => {
        this.photos.update(list => list.filter(p => p.id !== photo.id));
        this.notify.success('Photo deleted.');
        this.deletingPhotoId.set(null);
      },
      error: (err) => {
        this.notify.error(err.error?.message || 'Failed to delete photo.');
        this.deletingPhotoId.set(null);
      },
    });
  }

  // [todo B2] Availability + calendar management moved to the Edit screen (space-form).

  formatType(type: string): string {
    return type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
  }
}
