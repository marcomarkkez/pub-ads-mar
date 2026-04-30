import { Component, OnInit, signal, ViewChild, ElementRef, AfterViewChecked } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';
import { AuthService } from '../../../core/services/auth.service';
import { Conversation, Message } from '../../../core/models';

@Component({
  selector: 'app-conversation-detail',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  template: `
    <div class="page-header">
      <h1>
        <a routerLink="/conversations" class="back-link">&larr;</a>
        Conversation
        @if (conversation()) {
          <span class="header-sub">— {{ conversation()!.space?.name || 'Space' }}</span>
        }
      </h1>
    </div>

    @if (loading()) {
      <div class="loading-container">
        <span class="spinner"></span>
      </div>
    }

    @if (error()) {
      <div class="alert alert-error">{{ error() }}</div>
    }

    @if (!loading() && conversation()) {
      <div class="chat-container card">
        <div class="chat-info">
          <span>Space: <strong>{{ conversation()!.space?.name || 'N/A' }}</strong></span>
          <span>With: <strong>{{ getOtherPartyName() }}</strong></span>
        </div>

        <div class="messages-area" #messagesContainer>
          @if (messages().length === 0) {
            <div class="empty-state">
              <p>No messages yet. Start the conversation!</p>
            </div>
          }
          @for (msg of messages(); track msg.id) {
            <div class="message-bubble" [class.mine]="msg.sender_user_id === currentUserId" [class.theirs]="msg.sender_user_id !== currentUserId">
              <div class="message-sender">{{ msg.sender?.name || 'User' }}</div>
              <div class="message-body">{{ msg.body }}</div>
              <div class="message-time">{{ msg.created_at | date:'short' }}</div>
            </div>
          }
        </div>

        <div class="message-input">
          <div class="form-group input-row">
            <textarea
              [(ngModel)]="newMessage"
              placeholder="Type your message..."
              rows="2"
              (keydown.enter)="onEnterPress($event)"
            ></textarea>
            <button
              class="btn btn-primary send-btn"
              [disabled]="sending() || !newMessage.trim()"
              (click)="sendMessage()"
            >
              @if (sending()) {
                <span class="spinner"></span>
              } @else {
                Send
              }
            </button>
          </div>
        </div>
      </div>
    }
  `,
  styles: [`
    .back-link {
      text-decoration: none;
      color: var(--primary);
      margin-right: 8px;
    }
    .header-sub {
      font-size: 16px;
      font-weight: 400;
      color: var(--text-muted);
    }
    .chat-container {
      display: flex;
      flex-direction: column;
      height: calc(100vh - 200px);
      min-height: 400px;
    }
    .chat-info {
      display: flex;
      gap: 24px;
      padding-bottom: 12px;
      border-bottom: 1px solid var(--border);
      font-size: 13px;
      color: var(--text-muted);
    }
    .messages-area {
      flex: 1;
      overflow-y: auto;
      padding: 16px 0;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .message-bubble {
      max-width: 70%;
      padding: 10px 14px;
      border-radius: 12px;
      font-size: 14px;
    }
    .message-bubble.mine {
      align-self: flex-end;
      background: var(--primary);
      color: white;
      border-bottom-right-radius: 4px;
    }
    .message-bubble.theirs {
      align-self: flex-start;
      background: var(--hover);
      color: var(--text);
      border-bottom-left-radius: 4px;
    }
    .message-sender {
      font-size: 11px;
      font-weight: 600;
      margin-bottom: 2px;
      opacity: 0.8;
    }
    .message-body {
      word-break: break-word;
    }
    .message-time {
      font-size: 10px;
      opacity: 0.7;
      margin-top: 4px;
      text-align: right;
    }
    .message-input {
      border-top: 1px solid var(--border);
      padding-top: 12px;
    }
    .input-row {
      display: flex;
      gap: 12px;
      align-items: flex-end;
      margin-bottom: 0;
    }
    .input-row textarea {
      flex: 1;
      resize: none;
    }
    .send-btn {
      width: auto;
      min-width: 80px;
      height: 42px;
    }
  `],
})
export class ConversationDetailComponent implements OnInit, AfterViewChecked {
  @ViewChild('messagesContainer') private messagesContainer!: ElementRef;

  private readonly api = environment.apiUrl;
  private conversationId = 0;
  private shouldScrollToBottom = false;

  conversation = signal<Conversation | null>(null);
  messages = signal<Message[]>([]);
  loading = signal(false);
  error = signal('');
  sending = signal(false);
  newMessage = '';
  currentUserId: number | null = null;

  constructor(
    private http: HttpClient,
    private route: ActivatedRoute,
    private notify: NotificationService,
    private auth: AuthService,
  ) {}

  ngOnInit(): void {
    this.currentUserId = this.auth.user()?.id ?? null;
    this.conversationId = Number(this.route.snapshot.paramMap.get('id'));
    this.loadMessages();
  }

  ngAfterViewChecked(): void {
    if (this.shouldScrollToBottom) {
      this.scrollToBottom();
      this.shouldScrollToBottom = false;
    }
  }

  loadMessages(): void {
    this.loading.set(true);
    this.error.set('');

    this.http.get<{ data: { conversation: Conversation; messages: Message[] } }>(
      `${this.api}/conversations/${this.conversationId}/messages`
    ).subscribe({
      next: (res) => {
        this.conversation.set(res.data.conversation);
        this.messages.set(res.data.messages);
        this.loading.set(false);
        this.shouldScrollToBottom = true;
      },
      error: (err) => {
        this.loading.set(false);
        this.error.set(err.error?.message || 'Failed to load messages.');
        this.notify.error('Failed to load messages.');
      },
    });
  }

  sendMessage(): void {
    const body = this.newMessage.trim();
    if (!body) return;

    this.sending.set(true);

    this.http.post<{ data: Message }>(
      `${this.api}/conversations/${this.conversationId}/messages`,
      { body }
    ).subscribe({
      next: (res) => {
        this.messages.update(msgs => [...msgs, res.data]);
        this.newMessage = '';
        this.sending.set(false);
        this.shouldScrollToBottom = true;
      },
      error: (err) => {
        this.sending.set(false);
        this.notify.error(err.error?.message || 'Failed to send message.');
      },
    });
  }

  onEnterPress(event: Event): void {
    const keyEvent = event as KeyboardEvent;
    if (!keyEvent.shiftKey) {
      keyEvent.preventDefault();
      this.sendMessage();
    }
  }

  getOtherPartyName(): string {
    const conv = this.conversation();
    if (!conv) return '';
    if (this.currentUserId === conv.client_user_id) {
      return conv.provider?.name || 'Provider #' + conv.provider_user_id;
    }
    return conv.client?.name || 'Client #' + conv.client_user_id;
  }

  private scrollToBottom(): void {
    try {
      const el = this.messagesContainer?.nativeElement;
      if (el) {
        el.scrollTop = el.scrollHeight;
      }
    } catch (_) {}
  }
}
