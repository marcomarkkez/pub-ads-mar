import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';
import { Space, formatSpaceType, spaceTypeClass } from '../../../core/models';
// UC-37 — one reason a listing cannot be scheduled for deletion (the 409 body). The
// shape is shared with the account guardrail (Account::disputeBlockers/inUseBlockers),
// so it is declared once: two copies of one API contract drift, and the drift shows up
// as a refusal the user cannot read.
import { Blocker as SpaceBlocker } from '../../../core/models/api-error';

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
                <th>State</th>
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
                    <span class="badge" [class]="typeClass(space.type)">
                      {{ formatType(space.type) }}
                    </span>
                  </td>
                  <td>{{ space.location_text || (space.latitude + ', ' + space.longitude) }}</td>
                  <!-- A listing with no price is a real row in the data; an empty cell
                       would read as "free". Say we don't know instead. -->
                  <td>{{ (space.price_per_day | currency:'EUR') || 'Not set' }}</td>
                  <td>
                    <!-- §12 keeps THREE levels apart and so does this cell: the provider's own
                         pause, an admin takedown they cannot reverse, and a programmed deletion. -->
                    @if (space.taken_down_at) {
                      <span class="badge badge-cancelled" [title]="space.takedown_reason || ''">Taken down</span>
                    } @else if (space.delete_scheduled_at) {
                      <span class="badge badge-stale">Unpublished</span>
                      <span class="sub">purge on {{ space.delete_scheduled_at | date:'mediumDate' }}</span>
                    } @else if (space.is_active) {
                      <span class="badge badge-active">Published</span>
                    } @else {
                      <span class="badge badge-cancelled">Paused</span>
                    }
                  </td>
                  <td>{{ space.photos?.length || 0 }}</td>
                  <td class="actions">
                    <a [routerLink]="['/provider/spaces', space.id]" class="btn btn-sm">View</a>
                    <a [routerLink]="['/provider/spaces', space.id, 'edit']" class="btn btn-sm">Edit</a>
                    <!-- UC-37 — DELETE does not delete: it unpublishes and PROGRAMS a purge,
                         and 409s with blockers while clients are still on the listing. The
                         button says what the endpoint does, and the way back is offered. -->
                    @if (space.delete_scheduled_at) {
                      <button class="btn btn-sm" (click)="cancelDeletion(space)" [disabled]="busyId() === space.id">
                        Cancel deletion
                      </button>
                    } @else {
                      <button class="btn btn-sm btn-danger" (click)="scheduleDeletion(space)" [disabled]="busyId() === space.id">
                        Schedule deletion
                      </button>
                    }
                  </td>
                </tr>
                @if (blockersFor(space).length) {
                  <tr>
                    <td colspan="7" class="blockers">
                      <strong>This listing cannot be scheduled for deletion yet:</strong>
                      @for (b of blockersFor(space); track b.kind) {
                        <span class="blocker">{{ b.message }}</span>
                      }
                    </td>
                  </tr>
                }
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
    /* No type on the record. Deliberately colourless: it is an absence, not a kind. */
    .type-unknown { background: #f3f4f6; color: #6b7280; }
    .sub {
      display: block;
      font-size: 11px;
      color: var(--text-muted);
      margin-top: 2px;
    }
    .blockers {
      background: #fef2f2;
      color: #991b1b;
      font-size: 12px;
      padding: 8px 12px;
    }
    .blocker {
      display: block;
      margin-top: 2px;
    }
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
  busyId = signal<number | null>(null);
  /** 409 `blockers[]` from the last refused deletion, per space id. */
  blockers = signal<Record<number, SpaceBlocker[]>>({});
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

  /**
   * UC-37 · §12 — DELETE /provider/spaces/{id} UNPUBLISHES and programs a purge; it
   * destroys nothing (a human runs `spaces:purge` later, re-checking the blockers then).
   *
   * The old copy here said "This action cannot be undone" and dropped the row out of the
   * table on 200 — both false. The listing stays, in `unpublished`, with a date and a way
   * back, and a listing that still has live bookings or unsettled payments answers 409
   * with the reasons attached.
   */
  scheduleDeletion(space: Space): void {
    const ok = confirm(
      `Unpublish "${space.name}" and program its deletion?\n\n` +
      'It leaves the catalog now. Nothing is destroyed today: the purge runs after the ' +
      'grace period, and you can cancel it until then.'
    );
    if (!ok) return;

    this.busyId.set(space.id);
    this.blockers.update(m => { const n = { ...m }; delete n[space.id]; return n; });

    this.http.delete<{ message: string; space: Space }>(`${this.api}/provider/spaces/${space.id}`).subscribe({
      next: (res) => {
        this.replace(res.space ?? { ...space });
        this.notify.success('Listing unpublished and programmed for deletion.');
        this.busyId.set(null);
      },
      error: (err) => {
        // 409 carries `blockers[]` — the refusal names WHICH clients are holding the
        // listing, so it is rendered under the row instead of collapsed into a toast.
        const list = err.error?.blockers as SpaceBlocker[] | undefined;
        if (list?.length) {
          this.blockers.update(m => ({ ...m, [space.id]: list }));
        }
        this.notify.error(err.error?.message || 'Failed to schedule the deletion.');
        this.busyId.set(null);
      },
    });
  }

  /** The way back (POST /provider/spaces/{id}/cancel-deletion) — had no UI at all. */
  cancelDeletion(space: Space): void {
    this.busyId.set(space.id);

    this.http.post<{ message: string; space: Space }>(
      `${this.api}/provider/spaces/${space.id}/cancel-deletion`, {}
    ).subscribe({
      next: (res) => {
        this.replace(res.space ?? { ...space });
        this.notify.success('Programmed deletion cancelled.');
        this.busyId.set(null);
      },
      error: (err) => {
        this.notify.error(err.error?.message || 'Failed to cancel the programmed deletion.');
        this.busyId.set(null);
      },
    });
  }

  blockersFor(space: Space): SpaceBlocker[] {
    return this.blockers()[space.id] ?? [];
  }

  private replace(space: Space): void {
    this.spaces.update(list => list.map(s => (s.id === space.id ? { ...s, ...space } : s)));
  }

  // `spaces.type` is nullable, and a null used to throw from inside the template, which
  // took the whole update pass down with it (no href on View/Edit, no delete button, and
  // a navbar that stopped repainting). See core/models/space-type.helper.ts.
  formatType = formatSpaceType;
  typeClass = spaceTypeClass;

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
