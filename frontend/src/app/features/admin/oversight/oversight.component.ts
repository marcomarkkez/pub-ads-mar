import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpParams } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';
import { Chat, ChatMessage, activeFlags, displayChatStatus } from '../../../core/models';

// UC-28 · design.json §10/§12/§17 — Admin chat oversight: ONE read-only/incognito screen over
// the unified chat primitive. GET /admin/oversight/chats (+ /{chat}), filter bar
// (nature/flag/object/status/participant). Admin never posts; only write-power is reopen.
@Component({
  selector: 'app-oversight',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="oversight">
      <header class="oversight__head">
        <h2>Messages — Eagle-eye Oversight</h2>
        <span class="incognito" title="Read-only — your presence is not announced">incognito</span>
      </header>
      <p class="oversight__note">Read-only view of EVERY chat (one primitive — §10). Admin observes silently.</p>

      <!-- Filter bar: nature / flag / object / status / participant (design.json §12) -->
      <div class="filters">
        <select [(ngModel)]="fNature">
          <option value="">Nature: all</option>
          <option value="client_provider">client ↔ provider</option>
          <option value="support_client">support ↔ client</option>
          <option value="support_provider">support ↔ provider</option>
          <option value="internal">internal</option>
          <option value="general">general</option>
        </select>
        <select [(ngModel)]="fFlag">
          <option value="">Flag: any</option>
          <option value="payment_held">payment_held</option>
          <option value="refund">refund</option>
          <option value="payout_hold">payout_hold</option>
          <option value="cancel">cancel</option>
          <option value="mismatch">mismatch</option>
        </select>
        <select [(ngModel)]="fStatus">
          <option value="">Status: all</option>
          <option value="open">open</option>
          <option value="in_progress">in_progress</option>
          <option value="resolved">resolved</option>
          <option value="closed">closed</option>
        </select>
        <select [(ngModel)]="fObjectType">
          <option value="">Object: any</option>
          <option value="campaign">campaign</option>
          <option value="adset">adset</option>
          <option value="ad">ad</option>
          <option value="space">space</option>
          <option value="booking">booking</option>
          <option value="payment">payment</option>
        </select>
        <input type="number" min="1" [(ngModel)]="fObjectId" placeholder="Object id" class="num" />
        <input type="text" [(ngModel)]="fParticipant" placeholder="Participant (name/id)" />
        <button type="button" class="btn btn-sm" (click)="load()">Apply</button>
        <button type="button" class="btn btn-sm" (click)="clearFilters()">Clear</button>
      </div>

      <p *ngIf="loading()" class="muted">Loading…</p>
      <p *ngIf="error()" class="error">{{ error() }}</p>

      <section class="split">
        <ul class="list">
          <li *ngFor="let c of chats()" [class.selected]="selected()?.id === c.id" (click)="open(c)">
            <div class="list__title">
              #{{ c.id }} · {{ label(c) }}
              <span class="badge">{{ display(c) }}</span>
            </div>
            <div class="list__sub">
              <span class="badge nature">{{ c.nature || 'chat' }}</span>
              <span *ngFor="let f of flagsOf(c)" class="badge badge--flag">🚩 {{ f.type }}</span>
            </div>
          </li>
          <li *ngIf="!chats().length && !loading()" class="muted">No chats.</li>
        </ul>

        <div class="thread" *ngIf="selected() as sel">
          <div class="thread__head">
            <h3>Chat #{{ sel.id }} — {{ display(sel) }}</h3>
            <button *ngIf="sel.status === 'closed'" type="button" class="btn btn-sm" (click)="reopen(sel)">
              Reopen (investigation)
            </button>
          </div>
          <div class="meta">
            <div><strong>Participants:</strong>
              <span *ngFor="let p of sel.participants ?? []" class="badge">{{ p.user?.name || ('#' + p.user_id) }} ({{ p.side }})</span>
            </div>
            <div *ngIf="(sel.objects ?? []).length"><strong>Objects:</strong>
              <span *ngFor="let o of sel.objects ?? []" class="badge">{{ o.label || (o.object_type + ' #' + o.object_id) }}</span>
            </div>
          </div>
          <p *ngIf="threadLoading()" class="muted">Loading messages…</p>
          <div class="msg" *ngFor="let m of messages()" [class.system]="m.kind === 'system'">
            <span class="msg__who">{{ m.sender?.name || (m.kind === 'system' ? 'system' : 'Unknown') }}</span>
            <span class="msg__body">{{ m.body }}</span>
          </div>
          <p *ngIf="!threadLoading() && !messages().length" class="muted">No messages.</p>
        </div>
      </section>
    </div>
  `,
  styles: [`
    .oversight { padding: 1rem; }
    .oversight__head { display: flex; align-items: center; gap: .5rem; }
    .incognito { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; background: #222; color: #ddd; padding: .15rem .5rem; border-radius: 999px; }
    .oversight__note { color: #666; margin: .25rem 0 1rem; }
    .filters { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1rem; align-items: center; }
    .filters select, .filters input { padding: .4rem .6rem; border: 1px solid #ccc; border-radius: 6px; font-size: .85rem; }
    .filters .num { width: 90px; }
    .btn-sm { width: auto; }
    .split { display: grid; grid-template-columns: 1fr 1.4fr; gap: 1rem; }
    .list { list-style: none; margin: 0; padding: 0; border: 1px solid #eee; border-radius: 8px; max-height: 70vh; overflow: auto; }
    .list li { padding: .6rem .8rem; border-bottom: 1px solid #f1f1f1; cursor: pointer; }
    .list li.selected { background: #eef2ff; }
    .list li.muted { cursor: default; color: #999; }
    .list__title { font-weight: 600; display: flex; align-items: center; gap: .4rem; }
    .list__sub { font-size: .8rem; color: #666; display: flex; flex-wrap: wrap; gap: .3rem; margin-top: .2rem; }
    .badge { font-size: .65rem; background: #e5e7eb; color: #374151; padding: .1rem .4rem; border-radius: 999px; }
    .badge.nature { background: #dbeafe; color: #1e40af; }
    .badge--flag { background: #fee2e2; color: #991b1b; }
    .thread { border: 1px solid #eee; border-radius: 8px; padding: 1rem; max-height: 70vh; overflow: auto; }
    .thread__head { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
    .meta { font-size: .8rem; color: #444; margin: .5rem 0; display: flex; flex-direction: column; gap: .3rem; }
    .msg { display: flex; flex-direction: column; gap: .15rem; padding: .5rem 0; border-bottom: 1px solid #f5f5f5; }
    .msg.system { background: #f8fafc; font-style: italic; }
    .msg__who { font-weight: 600; font-size: .8rem; }
    .msg__body { white-space: pre-wrap; }
    .muted { color: #999; }
    .error { color: #b91c1c; }
  `],
})
export class OversightComponent implements OnInit {
  private readonly api = environment.apiUrl;
  private http = inject(HttpClient);
  private notify = inject(NotificationService);

  loading = signal(false);
  threadLoading = signal(false);
  error = signal('');
  chats = signal<Chat[]>([]);
  selected = signal<Chat | null>(null);
  messages = signal<ChatMessage[]>([]);

  fNature = '';
  fFlag = '';
  fStatus = '';
  fObjectType = '';
  fObjectId: number | null = null;
  fParticipant = '';

  ngOnInit(): void { this.load(); }

  clearFilters(): void {
    this.fNature = this.fFlag = this.fStatus = this.fObjectType = this.fParticipant = '';
    this.fObjectId = null;
    this.load();
  }

  load(): void {
    this.loading.set(true);
    this.error.set('');
    let params = new HttpParams();
    if (this.fNature) params = params.set('nature', this.fNature);
    if (this.fFlag) params = params.set('flag', this.fFlag);
    if (this.fStatus) params = params.set('status', this.fStatus);
    if (this.fObjectType) params = params.set('object_type', this.fObjectType);
    if (this.fObjectId) params = params.set('object_id', String(this.fObjectId));
    if (this.fParticipant) params = params.set('participant', this.fParticipant);

    this.http.get<Chat[] | { data: Chat[] }>(`${this.api}/admin/oversight/chats`, { params }).subscribe({
      next: (res) => {
        const arr = Array.isArray(res) ? res : (res.data ?? []);
        this.chats.set(arr);
        this.loading.set(false);
      },
      error: (err) => {
        this.loading.set(false);
        this.error.set(err.error?.message || 'Failed to load chats.');
        this.notify.error('Failed to load chats.');
      },
    });
  }

  open(c: Chat): void {
    this.selected.set(c);
    this.messages.set([]);
    this.threadLoading.set(true);
    this.http.get<Chat | { data: Chat }>(`${this.api}/admin/oversight/chats/${c.id}`).subscribe({
      next: (res) => {
        const chat = (res as { data?: Chat }).data ?? (res as Chat);
        this.selected.set(chat);
        this.messages.set(chat.messages ?? []);
        this.threadLoading.set(false);
      },
      error: (err) => {
        this.threadLoading.set(false);
        this.notify.error(err.error?.message || 'Failed to load chat.');
      },
    });
  }

  reopen(c: Chat): void { // UC-28 — Admin's ONLY chat write-power
    this.http.post(`${this.api}/chats/${c.id}/reopen`, {}).subscribe({
      next: () => { this.notify.success('Chat reopened.'); this.open(c); this.load(); },
      error: (err) => this.notify.error(err.error?.message || 'Failed to reopen chat.'),
    });
  }

  label(c: Chat): string {
    if (c.subject) return c.subject;
    const o = c.objects?.[0];
    return o ? (o.label || `${o.object_type} #${o.object_id}`) : 'Chat';
  }
  display(c: Chat): 'Abierto' | 'Cerrado' { return displayChatStatus(c.status); }
  flagsOf(c: Chat) { return activeFlags(c); }
}
