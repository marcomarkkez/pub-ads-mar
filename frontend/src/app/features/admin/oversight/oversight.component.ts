import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';

interface OversightUser {
  id: number;
  name: string;
  email?: string;
  role?: string;
}

interface OversightMessage {
  id: number;
  body: string;
  sender?: OversightUser;
  user?: OversightUser;
  is_internal?: boolean;
  created_at?: string;
}

interface OversightConversation {
  id: number;
  type?: string;
  space?: { id: number; name?: string } | null;
  client?: OversightUser;
  provider?: OversightUser;
  messages?: OversightMessage[];
  created_at?: string;
}

interface OversightTicket {
  id: number;
  subject: string;
  status?: string;
  priority?: string;
  ticketable_type?: string | null;
  ticketable_id?: number | null;
  user?: OversightUser;
  assignedTo?: OversightUser;
  messages?: OversightMessage[];
  created_at?: string;
}

@Component({
  selector: 'app-oversight',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="oversight">
      <header class="oversight__head">
        <h2>Eagle-eye Oversight</h2>
        <span class="incognito" title="Read-only — your presence is not announced">incognito</span>
      </header>
      <p class="oversight__note">Read-only view of every conversation and ticket in the system.</p>

      <div class="tabs">
        <button
          type="button"
          [class.active]="tab() === 'conversations'"
          (click)="setTab('conversations')"
        >Conversations</button>
        <button
          type="button"
          [class.active]="tab() === 'tickets'"
          (click)="setTab('tickets')"
        >Tickets</button>
      </div>

      <p *ngIf="loading()" class="muted">Loading…</p>
      <p *ngIf="error()" class="error">{{ error() }}</p>

      <!-- CONVERSATIONS -->
      <section *ngIf="tab() === 'conversations'" class="split">
        <ul class="list">
          <li
            *ngFor="let c of conversations()"
            [class.selected]="selectedConversation()?.id === c.id"
            (click)="openConversation(c)"
          >
            <div class="list__title">
              #{{ c.id }} · {{ c.space?.name || 'Direct' }}
              <span class="badge">{{ c.type || 'direct' }}</span>
            </div>
            <div class="list__sub">
              {{ c.client?.name || '—' }} ↔ {{ c.provider?.name || '—' }}
            </div>
            <div class="list__preview" *ngIf="c.messages?.length">
              {{ c.messages![0].body }}
            </div>
          </li>
          <li *ngIf="!conversations().length && !loading()" class="muted">No conversations.</li>
        </ul>

        <div class="thread" *ngIf="selectedConversation() as conv">
          <h3>Conversation #{{ conv.id }}</h3>
          <p *ngIf="threadLoading()" class="muted">Loading messages…</p>
          <div class="msg" *ngFor="let m of conversationMessages()">
            <span class="msg__who">{{ m.sender?.name || 'Unknown' }}</span>
            <span class="msg__body">{{ m.body }}</span>
          </div>
          <p *ngIf="!threadLoading() && !conversationMessages().length" class="muted">No messages.</p>
        </div>
      </section>

      <!-- TICKETS -->
      <section *ngIf="tab() === 'tickets'" class="split">
        <ul class="list">
          <li
            *ngFor="let t of tickets()"
            [class.selected]="selectedTicket()?.id === t.id"
            (click)="openTicket(t)"
          >
            <div class="list__title">
              #{{ t.id }} · {{ t.subject }}
              <span class="badge">{{ t.status || 'open' }}</span>
            </div>
            <div class="list__sub">
              {{ t.user?.name || '—' }}
              <ng-container *ngIf="t.ticketable_type"> · {{ t.ticketable_type }}#{{ t.ticketable_id }}</ng-container>
            </div>
          </li>
          <li *ngIf="!tickets().length && !loading()" class="muted">No tickets.</li>
        </ul>

        <div class="thread" *ngIf="selectedTicket() as tk">
          <h3>Ticket #{{ tk.id }} — {{ tk.subject }}</h3>
          <p *ngIf="threadLoading()" class="muted">Loading messages…</p>
          <div class="msg" *ngFor="let m of ticketMessages()" [class.internal]="m.is_internal">
            <span class="msg__who">
              {{ m.user?.name || 'Unknown' }}
              <span class="badge badge--internal" *ngIf="m.is_internal">internal</span>
            </span>
            <span class="msg__body">{{ m.body }}</span>
          </div>
          <p *ngIf="!threadLoading() && !ticketMessages().length" class="muted">No messages.</p>
        </div>
      </section>
    </div>
  `,
  styles: [`
    .oversight { padding: 1rem; }
    .oversight__head { display: flex; align-items: center; gap: .5rem; }
    .incognito {
      font-size: .7rem; text-transform: uppercase; letter-spacing: .05em;
      background: #222; color: #ddd; padding: .15rem .5rem; border-radius: 999px;
    }
    .oversight__note { color: #666; margin: .25rem 0 1rem; }
    .tabs { display: flex; gap: .5rem; margin-bottom: 1rem; }
    .tabs button {
      padding: .4rem .9rem; border: 1px solid #ccc; background: #fff;
      border-radius: 6px; cursor: pointer;
    }
    .tabs button.active { background: #1f2937; color: #fff; border-color: #1f2937; }
    .split { display: grid; grid-template-columns: 1fr 1.4fr; gap: 1rem; }
    .list { list-style: none; margin: 0; padding: 0; border: 1px solid #eee; border-radius: 8px; max-height: 70vh; overflow: auto; }
    .list li { padding: .6rem .8rem; border-bottom: 1px solid #f1f1f1; cursor: pointer; }
    .list li.selected { background: #eef2ff; }
    .list li.muted { cursor: default; color: #999; }
    .list__title { font-weight: 600; display: flex; align-items: center; gap: .4rem; }
    .list__sub { font-size: .8rem; color: #666; }
    .list__preview { font-size: .8rem; color: #888; margin-top: .2rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .badge { font-size: .65rem; background: #e5e7eb; color: #374151; padding: .1rem .4rem; border-radius: 999px; }
    .badge--internal { background: #fde68a; color: #92400e; }
    .thread { border: 1px solid #eee; border-radius: 8px; padding: 1rem; max-height: 70vh; overflow: auto; }
    .msg { display: flex; flex-direction: column; gap: .15rem; padding: .5rem 0; border-bottom: 1px solid #f5f5f5; }
    .msg.internal { background: #fffbeb; }
    .msg__who { font-weight: 600; font-size: .8rem; display: flex; align-items: center; gap: .3rem; }
    .msg__body { white-space: pre-wrap; }
    .muted { color: #999; }
    .error { color: #b91c1c; }
  `],
})
export class OversightComponent implements OnInit {
  private readonly api = environment.apiUrl;

  tab = signal<'conversations' | 'tickets'>('conversations');
  loading = signal(false);
  threadLoading = signal(false);
  error = signal('');

  conversations = signal<OversightConversation[]>([]);
  selectedConversation = signal<OversightConversation | null>(null);
  conversationMessages = signal<OversightMessage[]>([]);

  tickets = signal<OversightTicket[]>([]);
  selectedTicket = signal<OversightTicket | null>(null);
  ticketMessages = signal<OversightMessage[]>([]);

  constructor(
    private http: HttpClient,
    private notify: NotificationService,
  ) {}

  ngOnInit(): void {
    this.loadConversations();
  }

  setTab(tab: 'conversations' | 'tickets'): void {
    this.tab.set(tab);
    if (tab === 'conversations' && !this.conversations().length) {
      this.loadConversations();
    }
    if (tab === 'tickets' && !this.tickets().length) {
      this.loadTickets();
    }
  }

  private loadConversations(): void {
    this.loading.set(true);
    this.error.set('');
    this.http.get<OversightConversation[]>(`${this.api}/admin/oversight/conversations`).subscribe({
      next: (res) => {
        this.conversations.set(res);
        this.loading.set(false);
      },
      error: (err) => {
        this.loading.set(false);
        this.error.set(err.error?.message || 'Failed to load conversations.');
        this.notify.error('Failed to load conversations.');
      },
    });
  }

  openConversation(c: OversightConversation): void {
    this.selectedConversation.set(c);
    this.conversationMessages.set([]);
    this.threadLoading.set(true);
    this.http.get<OversightMessage[]>(`${this.api}/admin/oversight/conversations/${c.id}/messages`).subscribe({
      next: (res) => {
        this.conversationMessages.set(res);
        this.threadLoading.set(false);
      },
      error: (err) => {
        this.threadLoading.set(false);
        this.notify.error(err.error?.message || 'Failed to load messages.');
      },
    });
  }

  private loadTickets(): void {
    this.loading.set(true);
    this.error.set('');
    this.http.get<OversightTicket[]>(`${this.api}/admin/oversight/tickets`).subscribe({
      next: (res) => {
        this.tickets.set(res);
        this.loading.set(false);
      },
      error: (err) => {
        this.loading.set(false);
        this.error.set(err.error?.message || 'Failed to load tickets.');
        this.notify.error('Failed to load tickets.');
      },
    });
  }

  openTicket(t: OversightTicket): void {
    this.selectedTicket.set(t);
    this.ticketMessages.set([]);
    this.threadLoading.set(true);
    this.http.get<OversightTicket>(`${this.api}/admin/oversight/tickets/${t.id}`).subscribe({
      next: (res) => {
        this.ticketMessages.set(res.messages || []);
        this.threadLoading.set(false);
      },
      error: (err) => {
        this.threadLoading.set(false);
        this.notify.error(err.error?.message || 'Failed to load ticket.');
      },
    });
  }
}
