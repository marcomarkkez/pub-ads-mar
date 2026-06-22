import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink, ActivatedRoute } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';
import { LocationPickerComponent } from '../../../shared/components/location-picker/location-picker.component';
import { Space, SpaceAvailability } from '../../../core/models';

@Component({
  selector: 'app-space-form',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink, LocationPickerComponent],
  template: `
    <div class="page-header">
      <h1>{{ isEdit() ? 'Edit Space' : 'New Space' }}</h1>
      <a routerLink="/provider/spaces" class="btn">Back to Spaces</a>
    </div>

    @if (loadingSpace()) {
      <div class="loading-container"><span class="spinner"></span></div>
    } @else {
      <div class="card">
        @if (error()) {
          <div class="alert alert-error">{{ error() }}</div>
        }

        <form (ngSubmit)="onSubmit()" #spaceForm="ngForm">
          <div class="form-group">
            <label for="name">Name *</label>
            <input id="name" name="name" [(ngModel)]="form.name" required placeholder="Space name">
          </div>

          <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" [(ngModel)]="form.description" placeholder="Describe this space..."></textarea>
          </div>

          <div class="form-group">
            <label for="type">Type *</label>
            <select id="type" name="type" [(ngModel)]="form.type" required>
              <option value="" disabled>Select type</option>
              <option value="billboard">Billboard</option>
              <option value="big_screen">Big Screen</option>
              <option value="little_screen">Little Screen</option>
              <option value="radio_station">Radio Station</option>
              <option value="other">Other</option>
            </select>
          </div>

          <!-- Map Location Picker -->
          <div class="form-group">
            <label>Location * <span style="font-weight:400;color:var(--text-muted);font-size:12px;">— click the map or enter a what3words address</span></label>
            <app-location-picker
              [lat]="form.latitude"
              [lng]="form.longitude"
              height="320px"
              (locationChange)="onLocationChange($event)">
            </app-location-picker>

            <div class="form-row" style="margin-top:8px;">
              <div class="form-group" style="margin-bottom:0;">
                <label for="latitude">Latitude *</label>
                <input id="latitude" name="latitude" type="number" step="any"
                  [(ngModel)]="form.latitude" required placeholder="e.g. 25.6597">
              </div>
              <div class="form-group" style="margin-bottom:0;">
                <label for="longitude">Longitude *</label>
                <input id="longitude" name="longitude" type="number" step="any"
                  [(ngModel)]="form.longitude" required placeholder="e.g. -100.4023">
              </div>
            </div>
          </div>

          <div class="form-group">
            <label for="location_text">Location Name</label>
            <input id="location_text" name="location_text" [(ngModel)]="form.location_text" placeholder="e.g. San Pedro Garza García, NL">
          </div>

          <div class="form-group">
            <label for="price_per_day">Price per Day (MXN) *</label>
            <input id="price_per_day" name="price_per_day" type="number" step="0.01" min="0" [(ngModel)]="form.price_per_day" required placeholder="0.00">
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="width">Width (m)</label>
              <input id="width" name="width" type="number" step="0.01" min="0" [(ngModel)]="form.width" placeholder="Width in meters">
            </div>
            <div class="form-group">
              <label for="height">Height (m)</label>
              <input id="height" name="height" type="number" step="0.01" min="0" [(ngModel)]="form.height" placeholder="Height in meters">
            </div>
          </div>

          <div class="form-group checkbox-group">
            <label>
              <input type="checkbox" name="is_active" [(ngModel)]="form.is_active">
              <span>Active (visible to clients)</span>
            </label>
          </div>

          <div class="form-actions">
            <a routerLink="/provider/spaces" class="btn">Cancel</a>
            <button type="submit" class="btn btn-primary" style="width:auto" [disabled]="saving() || spaceForm.invalid">
              @if (saving()) {
                <span class="spinner"></span> Saving...
              } @else {
                {{ isEdit() ? 'Update Space' : 'Create Space' }}
              }
            </button>
          </div>
        </form>
      </div>

      <!-- [todo B2] Availability + Calendar moved here from the space VIEW (owner decision
           2026-06-20): availability/calendar belong in the Edit screen. Only meaningful for an
           existing space, so edit-mode only. New spaces: save first, then manage availability. -->
      @if (isEdit()) {
        <!-- Calendar Sync (preferred) -->
        <div class="card section-card">
          <div class="card-header"><h2>Calendar (preferred)</h2></div>
          <p class="text-muted" style="margin-bottom:12px;">
            Link a public Google Calendar (or any iCal URL) to automatically mark occupied dates.
            Blank days = available; days with events = occupied. This is the recommended way to keep
            availability up to date.
          </p>
          <div class="form-row">
            <div class="form-group" style="margin-bottom:0;">
              <label for="ical-url">
                Public iCal / Google Calendar URL
                <button type="button" class="help-btn" (click)="showIcalHelp.set(!showIcalHelp())" title="How does calendar sync work?">?</button>
              </label>
              <input id="ical-url" type="url" [(ngModel)]="icalUrl" name="icalUrl"
                placeholder="https://calendar.google.com/calendar/ical/.../basic.ics">
              @if (showIcalHelp()) {
                <p class="help-text">
                  Paste a public iCal/Google Calendar link (ending in <code>.ics</code>). Days with
                  events are marked occupied; blank days stay available. Use the optional keyword to
                  count only matching events. You can also upload an <code>.ics</code> file. Synced
                  calendars refresh daily.
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
            <button type="button" class="btn btn-sm" (click)="saveCalendarSettings()" [disabled]="savingCalendar()">
              @if (savingCalendar()) { <span class="spinner"></span> } @else { Save Settings }
            </button>
            <button type="button" class="btn btn-sm btn-primary" style="width:auto" (click)="syncCalendar()" [disabled]="syncingCalendar() || !icalUrl">
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

        <!-- Manual availability ranges (accordion) -->
        <div class="card section-card">
          <div class="card-header" style="cursor:pointer;" (click)="showManualAvail.set(!showManualAvail())">
            <h2>Manual availability ranges ({{ availabilities().length }}) <span style="font-weight:400;color:var(--text-muted);font-size:13px;">— {{ showManualAvail() ? 'hide' : 'show' }}</span></h2>
          </div>
          @if (showManualAvail()) {
            <p class="text-muted" style="margin-bottom:8px;">Add one or more date ranges when this space is available (use this if you don't sync a calendar).</p>
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
              <button type="button" class="btn btn-sm btn-success" (click)="addAvailability()" [disabled]="addingAvail() || !availForm.start_date || !availForm.end_date">
                @if (addingAvail()) { <span class="spinner"></span> } @else { + Add Range }
              </button>
            </div>

            @if (availabilities().length === 0) {
              <p class="text-muted" style="margin-top:16px">No availabilities yet. Add ranges above or sync a calendar.</p>
            } @else {
              <div class="table-container" style="margin-top:16px">
                <table>
                  <thead>
                    <tr><th>Start</th><th>End</th><th>Status</th><th>Source</th><th>Actions</th></tr>
                  </thead>
                  <tbody>
                    @for (avail of availabilities(); track avail.id) {
                      <tr>
                        <td>{{ avail.start_date }}</td>
                        <td>{{ avail.end_date }}</td>
                        <td><span class="badge" [class]="'badge-' + avail.status">{{ avail.status | titlecase }}</span></td>
                        <td><span style="font-size:12px;color:var(--text-muted);">{{ avail.source || 'manual' }}</span></td>
                        <td>
                          <button type="button" class="btn btn-sm btn-danger" (click)="deleteAvailability(avail)" [disabled]="deletingAvailId() === avail.id">
                            @if (deletingAvailId() === avail.id) { <span class="spinner"></span> } @else { Delete }
                          </button>
                        </td>
                      </tr>
                    }
                  </tbody>
                </table>
              </div>
            }
          }
        </div>
      }
    }
  `,
  styles: [`
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }
    .checkbox-group label {
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      font-weight: 400;

      input[type="checkbox"] {
        width: auto;
        accent-color: var(--primary);
      }
    }
    .form-actions {
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      margin-top: 24px;
      padding-top: 16px;
      border-top: 1px solid var(--border);
    }
    /* [todo B2] availability + calendar sections */
    .section-card { margin-top: 24px; }
    .text-muted { color: var(--text-muted); font-size: 14px; }
    .inline-form {
      display: flex;
      align-items: flex-end;
      gap: 12px;
      flex-wrap: wrap;
      padding-top: 8px;
      .form-group { margin-bottom: 0; flex: 1; min-width: 140px; }
      button { margin-bottom: 0; white-space: nowrap; }
    }
    .disabled { opacity: 0.5; pointer-events: none; }
    .help-btn {
      display: inline-flex; align-items: center; justify-content: center;
      width: 16px; height: 16px; margin-left: 6px; padding: 0;
      font-size: 11px; line-height: 1; border-radius: 50%;
      border: 1px solid var(--border); background: var(--bg-muted, #f3f4f6);
      color: var(--text-muted); cursor: pointer;
    }
    .help-text { margin-top: 6px; font-size: 12px; color: var(--text-muted); line-height: 1.5; }
  `],
})
export class SpaceFormComponent implements OnInit {
  isEdit = signal(false);
  loadingSpace = signal(false);
  saving = signal(false);
  error = signal('');

