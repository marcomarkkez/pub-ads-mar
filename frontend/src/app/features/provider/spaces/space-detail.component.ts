import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink, ActivatedRoute } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';
import { Space, SpacePhoto, SpaceAvailability } from '../../../core/models';

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

      <!-- Availabilities Section -->
      <div class="card section-card">
        <div class="card-header">
          <h2>Availabilities ({{ availabilities().length }})</h2>
        </div>

        <!-- Add Availability Form — multiple ranges -->
        <p class="text-muted" style="margin-bottom:8px;">Add one or more date ranges when this space is available for booking.</p>
        <div class="inline-form">
          <div class="form-group">
            <label for="avail-start">Start Date</label>
            <input id="avail-start" type="date" name="availStart" [(ngModel)]="availForm.start_date">
          </div>
          <div class="form-group">
            <label for="avail-end">End Date</label>
            <input id="avail-end" type="date" name="availEnd" [(ngModel)]="availForm.end_date">
          </div>
          <div class="form-group">
            <label for="avail-status">Status</label>
            <select id="avail-status" name="availStatus" [(ngModel)]="availForm.status">
              <option value="available">Available</option>
              <option value="blocked">Blocked</option>
            </select>
          </div>
          <button class="btn btn-sm btn-success" (click)="addAvailability()" [disabled]="addingAvail() || !availForm.start_date || !availForm.end_date">
            @if (addingAvail()) {
              <span class="spinner"></span>
            } @else {
              + Add Range
            }
          </button>
        </div>

        @if (availabilities().length === 0) {
          <p class="text-muted" style="margin-top:16px">No availabilities defined yet. Add date ranges above or sync from a calendar below.</p>
        } @else {
          <div class="table-container" style="margin-top:16px">
            <table>
              <thead>
                <tr>
                  <th>Start Date</th>
                  <th>End Date</th>
                  <th>Status</th>
                  <th>Source</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                @for (avail of availabilities(); track avail.id) {
                  <tr>
                    <td>{{ avail.start_date }}</td>
                    <td>{{ avail.end_date }}</td>
                    <td>
                      <span class="badge" [class]="'badge-' + avail.status">{{ avail.status | titlecase }}</span>
                    </td>
                    <td>
                      <span style="font-size:12px;color:var(--text-muted);">{{ avail.source || 'manual' }}</span>
                    </td>
                    <td>
                      <button class="btn btn-sm btn-danger" (click)="deleteAvailability(avail)" [disabled]="deletingAvailId() === avail.id">
                        @if (deletingAvailId() === avail.id) {
                          <span class="spinner"></span>
                        } @else {
                          Delete
                        }
                      </button>
                    </td>
                  </tr>
                }
              </tbody>
            </table>
          </div>
        }
      </div>

      <!-- Calendar Sync Section -->
      <div class="card section-card">
        <div class="card-header">
          <h2>Calendar Sync</h2>
        </div>

        <p class="text-muted" style="margin-bottom:12px;">
          Link a public Google Calendar (or any iCal URL) to automatically mark occupied dates.
          Blank days in the calendar = available. Days with events = occupied.
        </p>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div class="form-group" style="margin-bottom:0;">
            <label for="ical-url">
              Public iCal URL
              <button type="button" class="help-btn" (click)="showIcalHelp.set(!showIcalHelp())"
                title="How does calendar sync work?">?</button>
            </label>
            <input id="ical-url" type="url" [(ngModel)]="icalUrl" name="icalUrl"
              placeholder="https://calendar.google.com/calendar/ical/.../basic.ics">
            @if (showIcalHelp()) {
              <p class="help-text">
                Paste a public iCal/Google Calendar link (ending in <code>.ics</code>).
                Days with calendar events are marked occupied; blank days stay available.
                Use the optional keyword to count only matching events. You can also upload
                an <code>.ics</code> file directly. Synced calendars refresh daily.
              </p>
            }
          </div>
          <div class="form-group" style="margin-bottom:0;">
            <label for="cal-keyword">Calendar Keyword <span style="font-weight:400;color:var(--text-muted);font-size:11px;">(optional)</span></label>
            <input id="cal-keyword" type="text" [(ngModel)]="calendarKeyword" name="calendarKeyword"
              placeholder="e.g. Billboard Norte — only events with this name count as occupied">
          </div>
        </div>

        <div style="display:flex;gap:8px;margin-top:12px;align-items:center;flex-wrap:wrap;">
          <button class="btn btn-sm" (click)="saveCalendarSettings()" [disabled]="savingCalendar()">
            @if (savingCalendar()) { <span class="spinner"></span> } @else { Save Settings }
          </button>
          <button class="btn btn-sm" style="background:var(--primary);color:#fff;border-color:var(--primary);"
            (click)="syncCalendar()" [disabled]="syncingCalendar() || !icalUrl">
            @if (syncingCalendar()) { <span class="spinner"></span> Syncing... } @else { Sync Now }
          </button>

          <span style="color:var(--text-muted);font-size:12px;">— or —</span>

          <label class="btn btn-sm" [class.disabled]="uploadingIcal()">
            @if (uploadingIcal()) { <span class="spinner"></span> } @else { Upload .ics File }
            <input type="file" accept=".ics" (change)="onIcsFileSelected($event)" hidden [disabled]="uploadingIcal()">
          </label>
        </div>

        @if (calendarSyncResult()) {
          <p style="margin-top:8px;font-size:13px;" [style.color]="calendarSyncResult()!.startsWith('Error') ? 'var(--danger)' : 'var(--success)'">
            {{ calendarSyncResult() }}
          </p>
        }
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
  availabilities = signal<SpaceAvailability[]>([]);
  loading = signal(false);
  error = signal('');
  uploadingPhoto = signal(false);
  deletingPhotoId = signal<number | null>(null);
  addingAvail = signal(false);
  deletingAvailId = signal<number | null>(null);
  savingCalendar = signal(false);
  syncingCalendar = signal(false);
  uploadingIcal = signal(false);
  calendarSyncResult = signal<string | null>(null);
  showIcalHelp = signal(false);

  icalUrl = '';
  calendarKeyword = '';

  availForm = {
    start_date: '',
    end_date: '',
    status: 'available' as 'available' | 'blocked',
  };

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
        this.availabilities.set((res as any).availabilities || []);
        this.icalUrl = res.ical_url || '';
        this.calendarKeyword = res.calendar_keyword || '';
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

  // --- Availabilities ---

  addAvailability(): void {
    if (!this.availForm.start_date || !this.availForm.end_date) return;

    this.addingAvail.set(true);

    this.http.post<SpaceAvailability>(
      `${this.api}/provider/spaces/${this.spaceId}/availabilities`,
      this.availForm,
    ).subscribe({
      next: (res) => {
        this.availabilities.update(list => [...list, res]);
        this.notify.success('Availability added.');
        this.availForm = { start_date: '', end_date: '', status: 'available' };
        this.addingAvail.set(false);
      },
      error: (err) => {
        this.notify.error(err.error?.message || 'Failed to add availability.');
        this.addingAvail.set(false);
      },
    });
  }

  deleteAvailability(avail: SpaceAvailability): void {
    if (!confirm('Delete this availability?')) return;

    this.deletingAvailId.set(avail.id);

    this.http.delete(`${this.api}/provider/spaces/${this.spaceId}/availabilities/${avail.id}`).subscribe({
      next: () => {
        this.availabilities.update(list => list.filter(a => a.id !== avail.id));
        this.notify.success('Availability deleted.');
        this.deletingAvailId.set(null);
      },
      error: (err) => {
        this.notify.error(err.error?.message || 'Failed to delete availability.');
        this.deletingAvailId.set(null);
      },
    });
  }

  // --- Calendar Sync ---

  saveCalendarSettings(): void {
    this.savingCalendar.set(true);
    this.http.put(`${this.api}/provider/spaces/${this.spaceId}`, {
      ical_url: this.icalUrl || null,
      calendar_keyword: this.calendarKeyword || null,
    }).subscribe({
      next: () => {
        this.notify.success('Calendar settings saved.');
        this.savingCalendar.set(false);
      },
      error: (err) => {
        this.notify.error(err.error?.message || 'Failed to save calendar settings.');
        this.savingCalendar.set(false);
      },
    });
  }

  syncCalendar(): void {
    this.syncingCalendar.set(true);
    this.calendarSyncResult.set(null);
    this.http.post<any>(`${this.api}/provider/spaces/${this.spaceId}/sync-ical`, {}).subscribe({
      next: (res) => {
        const count = res.synced ?? 0;
        this.calendarSyncResult.set(`Synced ${count} occupied date range(s) from calendar.`);
        this.syncingCalendar.set(false);
        this.loadSpace(); // reload availabilities
      },
      error: (err) => {
        this.calendarSyncResult.set('Error: ' + (err.error?.message || 'Sync failed.'));
        this.syncingCalendar.set(false);
      },
    });
  }

  onIcsFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (!input.files?.length) return;

    const formData = new FormData();
    formData.append('file', input.files[0]);

    this.uploadingIcal.set(true);
    this.calendarSyncResult.set(null);
    this.http.post<any>(`${this.api}/provider/spaces/${this.spaceId}/import-ical`, formData).subscribe({
      next: (res) => {
        const count = res.synced ?? 0;
        this.calendarSyncResult.set(`Imported ${count} occupied date range(s) from file.`);
        this.uploadingIcal.set(false);
        input.value = '';
        this.loadSpace();
      },
      error: (err) => {
        this.calendarSyncResult.set('Error: ' + (err.error?.message || 'Import failed.'));
        this.uploadingIcal.set(false);
        input.value = '';
      },
    });
  }

  formatType(type: string): string {
    return type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
  }
}
