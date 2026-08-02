import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpParams } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';

// UC-31 · design.json §12/§17 — the immutable audit log, Admin read-only.
// GET /admin/audit with ?target_type ?target_id ?actor_id ?action.
// There is nothing to click but filters: the log is append-only, so this screen
// has no edit, no delete and no reply. Same posture as the oversight screen.

interface AuditActor {
  id: number;
  name: string;
  email: string;
  role: string;
}

interface AuditEntry {
  id: number;
  actor_id: number | null;
  actor_role: string;
  actor_label: string | null;
  action: string;
  target_type: string;
  /** null when the action targets a SET, not a row (a role's whole RBAC matrix). */
  target_id: number | null;
  before: Record<string, unknown> | null;
  after: Record<string, unknown> | null;
  context: string | null;
  created_at: string;
  actor?: AuditActor | null;
}

@Component({
  selector: 'app-audit-log',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="audit">
      <header class="audit__head">
        <h2>Audit Log</h2>
        <span class="append-only" title="Append-only — entries are never edited or deleted">append-only</span>
      </header>
      <p class="audit__note">
        One entry per staff action, with actor, target and the exact before/after (§12). Read-only:
        nothing on this screen can change an entry, and neither can anything else in the app.
      </p>

      <div class="filters">
        <select [(ngModel)]="fTargetType">
          <option value="">Object: any</option>
          <option value="spaces">spaces</option>
          <option value="ads">ads</option>
          <option value="bookings">bookings</option>
          <option value="users">users</option>
          <option value="collaborators">collaborators</option>
          <option value="payments">payments</option>
          <option value="chats">chats</option>
          <option value="system_configurations">system config</option>
          <option value="role_permissions">RBAC matrix</option>
        </select>
        <input type="number" min="1" [(ngModel)]="fTargetId" placeholder="Object id" class="num" />
        <input type="number" min="1" [(ngModel)]="fActorId" placeholder="Actor id" class="num" />
        <select [(ngModel)]="fAction">
          <option value="">Action: any</option>
          <option value="create">create</option>
          <option value="update">update</option>
          <option value="delete">delete</option>
          <option value="approve">approve ($)</option>
          <option value="reject">reject ($)</option>
          <option value="refund">refund ($)</option>
          <option value="release_payout">release payout ($)</option>
          <option value="hold_payout">hold payout ($)</option>
          <option value="flag_raise">flag raised</option>
          <option value="flag_change">flag changed</option>
        </select>
        <button type="button" class="btn btn-sm" (click)="load()">Apply</button>
        <button type="button" class="btn btn-sm" (click)="clearFilters()">Clear</button>
      </div>

      <p *ngIf="loading()" class="muted">Loading…</p>
      <p *ngIf="error()" class="error">{{ error() }}</p>

      <ul class="list">
        <li *ngFor="let e of entries()">
          <div class="row">
            <span class="badge badge--action">{{ e.action }}</span>
            <strong>{{ e.target_type }}<ng-container *ngIf="e.target_id"> #{{ e.target_id }}</ng-container></strong>
            <span class="who">
              {{ e.actor?.name || e.actor_label || 'system' }}
              <span class="badge">{{ e.actor_role }}</span>
            </span>
            <span class="when">{{ e.created_at | date: 'short' }}</span>
          </div>

          <table class="diff" *ngIf="changedKeys(e).length">
            <tr>
              <th>field</th>
              <th>before</th>
              <th>after</th>
            </tr>
            <tr *ngFor="let k of changedKeys(e)">
              <td class="k">{{ k }}</td>
              <td class="before">{{ render(e.before?.[k]) }}</td>
              <td class="after">{{ render(e.after?.[k]) }}</td>
            </tr>
          </table>

          <p class="context" *ngIf="e.context">{{ e.context }}</p>
        </li>
        <li *ngIf="!entries().length && !loading()" class="muted">No entries.</li>
      </ul>
    </div>
  `,
  styles: [`
    .audit { padding: 1rem; }
    .audit__head { display: flex; align-items: center; gap: .5rem; }
    .append-only { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; background: #222; color: #ddd; padding: .15rem .5rem; border-radius: 999px; }
    .audit__note { color: #666; margin: .25rem 0 1rem; max-width: 60ch; }
    .filters { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1rem; align-items: center; }
    .filters select, .filters input { padding: .4rem .6rem; border: 1px solid #ccc; border-radius: 6px; font-size: .85rem; }
    .filters .num { width: 110px; }
    .btn-sm { width: auto; }
    .list { list-style: none; margin: 0; padding: 0; }
    .list li { border: 1px solid #eee; border-radius: 8px; padding: .7rem .9rem; margin-bottom: .6rem; }
    .list li.muted { color: #999; border-style: dashed; }
    .row { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
    .who { color: #444; font-size: .85rem; display: flex; align-items: center; gap: .3rem; }
    .when { margin-left: auto; color: #888; font-size: .8rem; }
    .badge { font-size: .65rem; background: #e5e7eb; color: #374151; padding: .1rem .4rem; border-radius: 999px; }
    .badge--action { background: #dbeafe; color: #1e40af; text-transform: uppercase; letter-spacing: .04em; }
    .diff { width: 100%; border-collapse: collapse; margin-top: .5rem; font-size: .82rem; }
    .diff th { text-align: left; color: #888; font-weight: 500; border-bottom: 1px solid #f1f1f1; padding: .2rem .4rem; }
    .diff td { padding: .2rem .4rem; border-bottom: 1px solid #f7f7f7; vertical-align: top; }
    .diff .k { font-weight: 600; white-space: nowrap; }
    .diff .before { color: #991b1b; text-decoration: line-through; }
    .diff .after { color: #166534; }
    .context { margin: .4rem 0 0; color: #888; font-size: .78rem; font-style: italic; }
    .muted { color: #999; }
    .error { color: #b91c1c; }
  `],
})
export class AuditLogComponent implements OnInit {
  private readonly api = environment.apiUrl;
  private http = inject(HttpClient);
  private notify = inject(NotificationService);

  loading = signal(false);
  error = signal('');
  entries = signal<AuditEntry[]>([]);

  fTargetType = '';
  fTargetId: number | null = null;
  fActorId: number | null = null;
  fAction = '';

  ngOnInit(): void { this.load(); }

  clearFilters(): void {
    this.fTargetType = this.fAction = '';
    this.fTargetId = this.fActorId = null;
    this.load();
  }

  load(): void {
    this.loading.set(true);
    this.error.set('');
    let params = new HttpParams();
    if (this.fTargetType) params = params.set('target_type', this.fTargetType);
    if (this.fTargetId) params = params.set('target_id', String(this.fTargetId));
    if (this.fActorId) params = params.set('actor_id', String(this.fActorId));
    if (this.fAction) params = params.set('action', this.fAction);

    this.http.get<{ data: AuditEntry[] }>(`${this.api}/admin/audit`, { params }).subscribe({
      next: (res) => {
        this.entries.set(res.data ?? []);
        this.loading.set(false);
      },
      error: (err) => {
        this.loading.set(false);
        this.error.set(err.error?.message || 'Failed to load the audit log.');
        this.notify.error('Failed to load the audit log.');
      },
    });
  }

  /** Every field the entry touched — `after` is authoritative, `before` mirrors it. */
  changedKeys(e: AuditEntry): string[] {
    return Object.keys(e.after ?? e.before ?? {});
  }

  render(value: unknown): string {
    if (value === null || value === undefined) return '—';
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
  }
}
