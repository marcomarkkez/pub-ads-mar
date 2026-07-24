import { Component, OnInit, AfterViewChecked, ElementRef, ViewChild, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';
import { AuthService } from '../../../core/services/auth.service';
import {
  Chat, ChatMessage, ChatFlag, ChatObject, ChatFlagType,
  activeFlags, displayChatStatus, chatTitle,
} from '../../../core/models';
import { ObjectPickerComponent, PickedObject } from './object-picker.component';

// UC-8/UC-9/UC-21/UC-22 · design.md §10/§17 — chat detail: participants, attached OBJECTS,
// active FLAGS, state as Abierto/Cerrado, messages, and role actions
// (Support: join/flag/resolve · opener: close).
@Component({
  selector: 'app-chat-detail',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink, ObjectPickerComponent],
  template: `
    <div class="page-header">
      <h1>
        <a routerLink="/messages" class="back-link">&larr;</a>
        {{ title() }}
        @if (chat()) {
          <span class="badge state-badge"
                [class.badge-open]="displayStatus() === 'Abierto'"
                [class.badge-cancelled]="displayStatus() === 'Cerrado'">
            {{ displayStatus() }}
          </span>
        }
      </h1>
    </div>

    @if (loading()) {
      <div class="loading-container"><span class="spinner"></span></div>
    }

    @if (error()) {
      <div class="alert alert-error">{{ error() }}</div>
    }

    @if (!loading() && chat(); as c) {
      <div class="chat-layout">
        <!-- Sidebar: participants / objects / flags / actions -->
        <aside class="side card">
          <section class="panel">
            <h3>Participantes</h3>
            @if ((c.participants ?? []).length === 0) {
              <p class="muted">—</p>
            }
            @for (p of c.participants ?? []; track p.id) {
              <div class="participant">
                <span class="p-name">{{ p.user?.name || ('#' + p.user_id) }}</span>
                <span class="badge p-side">{{ p.side }}</span>
                @if (p.announced) { <span class="badge announced">📣</span> }
              </div>
            }
          </section>

          <section class="panel">
            <h3>Objetos</h3>
            @if ((c.objects ?? []).length === 0) {
              <p class="muted">Sin objetos.</p>
            }
            @for (o of c.objects ?? []; track o.id) {
              <div class="object-row">
                <span>📎 {{ o.label || (o.object_type + ' #' + o.object_id) }}</span>
                @if (!isClosed()) {
                  <button type="button" class="link-btn" (click)="detachObject(o)">Quitar</button>
                }
              </div>
            }
            @if (!isClosed()) {
              <div class="attach">
                <app-object-picker (picked)="attachCandidate.set($event)"></app-object-picker>
                <button type="button" class="btn btn-sm" [disabled]="!attachCandidate() || busy()"
                        (click)="attachObject()">Adjuntar objeto</button>
              </div>
            }
          </section>

          <section class="panel">
            <h3>Flags</h3>
            @if (flags().length === 0) {
              <p class="muted">Sin flags activas.</p>
            }
            @for (f of flags(); track f.id) {
              <div class="flag-row">
                <span class="badge badge-flag">🚩 {{ f.type }}</span>
                @if (f.reason) { <span class="flag-reason">{{ f.reason }}</span> }
              </div>
            }
          </section>

          <!-- Support actions (UC-21 join, UC-22 raise/change flag, resolve) -->
          @if (isSupport() && !isClosed()) {
            <section class="panel actions">
              <h3>Acciones de Soporte</h3>
              <button type="button" class="btn btn-sm" [disabled]="busy()" (click)="join()">Unirse (announced)</button>
              <button type="button" class="btn btn-sm" [disabled]="busy()" (click)="resolve()">Resolver</button>
              <div class="flag-form">
                <select [(ngModel)]="flagType">
                  <option value="payment_held">payment_held</option>
                  <option value="refund">refund</option>
                  <option value="payout_hold">payout_hold</option>
                  <option value="cancel">cancel</option>
                  <option value="mismatch">mismatch</option>
                </select>
                <input type="text" [(ngModel)]="flagReason" placeholder="Motivo (opcional)" />
                <button type="button" class="btn btn-sm" [disabled]="busy()" (click)="raiseFlag()">Levantar / cambiar flag</button>
              </div>
            </section>
          }

          <!-- Opener action: close (UC-8/UC-9) -->
          @if (canClose()) {
            <section class="panel actions">
              <button type="button" class="btn btn-sm" [disabled]="busy()" (click)="close()">Cerrar chat</button>
            </section>
          }
        </aside>

        <!-- Messages -->
        <div class="thread card">
          <div class="messages-area" #messagesContainer>
            @if (messages().length === 0) {
              <div class="empty-state"><p>No hay mensajes todavía.</p></div>
            }
            @for (m of messages(); track m.id) {
              @if (m.kind === 'system') {
                <div class="system-msg">{{ m.body }}</div>
              } @else {
                <div class="message-bubble"
                     [class.mine]="m.sender_user_id === currentUserId"
                     [class.theirs]="m.sender_user_id !== currentUserId">
                  <div class="message-sender">{{ m.sender?.name || 'Usuario' }}</div>
                  <div class="message-body">{{ m.body }}</div>
                  <div class="message-time">{{ m.created_at | date:'short' }}</div>
                </div>
              }
            }
          </div>

          @if (!isClosed()) {
            <div class="message-input">
              <textarea [(ngModel)]="newMessage" rows="2" placeholder="Escribe tu mensaje…"
                        (keydown.enter)="onEnter($event)"></textarea>
              <button class="btn btn-primary send-btn" [disabled]="sending() || !newMessage.trim()"
                      (click)="send()">
                @if (sending()) { <span class="spinner"></span> } @else { Enviar }
              </button>
            </div>
          } @else {
            <div class="closed-note">Este chat está <strong>Cerrado</strong>. Abre un nuevo chat si necesitas ayuda.</div>
          }
        </div>
      </div>
    }
  `,
  styles: [`
    .back-link { text-decoration: none; color: var(--primary); margin-right: 8px; }
    .state-badge { margin-left: 10px; font-size: 12px; vertical-align: middle; }
    .chat-layout { display: grid; grid-template-columns: 300px 1fr; gap: 16px; align-items: start; }
    .side { padding: 0; }
    .panel { padding: 14px 16px; border-bottom: 1px solid var(--border); }
    .panel h3 { font-size: 13px; text-transform: uppercase; letter-spacing: .04em; color: var(--text-muted); margin: 0 0 10px; }
    .muted { color: var(--text-muted); font-size: 13px; }
    .participant, .object-row, .flag-row { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; font-size: 13px; }
    .object-row { justify-content: space-between; }
    .p-side { background: #e5e7eb; color: #374151; font-size: 11px; }
    .announced { background: #dbeafe; color: #1e40af; }
    .badge-flag { background: #fee2e2; color: #991b1b; font-size: 11px; }
    .flag-reason { font-size: 12px; color: var(--text-muted); }
    .link-btn { background: none; border: none; color: var(--primary); cursor: pointer; font-size: 12px; padding: 0; }
    .attach { margin-top: 10px; display: flex; flex-direction: column; gap: 8px; }
    .actions { display: flex; flex-direction: column; gap: 8px; }
    .btn-sm { width: auto; }
    .flag-form { display: flex; flex-direction: column; gap: 6px; margin-top: 6px; }
    .flag-form select, .flag-form input {
      padding: 6px 8px; border: 1px solid var(--border); border-radius: 6px;
      font-size: 13px; background: var(--surface); color: var(--text);
    }
    .thread { display: flex; flex-direction: column; height: calc(100vh - 220px); min-height: 400px; }
    .messages-area { flex: 1; overflow-y: auto; padding: 16px 0; display: flex; flex-direction: column; gap: 12px; }
    .system-msg { align-self: center; font-size: 12px; color: var(--text-muted); font-style: italic; background: var(--hover); padding: 4px 12px; border-radius: 999px; }
    .message-bubble { max-width: 70%; padding: 10px 14px; border-radius: 12px; font-size: 14px; }
    .message-bubble.mine { align-self: flex-end; background: var(--primary); color: #fff; border-bottom-right-radius: 4px; }
    .message-bubble.theirs { align-self: flex-start; background: var(--hover); color: var(--text); border-bottom-left-radius: 4px; }
    .message-sender { font-size: 11px; font-weight: 600; margin-bottom: 2px; opacity: .8; }
    .message-body { word-break: break-word; }
    .message-time { font-size: 10px; opacity: .7; margin-top: 4px; text-align: right; }
    .message-input { border-top: 1px solid var(--border); padding-top: 12px; display: flex; gap: 12px; align-items: flex-end; }
    .message-input textarea { flex: 1; resize: none; }
    .send-btn { width: auto; min-width: 80px; height: 42px; }
    .closed-note { border-top: 1px solid var(--border); padding-top: 12px; color: var(--text-muted); font-size: 13px; }
    @media (max-width: 800px) { .chat-layout { grid-template-columns: 1fr; } }
  `],
})
export class ChatDetailComponent implements OnInit, AfterViewChecked {
  @ViewChild('messagesContainer') private messagesContainer!: ElementRef;

