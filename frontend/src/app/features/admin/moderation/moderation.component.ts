import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';

// UC-29 + UC-32 · design.json §12/§8 — the admin moderation console.
//
// §12 gives Admin exactly one set of writes and this screen is all of them:
// takedown/restore on a listing, freeze/unfreeze on a provider account, and the
// payout stop / clawback of §8. Everything else Admin sees, it may only read.
//
// The screen is built around keeping the THREE levels of §12 apart, because that is
// where the feature is easy to get wrong:
//   - the provider's own pause (is_active) is shown, never edited from here;
//   - a takedown is admin state the provider cannot reverse;
//   - a freeze acts on the ACCOUNT and cancels + refunds upcoming bookings.
// The `bookable` column is the server's single answer to "can a client book this
// right now" — the UI never recomputes it from the three flags, so it cannot
// disagree with the catalog.
//
// Every destructive action asks for a REASON first (the API requires it: it goes
// into the audit row and, for a takedown/freeze, is shown to the provider).

interface ModSpace {
  id: number;
  name: string;
  location_text: string | null;
  provider: { id: number; name: string; email: string } | null;
  is_active: boolean;
  taken_down_at: string | null;
  takedown_reason: string | null;
  bookable: boolean;
}

interface ModProvider {
  id: number;
  name: string;
  email: string;
  is_active: boolean;
  frozen_at: string | null;
  freeze_reason: string | null;
  spaces_count: number;
}

interface ModPayout {
  id: number;
  amount: number;
  status: string;
  booking_id: number;
  client: { id: number; name: string } | null;
  provider: { id: number; name: string } | null;
  payout_releasable_at: string | null;
  payout_stop_deadline: string | null;
  payout_stopped_at: string | null;
  clawed_back_at: string | null;
  can_stop: boolean;
  can_clawback: boolean;
}

interface ModerationFeed {
  spaces: ModSpace[];
  providers: ModProvider[];
  payouts: ModPayout[];
  payout_stop_hours: number;
}

/** An action that needs a reason before it is sent. */
interface PendingAction {
  url: string;
  title: string;
  hint: string;
  danger: boolean;
}