  form = {
    name: '',
    description: '',
    type: '' as string,
    latitude: null as number | null,
    longitude: null as number | null,
    location_text: '',
    price_per_day: null as number | null,
    width: null as number | null,
    height: null as number | null,
    is_active: true,
  };

  // [todo B2] availability + calendar (edit mode), moved from space-detail
  availabilities = signal<SpaceAvailability[]>([]);
  showManualAvail = signal(false);
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

  private spaceId: number | null = null;
  private readonly api = environment.apiUrl;

  constructor(
    private http: HttpClient,
    private router: Router,
    private route: ActivatedRoute,
    private notify: NotificationService,
  ) {}

  ngOnInit(): void {
    const idParam = this.route.snapshot.paramMap.get('id');
    const url = this.router.url;

    if (idParam && url.endsWith('/edit')) {
      this.spaceId = +idParam;
      this.isEdit.set(true);
      this.loadSpace();
    }
  }

  onLocationChange(coords: { lat: number; lng: number }): void {
    this.form.latitude = coords.lat;
    this.form.longitude = coords.lng;
  }

  private loadSpace(): void {
    this.loadingSpace.set(true);

    this.http.get<Space>(`${this.api}/provider/spaces/${this.spaceId}`).subscribe({
      next: (s) => {
        this.form.name = s.name;
        this.form.description = s.description || '';
        this.form.type = s.type;
        this.form.latitude = s.latitude;
        this.form.longitude = s.longitude;
        this.form.location_text = s.location_text || s.location_name || '';
        this.form.price_per_day = s.price_per_day;
        this.form.width = s.width;
        this.form.height = s.height;
        this.form.is_active = s.is_active;
        // [todo B2] hydrate availability + calendar fields for the edit-mode sections
        this.availabilities.set((s as any).availabilities || []);
        this.icalUrl = s.ical_url || '';
        this.calendarKeyword = s.calendar_keyword || '';
        this.loadingSpace.set(false);
      },
      error: (err) => {
        this.error.set(err.error?.message || 'Failed to load space.');
        this.loadingSpace.set(false);
      },
    });
  }