  private readonly api = environment.apiUrl;
  private http = inject(HttpClient);
  private route = inject(ActivatedRoute);
  private notify = inject(NotificationService);
  private auth = inject(AuthService);

  private chatId = 0;
  private shouldScroll = false;

  chat = signal<Chat | null>(null);
  messages = signal<ChatMessage[]>([]);
  loading = signal(false);
  error = signal('');
  sending = signal(false);
  busy = signal(false);
  newMessage = '';

  attachCandidate = signal<PickedObject | null>(null);
  flagType: ChatFlagType = 'payment_held';
  flagReason = '';

  currentUserId: number | null = null;

  ngOnInit(): void {
    this.currentUserId = this.auth.user()?.id ?? null;
    this.chatId = Number(this.route.snapshot.paramMap.get('id'));
    this.load();
  }

  ngAfterViewChecked(): void {
    if (this.shouldScroll) { this.scrollToBottom(); this.shouldScroll = false; }
  }

  private unwrap<T>(res: T | { data: T }): T {
    return (res as { data?: T }).data ?? (res as T);
  }

  load(): void {
    this.loading.set(true);
    this.error.set('');
    // GET /chats/{id} returns { data: { chat, messages, closed? } } — the chat (with its
    // participants/objects/active_flags) and messages are SIBLINGS, not nested in one another.
    this.http.get<{ data: { chat: Chat; messages: ChatMessage[]; closed?: boolean } }>(
      `${this.api}/chats/${this.chatId}`
    ).subscribe({
      next: (res) => {
        const payload = res.data;
        this.chat.set(payload.chat);
        this.messages.set(payload.messages ?? []);
        this.loading.set(false);
        this.shouldScroll = true;
      },
      error: (err) => {
        this.loading.set(false);
        this.error.set(err.error?.message || 'No se pudo cargar el chat.');
        this.notify.error('No se pudo cargar el chat.');
      },
    });
  }