@Component({
  selector: 'app-admin-moderation',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="page-header">
      <h1>Moderation</h1>
      <span class="audited-badge" title="Every action here writes an audit entry">audited</span>
    </div>

    <p class="page-note">
      The only actions Admin may take (§12). A takedown hides one listing and the provider
      cannot re-publish it; a freeze acts on the whole account, stops new bookings and
      cancels + refunds the upcoming ones. Each one is logged with actor, before/after and
      your reason.
    </p>

    <nav class="tabs">
      <button type="button" class="tab" [class.tab--on]="tab() === 'spaces'" (click)="tab.set('spaces')">
        Listings ({{ feed()?.spaces?.length || 0 }})
      </button>
      <button type="button" class="tab" [class.tab--on]="tab() === 'providers'" (click)="tab.set('providers')">
        Provider accounts ({{ feed()?.providers?.length || 0 }})
      </button>
      <button type="button" class="tab" [class.tab--on]="tab() === 'payouts'" (click)="tab.set('payouts')">
        Payouts ({{ feed()?.payouts?.length || 0 }})
      </button>
    </nav>

    @if (loading()) {
      <div class="loading-container"><span class="spinner"></span></div>
    }
    @if (error()) {
      <div class="alert alert-error">{{ error() }}</div>
    }

    @if (pending(); as p) {
      <div class="card reason-box" [class.reason-box--danger]="p.danger">
        <h3>{{ p.title }}</h3>
        <p class="reason-hint">{{ p.hint }}</p>
        <textarea rows="2" [(ngModel)]="reason" name="reason"
          placeholder="Reason (required — stored in the audit log)"></textarea>
        <div class="reason-actions">
          <button type="button" class="btn btn-primary" [disabled]="busy() || !reason.trim()" (click)="confirm()">
            Confirm
          </button>
          <button type="button" class="btn" (click)="cancel()">Cancel</button>
        </div>
      </div>
    }

    @if (!loading() && feed(); as f) {
      @if (tab() === 'spaces') {
        <div class="card">
          <table class="grid">
            <tr>
              <th>Listing</th>
              <th>Provider</th>
              <th>Provider pause</th>
              <th>Moderation</th>
              <th>Bookable</th>
              <th></th>
            </tr>
            @for (s of f.spaces; track s.id) {
              <tr>
                <td>
                  <strong>{{ s.name }}</strong>
                  <div class="sub">{{ s.location_text || '—' }}</div>
                </td>
                <td>{{ s.provider?.name || '—' }}</td>
                <td>
                  @if (s.is_active) { <span class="pill pill--ok">published</span> }
                  @else { <span class="pill">paused by provider</span> }
                </td>
                <td>
                  @if (s.taken_down_at) {
                    <span class="pill pill--bad">taken down</span>
                    <div class="sub">{{ s.takedown_reason }}</div>
                    <div class="sub">{{ s.taken_down_at | date: 'short' }}</div>
                  } @else {
                    <span class="sub">—</span>
                  }
                </td>
                <td>
                  @if (s.bookable) { <span class="pill pill--ok">yes</span> }
                  @else { <span class="pill pill--bad">no</span> }
                </td>
                <td class="right">
                  @if (s.taken_down_at) {
                    <button type="button" class="btn btn-sm" [disabled]="busy()" (click)="restoreSpace(s)">Restore</button>
                  } @else {
                    <button type="button" class="btn btn-sm btn-danger" (click)="askTakedown(s)">Take down</button>
                  }
                </td>
              </tr>
            }
            @if (!f.spaces.length) {
              <tr><td colspan="6" class="sub">No listings.</td></tr>
            }
          </table>
        </div>
      }

      @if (tab() === 'providers') {
        <div class="card">
          <table class="grid">
            <tr>
              <th>Provider</th>
              <th>Email</th>
              <th>Listings</th>
              <th>Account</th>
              <th></th>
            </tr>
            @for (p of f.providers; track p.id) {
              <tr>
                <td><strong>{{ p.name }}</strong></td>
                <td class="sub">{{ p.email }}</td>
                <td>{{ p.spaces_count }}</td>
                <td>
                  @if (p.frozen_at) {
                    <span class="pill pill--bad">frozen</span>
                    <div class="sub">{{ p.freeze_reason }}</div>
                    <div class="sub">{{ p.frozen_at | date: 'short' }}</div>
                  } @else if (!p.is_active) {
                    <span class="pill">deactivated</span>
                  } @else {
                    <span class="pill pill--ok">active</span>
                  }
                </td>
                <td class="right">
                  @if (p.frozen_at) {
                    <button type="button" class="btn btn-sm" [disabled]="busy()" (click)="unfreeze(p)">Unfreeze</button>
                  } @else {
                    <button type="button" class="btn btn-sm btn-danger" (click)="askFreeze(p)">Freeze account</button>
                  }
                </td>
              </tr>
            }
            @if (!f.providers.length) {
              <tr><td colspan="5" class="sub">No provider accounts.</td></tr>
            }
          </table>
        </div>
      }

      @if (tab() === 'payouts') {
        <div class="card">
          <p class="page-note">
            Admin may stop a payout for {{ f.payout_stop_hours }}h after the client frees it (§8).
            Once it has been released, the money is with the provider: pulling it back is a
            clawback — a new negative ledger entry, never an edit of the release.
          </p>
          <table class="grid">
            <tr>
              <th>Payment</th>
              <th>Provider</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Stop window</th>
              <th></th>
            </tr>
            @for (p of f.payouts; track p.id) {
              <tr>
                <td>
                  <strong>#{{ p.id }}</strong>
                  <div class="sub">booking #{{ p.booking_id }}</div>
                </td>
                <td>{{ p.provider?.name || '—' }}</td>
                <td>{{ p.amount | number: '1.2-2' }} MXN</td>
                <td>
                  <span class="pill" [class.pill--bad]="p.status === 'held'">{{ p.status }}</span>
                  @if (p.clawed_back_at) { <div class="sub">clawed back {{ p.clawed_back_at | date: 'short' }}</div> }
                  @else if (p.payout_stopped_at) { <div class="sub">stopped {{ p.payout_stopped_at | date: 'short' }}</div> }
                </td>
                <td class="sub">
                  @if (p.payout_stop_deadline) { until {{ p.payout_stop_deadline | date: 'short' }} }
                  @else { not releasable yet }
                </td>
                <td class="right">
                  @if (p.can_stop) {
                    <button type="button" class="btn btn-sm btn-danger" (click)="askStop(p)">Stop payout</button>
                  } @else if (p.can_clawback) {
                    <button type="button" class="btn btn-sm btn-danger" (click)="askClawback(p)">Clawback</button>
                  } @else {
                    <span class="sub">no action</span>
                  }
                </td>
              </tr>
            }
            @if (!f.payouts.length) {
              <tr><td colspan="6" class="sub">No payouts to act on.</td></tr>
            }
          </table>
        </div>
      }
    }
  `,
  styles: [`
    .page-header { display: flex; align-items: center; gap: .6rem; }
    .audited-badge { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; background: #222; color: #ddd; padding: .15rem .5rem; border-radius: 999px; }
    .page-note { color: #666; max-width: 80ch; margin: .25rem 0 1rem; font-size: .88rem; }
    .tabs { display: flex; gap: .4rem; margin-bottom: 1rem; flex-wrap: wrap; }
    .tab { background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 999px; padding: .35rem .9rem; font-size: .85rem; cursor: pointer; width: auto; }
    .tab--on { background: #1e40af; border-color: #1e40af; color: #fff; }
    .grid { width: 100%; border-collapse: collapse; font-size: .87rem; }
    .grid th { text-align: left; color: #888; font-weight: 500; border-bottom: 1px solid #eee; padding: .4rem .5rem; }
    .grid td { padding: .55rem .5rem; border-bottom: 1px solid #f5f5f5; vertical-align: top; }
    .grid td.right { text-align: right; white-space: nowrap; }
    .sub { color: #999; font-size: .78rem; }
    .pill { display: inline-block; font-size: .7rem; background: #e5e7eb; color: #374151; padding: .1rem .5rem; border-radius: 999px; }
    .pill--ok { background: #dcfce7; color: #166534; }
    .pill--bad { background: #fee2e2; color: #991b1b; }
    .btn-sm { width: auto; padding: .3rem .7rem; font-size: .8rem; }
    .btn-danger { background: #b91c1c; color: #fff; border-color: #b91c1c; }
    .reason-box { border-left: 4px solid #1e40af; }
    .reason-box--danger { border-left-color: #b91c1c; }
    .reason-box h3 { margin: 0 0 .2rem; font-size: 1rem; }
    .reason-hint { color: #666; font-size: .82rem; margin: 0 0 .5rem; }
    .reason-box textarea { width: 100%; padding: .5rem; border: 1px solid #ccc; border-radius: 6px; font: inherit; }
    .reason-actions { display: flex; gap: .5rem; margin-top: .5rem; }
    .reason-actions .btn { width: auto; }
  `],
})
export class AdminModerationComponent implements OnInit {
  private readonly api = environment.apiUrl;
  private http = inject(HttpClient);
  private notify = inject(NotificationService);

  loading = signal(false);
  busy = signal(false);
  error = signal('');
  feed = signal<ModerationFeed | null>(null);
  tab = signal<'spaces' | 'providers' | 'payouts'>('spaces');
  pending = signal<PendingAction | null>(null);
  reason = '';

  ngOnInit(): void { this.load(); }

  load(): void {
    this.loading.set(true);
    this.error.set('');
    this.http.get<ModerationFeed>(this.api + '/admin/moderation').subscribe({
      next: (res) => { this.feed.set(res); this.loading.set(false); },
      error: (err) => {
        this.loading.set(false);
        this.error.set(err.error?.message || 'Failed to load the moderation console.');
      },
    });
  }

  // ── actions that need a reason ────────────────────────────────────────

  askTakedown(s: ModSpace): void {
    this.ask({
      url: '/admin/spaces/' + s.id + '/takedown',
      title: 'Take down "' + s.name + '"',
      hint: 'The listing leaves the catalog immediately and its provider CANNOT re-publish it — only an admin restore can.',
      danger: true,
    });
  }

  askFreeze(p: ModProvider): void {
    this.ask({
      url: '/admin/providers/' + p.id + '/freeze',
      title: 'Freeze the account of ' + p.name,
      hint: 'Stops new bookings on all their listings, cancels every upcoming booking with a full refund to the client, and revokes their sessions. Payouts already released are NOT refunded — that would be a clawback.',
      danger: true,
    });
  }

  askStop(p: ModPayout): void {
    this.ask({
      url: '/admin/payments/' + p.id + '/payout/stop',
      title: 'Stop payout #' + p.id,
      hint: 'Pulls the payout back to held before any money moves. Payments has to decide again.',
      danger: true,
    });
  }

  askClawback(p: ModPayout): void {
    this.ask({
      url: '/admin/payments/' + p.id + '/clawback',
      title: 'Claw back payout #' + p.id,
      hint: 'The provider was already paid. This writes a NEW negative ledger entry against them for the full amount; the original release entry stays untouched.',
      danger: true,
    });
  }

  private ask(action: PendingAction): void {
    this.reason = '';
    this.pending.set(action);
  }

  cancel(): void {
    this.pending.set(null);
    this.reason = '';
  }

  confirm(): void {
    const action = this.pending();
    if (!action || !this.reason.trim()) return;
    this.post(action.url, { reason: this.reason.trim() }, 'Done. The action is in the audit log.');
  }

  // ── reversals: no reason required, the audit row records the actor ────

  restoreSpace(s: ModSpace): void {
    this.post('/admin/spaces/' + s.id + '/restore', {}, 'Listing restored. A pause by its provider, if any, still applies.');
  }

  unfreeze(p: ModProvider): void {
    this.post('/admin/providers/' + p.id + '/unfreeze', {}, 'Account unfrozen. Cancelled bookings are not restored.');
  }

  private post(path: string, body: Record<string, unknown>, okMessage: string): void {
    this.busy.set(true);
    this.http.post(this.api + path, body).subscribe({
      next: () => {
        this.busy.set(false);
        this.pending.set(null);
        this.reason = '';
        this.notify.success(okMessage);
        this.load();
      },
      error: (err) => {
        this.busy.set(false);
        // 409 = the object is in a state that forbids this (owner 2026-08-03). The
        // API always says which state, so it is shown verbatim rather than retyped.
        this.notify.error(err.error?.message || 'The action was refused.');
        this.load();
      },
    });
  }
}