  // ── [todo B2] Availability ranges ──────────────────────────────────────────
  addAvailability(): void {
    if (!this.spaceId || !this.availForm.start_date || !this.availForm.end_date) return;
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
    if (!this.spaceId || !confirm('Delete this availability?')) return;
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

  // ── [todo B2] Calendar sync ────────────────────────────────────────────────
  saveCalendarSettings(): void {
    if (!this.spaceId) return;
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
    if (!this.spaceId) return;
    this.syncingCalendar.set(true);
    this.calendarSyncResult.set(null);
    this.http.post<any>(`${this.api}/provider/spaces/${this.spaceId}/sync-ical`, {}).subscribe({
      next: (res) => {
        this.calendarSyncResult.set(`Synced ${res.synced ?? 0} occupied date range(s) from calendar.`);
        this.syncingCalendar.set(false);
        this.reloadAvailabilities();
      },
      error: (err) => {
        this.calendarSyncResult.set('Error: ' + (err.error?.message || 'Sync failed.'));
        this.syncingCalendar.set(false);
      },
    });
  }

  onIcsFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (!input.files?.length || !this.spaceId) return;
    const formData = new FormData();
    formData.append('file', input.files[0]);
    this.uploadingIcal.set(true);
    this.calendarSyncResult.set(null);
    this.http.post<any>(`${this.api}/provider/spaces/${this.spaceId}/import-ical`, formData).subscribe({
      next: (res) => {
        this.calendarSyncResult.set(`Imported ${res.synced ?? 0} occupied date range(s) from file.`);
        this.uploadingIcal.set(false);
        input.value = '';
        this.reloadAvailabilities();
      },
      error: (err) => {
        this.calendarSyncResult.set('Error: ' + (err.error?.message || 'Import failed.'));
        this.uploadingIcal.set(false);
        input.value = '';
      },
    });
  }

  private reloadAvailabilities(): void {
    if (!this.spaceId) return;
    this.http.get<Space>(`${this.api}/provider/spaces/${this.spaceId}`).subscribe({
      next: (s) => this.availabilities.set((s as any).availabilities || []),
      error: () => {},
    });
  }

  onSubmit(): void {
    this.saving.set(true);
    this.error.set('');

    const payload = { ...this.form };

    const request$ = this.isEdit()
      ? this.http.put<any>(`${this.api}/provider/spaces/${this.spaceId}`, payload)
      : this.http.post<any>(`${this.api}/provider/spaces`, payload);

    request$.subscribe({
      next: (res) => {
        this.notify.success(this.isEdit() ? 'Space updated successfully.' : 'Space created! Now add availability & calendar below.');
        // [todo B2] availability/calendar now live in Edit -> send new spaces to the edit screen.
        if (this.isEdit()) {
          this.router.navigate(['/provider/spaces', this.spaceId]);
        } else {
          this.router.navigate(['/provider/spaces', res.id, 'edit']);
        }
      },
      error: (err) => {
        this.error.set(err.error?.message || 'Failed to save space.');
        this.saving.set(false);
      },
    });
  }
}