  // ---- derived view helpers ----
  displayStatus(): 'Abierto' | 'Cerrado' { return displayChatStatus(this.chat()?.status); }
  isClosed(): boolean { return this.chat()?.status === 'closed'; }
  flags(): ChatFlag[] { const c = this.chat(); return c ? activeFlags(c) : []; }
  isSupport(): boolean { return this.auth.userRole() === 'support'; }
  canClose(): boolean {
    const role = this.auth.userRole();
    return !this.isClosed() && (role === 'client' || role === 'provider');
  }
  title(): string {
    const c = this.chat();
    return c ? chatTitle(c) : 'Chat';
  }

  // ---- messages ----
  send(): void {
    const body = this.newMessage.trim();
    if (!body) return;
    this.sending.set(true);
    this.http.post<ChatMessage | { data: ChatMessage }>(
      `${this.api}/chats/${this.chatId}/messages`, { body }
    ).subscribe({
      next: (res) => {
        this.messages.update(m => [...m, this.unwrap(res)]);
        this.newMessage = '';
        this.sending.set(false);
        this.shouldScroll = true;
      },
      error: (err) => {
        this.sending.set(false);
        this.notify.error(err.error?.message || 'No se pudo enviar el mensaje.');
      },
    });
  }

  onEnter(event: Event): void {
    const ke = event as KeyboardEvent;
    if (!ke.shiftKey) { ke.preventDefault(); this.send(); }
  }

  // ---- objects (attach/detach; ownership-checked server-side) ----
  attachObject(): void {
    const obj = this.attachCandidate();
    if (!obj) return;
    this.busy.set(true);
    this.http.post(`${this.api}/chats/${this.chatId}/objects`, {
      object_type: obj.object_type, object_id: obj.object_id,
    }).subscribe({
      next: () => { this.busy.set(false); this.attachCandidate.set(null); this.notify.success('Objeto adjuntado.'); this.load(); },
      error: (err) => { this.busy.set(false); this.notify.error(err.error?.message || 'No se pudo adjuntar el objeto.'); },
    });
  }

  detachObject(o: ChatObject): void {
    this.busy.set(true);
    this.http.delete(`${this.api}/chats/${this.chatId}/objects/${o.id}`).subscribe({
      next: () => { this.busy.set(false); this.notify.success('Objeto quitado.'); this.load(); },
      error: (err) => { this.busy.set(false); this.notify.error(err.error?.message || 'No se pudo quitar el objeto.'); },
    });
  }

  // ---- Support actions ----
  join(): void { this.act('join', 'Te uniste al chat.'); }        // UC-21
  resolve(): void { this.act('resolve', 'Chat marcado como resuelto.'); }
  close(): void { this.act('close', 'Chat cerrado.'); }           // opener

  private act(path: 'join' | 'resolve' | 'close', okMsg: string): void {
    this.busy.set(true);
    this.http.post(`${this.api}/chats/${this.chatId}/${path}`, {}).subscribe({
      next: () => { this.busy.set(false); this.notify.success(okMsg); this.load(); },
      error: (err) => { this.busy.set(false); this.notify.error(err.error?.message || 'La acción falló.'); },
    });
  }

  raiseFlag(): void { // UC-22 — Support raises/changes a flag; money is executed by Payments (§8)
    this.busy.set(true);
    this.http.post(`${this.api}/chats/${this.chatId}/flags`, {
      type: this.flagType, reason: this.flagReason.trim() || null,
    }).subscribe({
      next: () => { this.busy.set(false); this.flagReason = ''; this.notify.success('Flag actualizada.'); this.load(); },
      error: (err) => { this.busy.set(false); this.notify.error(err.error?.message || 'No se pudo levantar la flag.'); },
    });
  }

  private scrollToBottom(): void {
    try {
      const el = this.messagesContainer?.nativeElement;
      if (el) el.scrollTop = el.scrollHeight;
    } catch { /* view not ready */ }
  }
}
