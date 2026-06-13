import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';
import { Space } from '../../../core/models';

@Component({
  selector: 'app-space-list',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="page-header">
      <h1>My Spaces</h1>
      <div style="display:flex;gap:8px;align-items:center">
        <button type="button" class="btn btn-sm help-btn" (click)="showIcalHelp.set(true)" title="How to get a public calendar (ICS) URL">?</button>
        <a routerLink="/provider/spaces/new" class="btn btn-primary" style="width:auto">+ Add Space</a>
      </div>
    </div>

    @if (showIcalHelp()) {
      <div class="modal-backdrop" (click)="showIcalHelp.set(false)">
        <div class="modal-card" (click)="$event.stopPropagation()">
          <h2>Getting a public calendar (ICS) URL</h2>
          <p>Connect a calendar so booked dates block automatically. You need a <strong>public ICS link</strong>:</p>
          <p><strong>Google Calendar:</strong> open Settings &rarr; select your calendar &rarr; "Integrate calendar" &rarr; copy the <em>Public address in iCal format</em> (ends in <code>.ics</code>). The calendar must be set to public.</p>
          <p><strong>Outlook / Microsoft 365:</strong> open Calendar settings &rarr; "Shared calendars" &rarr; Publish a calendar &rarr; choose permissions &rarr; copy the <em>ICS</em> link.</p>
          <p>Paste that link into a space's iCal URL field. We re-sync daily; the badge below shows when a calendar hasn't refreshed in over {{ stalenessDays }} days.</p>
          <button type="button" class="btn btn-primary" style="width:auto" (click)="showIcalHelp.set(false)">Got it</button>
        </div>
      </div>
    }

    @if (loading()) {
      <div class="loading-container"><span class="spinner"></span></div>
    } @else if (error()) {
      <div class="alert alert-error">{{ error() }}</div>
    } @else if (spaces().length === 0) {
      <div class="empty-state">
        <div class="empty-icon">&#x1F4CD;</div>
        <p>You haven't created any spaces yet.</p>
        <a routerLink="/provider/spaces/new" class="btn btn-primary" style="width:auto;margin-top:16px">Create your first space</a>
      </div>
    } @else {
      <div class="card">
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Name</th>
                <th>Type</th>
                <th>Location</th>
                <th>Price/Day</th>
                <th>Active</th>
                <th>Photos</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @for (space of spaces(); track space.id) {
                <tr>
                  <td>
                    <a [routerLink]="['/provider/spaces', space.id]" class="link">{{ space.name }}</a>
                    @if (isStale(space)) {
                      <span class="badge badge-stale" [title]="'Calendar last synced ' + staleDays(space) + ' days ago'">
                        Calendar {{ staleDays(space) }} days old &mdash; refresh
                      </span>
                    }
                  </td>
                  <td>
                    <span class="badge" [class]="'badge type-' + space.type">
                      {{ formatType(space.type) }}
                    </span>
                  </td>
                  <td>{{ space.location_name || (space.latitude + ', ' + space.longitude) }}</td>
                  <td>{{ space.price_per_day | currency:'EUR' }}</td>
                  <td>
                    <span class="badge" [class.badge-active]="space.is_active" [class.badge-cancelled]="!space.is_active">
                      {{ space.is_active ? 'Active' : 'Inactive' }}
                    </span>
                  </td>
                  <td>{{ space.photos?.length || 0 }}</td>
                  <td class="actions">
                    <a [routerLink]="['/provider/spaces', space.id]" class="btn btn-sm">View</a>
                    <a [routerLink]="['/provider/spaces', space.id, 'edit']" class="btn btn-sm">Edit</a>
                    <button class="btn btn-sm btn-danger" (click)="confirmDelete(space)" [disabled]="deleting()">
                      Delete
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
  styles: [`
    .link {
      color: var(--primary);
      text-decoration: none;
      font-weight: 600;
      &:hover { text-decoration: underline; }
    }
    .actions {
      display: flex;
      gap: 6px;
      white-space: nowrap;
    }
    .type-billboard { background: #dbeafe; color: #1e40af; }
    .type-big_screen { background: #ede9fe; color: #5b21b6; }
    .type-little_screen { background: #fce7f3; color: #9d174d; }
    .type-radio_station { background: #fef3c7; color: #92400e; }
    .type-other { background: #f3f4f6; color: #374151; }
    .badge-stale {
      display: inline-block;
      margin-left: 8px;
      background: #fef3c7;
      color: #92400e;
      border: 1px solid #fcd34d;
      font-size: 11px;
    }
    .help-btn {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      padding: 0;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .modal-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.4);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1000;
      padding: 16px;
    }
    .modal-card {
      background: #fff;
      border-radius: 8px;
      padding: 24px;
      max-width: 560px;
      width: 100%;
      box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    }
    .modal-card h2 { margin-top: 0; }
    .modal-card p { line-height: 1.5; }
    .modal-card code {
      background: #f3f4f6;
      padding: 1px 5px;
      border-radius: 3px;
      font-size: 13px;
    }
  `],
})
export class SpaceListComponent implements OnInit {
  spaces = signal<Space[]>([]);
  loading = signal(false);
  error = signal('');
  deleting = signal(false);
  showIcalHelp = signal(false);

  // Staleness threshold in days. No frontend SystemConfiguration service is
  // available, so default to 7 per the calendar_staleness_days config.
  readonly stalenessDays = 7;

  private readonly api = environment.apiUrl;

  constructor(
    private http: HttpClient,
    private notify: NotificationService,
  ) {}

  ngOnInit(): void {
    this.loadSpaces();
  }

  loadSpaces(): void {
    this.loading.set(true);
    this.error.set('');

    this.http.get<Space[]>(`${this.api}/provider/spaces`).subscribe({
      next: (res) => {
        this.spaces.set(res);
        this.loading.set(false);
      },
      error: (err) => {
        this.error.set(err.error?.message || 'Failed to load spaces.');
        this.loading.set(false);
      },
    });
  }

  confirmDelete(space: Space): void {
    if (!confirm(`Are you sure you want to delete "${space.name}"? This action cannot be undone.`)) {
      return;
    }
    this.deleting.set(true);

    this.http.delete(`${this.api}/provider/spaces/${space.id}`).subscribe({
      next: () => {
        this.spaces.update(list => list.filter(s => s.id !== space.id));
        this.notify.success(`Space "${space.name}" deleted.`);
        this.deleting.set(false);
      },
      error: (err) => {
        this.notify.error(err.error?.message || 'Failed to delete space.');
        this.deleting.set(false);
      },
    });
  }

  formatType(type: string): string {
    return type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
  }

  /** Whole days since the calendar was last synced, or null if never synced. */
  staleDays(space: Space): number | null {
    if (!space.calendar_synced_at) {
      return null;
    }
    const synced = new Date(space.calendar_synced_at).getTime();
    if (isNaN(synced)) {
      return null;
    }
    return Math.floor((Date.now() - synced) / 86_400_000);
  }

  /** True when a synced calendar has gone stale (older than the threshold). */
  isStale(space: Space): boolean {
    const days = this.staleDays(space);
    return days !== null && days > this.stalenessDays;
  }
}
