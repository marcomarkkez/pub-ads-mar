import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';
import { AuthService } from '../../../core/services/auth.service';
import { Chat, activeFlags, displayChatStatus } from '../../../core/models';

// UC-8/UC-9 · design.md §10/§17 — the single Messages surface for ALL roles.
// GET /chats returns the caller's chats (Support's list is its DERIVED queue).
@Component({
  selector: 'app-chat-list',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  template: `
    <div class="page-header">
      <h1>Messages</h1>
      <a routerLink="/messages/new" class="btn btn-primary new-btn">Open a chat</a>
    </div>

    <div class="filter-bar card">
      <div class="filter-group">
        <label for="statusFilter">Estado</label>
        <select id="statusFilter" [ngModel]="statusFilter()" (ngModelChange)="statusFilter.set($event)">
          <option value="">Todos</option>
          <option value="Abierto">Abierto</option>
          <option value="Cerrado">Cerrado</option>
        </select>
      </div>
      <div class="filter-group">
        <label for="natureFilter">Naturaleza</label>
        <select id="natureFilter" [ngModel]="natureFilter()" (ngModelChange)="natureFilter.set($event)">
          <option value="">Todas</option>
          <option value="client_provider">Cliente ↔ Proveedor</option>
          <option value="support_client">Soporte ↔ Cliente</option>
          <option value="support_provider">Soporte ↔ Proveedor</option>
          <option value="internal">Interno</option>
          <option value="general">General</option>
        </select>
      </div>
    </div>

    @if (loading()) {
      <div class="loading-container"><span class="spinner"></span></div>
    }

    @if (error()) {
      <div class="alert alert-error">{{ error() }}</div>
    }

    @if (!loading() && filtered().length === 0 && !error()) {
      <div class="empty-state">
        <div class="empty-icon">💬</div>
        <p>No hay chats todavía.</p>
      </div>
    }

    @if (!loading() && filtered().length > 0) {
      <div class="chat-grid">
        @for (chat of filtered(); track chat.id) {
          <a [routerLink]="['/messages', chat.id]" class="card chat-card">
            <div class="chat-head">
              <strong class="chat-title">{{ title(chat) }}</strong>
              <span class="badge" [class.badge-open]="displayStatus(chat) === 'Abierto'"
                    [class.badge-cancelled]="displayStatus(chat) === 'Cerrado'">
                {{ displayStatus(chat) }}
              </span>
            </div>
            <div class="chat-meta">
              <span class="participants">{{ participantSummary(chat) }}</span>
            </div>
            @if (objectCount(chat) > 0) {
              <div class="chat-objects">📎 {{ objectCount(chat) }} objeto(s)</div>
            }
            @if (flagsFor(chat).length > 0) {
              <div class="chat-flags">
                @for (f of flagsFor(chat); track f.id) {
                  <span class="badge badge-flag">🚩 {{ f.type }}</span>
                }
              </div>
            }
            @if (chat.latest_message) {
              <div class="chat-preview">
                {{ chat.latest_message.body.slice(0, 90) }}{{ chat.latest_message.body.length > 90 ? '…' : '' }}
              </div>
            }
          </a>
        }
      </div>
    }
  `,
  styles: [`
    .filter-bar { display: flex; gap: 20px; padding: 12px 16px; margin-bottom: 16px; }
    .filter-group { display: flex; align-items: center; gap: 8px; font-size: 13px; }
    .filter-group label { font-weight: 600; color: var(--text-muted); }
    .filter-group select {
      padding: 6px 10px; border: 1px solid var(--border); border-radius: 6px;
      font-size: 13px; background: var(--surface); color: var(--text);
    }
    .new-btn { width: auto; }
    .chat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 16px; }
    .chat-card { text-decoration: none; color: var(--text); cursor: pointer; transition: all .15s; }
    .chat-card:hover { border-color: var(--primary); box-shadow: 0 2px 8px rgba(0,0,0,.08); }
    .chat-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
    .chat-title { font-size: 15px; }
    .chat-meta { font-size: 13px; color: var(--text-muted); margin-bottom: 8px; }
    .chat-objects { font-size: 12px; color: var(--text-muted); margin-bottom: 6px; }
    .chat-flags { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 6px; }
    .badge-flag { background: #fee2e2; color: #991b1b; font-size: 11px; }
    .chat-preview {
      font-size: 13px; color: var(--text-muted); padding-top: 8px;
      border-top: 1px solid var(--border); overflow: hidden; white-space: nowrap; text-overflow: ellipsis;
    }
  `],
})
export class ChatListComponent implements OnInit {
  private readonly api = environment.apiUrl;
  private http = inject(HttpClient);
  private notify = inject(NotificationService);
  private auth = inject(AuthService);

  chats = signal<Chat[]>([]);
  loading = signal(false);
  error = signal('');
  statusFilter = signal('');
  natureFilter = signal('');

  private currentUserId: number | null = null;

  // Client-side filtering keeps us off unverified query-param contracts.
  // Filters are signals so this computed re-evaluates when they change.
  filtered = computed(() => {
    const status = this.statusFilter();
    const nature = this.natureFilter();
    return this.chats().filter(c => {
      if (status && displayChatStatus(c.status) !== status) return false;
      if (nature && (c.nature ?? '') !== nature) return false;
      return true;
    });
  });

  ngOnInit(): void {
    this.currentUserId = this.auth.user()?.id ?? null;
    this.load();
  }

  load(): void {
    this.loading.set(true);
    this.error.set('');
    this.http.get<Chat[]>(`${this.api}/chats`).subscribe({
      next: (res) => {
        this.chats.set(res ?? []);
        this.loading.set(false);
      },
      error: (err) => {
        this.loading.set(false);
        this.error.set(err.error?.message || 'No se pudieron cargar los chats.');
        this.notify.error('No se pudieron cargar los chats.');
      },
    });
  }

  displayStatus(chat: Chat): 'Abierto' | 'Cerrado' { return displayChatStatus(chat.status); }
  flagsFor(chat: Chat) { return activeFlags(chat); }
  objectCount(chat: Chat): number { return chat.objects?.length ?? 0; }

  title(chat: Chat): string {
    if (chat.subject) return chat.subject;
    const obj = chat.objects?.[0];
    if (obj) return obj.label || `${obj.object_type} #${obj.object_id}`;
    return `Chat #${chat.id}`;
  }

  participantSummary(chat: Chat): string {
    const others = (chat.participants ?? [])
      .filter(p => p.user_id !== this.currentUserId)
      .map(p => p.user?.name || `${p.side}`);
    return others.length ? `Con: ${others.join(', ')}` : 'Sin otros participantes';
  }
}
