import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';
import { AuthService } from '../../../core/services/auth.service';
import { Conversation } from '../../../core/models';

@Component({
  selector: 'app-conversation-list',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="page-header">
      <h1>Conversations</h1>
    </div>

    @if (loading()) {
      <div class="loading-container">
        <span class="spinner"></span>
      </div>
    }

    @if (error()) {
      <div class="alert alert-error">{{ error() }}</div>
    }

    @if (!loading() && conversations().length === 0 && !error()) {
      <div class="empty-state">
        <div class="empty-icon">💬</div>
        <p>No conversations yet.</p>
      </div>
    }

    @if (!loading() && conversations().length > 0) {
      <div class="conversation-grid">
        @for (conv of conversations(); track conv.id) {
          <a [routerLink]="['/conversations', conv.id]" class="card conversation-card">
            <div class="conversation-header">
              <strong class="space-name">{{ conv.space?.name || 'Space #' + conv.space_id }}</strong>
              <span class="message-count badge badge-open">
                {{ conv.messages_count || 0 }} messages
              </span>
            </div>
            <div class="other-party">
              With: {{ getOtherPartyName(conv) }}
            </div>
            @if (conv.latest_message) {
              <div class="latest-message">
                <span class="message-preview">{{ conv.latest_message.body.slice(0, 80) }}{{ conv.latest_message.body.length > 80 ? '...' : '' }}</span>
                <span class="message-time">{{ conv.latest_message.created_at | date:'short' }}</span>
              </div>
            } @else {
              <div class="latest-message">
                <span class="message-preview text-muted">No messages yet</span>
              </div>
            }
          </a>
        }
      </div>
    }
  `,
  styles: [`
    .conversation-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
      gap: 16px;
    }
    .conversation-card {
      text-decoration: none;
      color: var(--text);
      cursor: pointer;
      transition: all 0.15s;
      &:hover {
        border-color: var(--primary);
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      }
    }
    .conversation-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 8px;
    }
    .space-name {
      font-size: 15px;
    }
    .message-count {
      font-size: 11px;
    }
    .other-party {
      font-size: 13px;
      color: var(--text-muted);
      margin-bottom: 10px;
    }
    .latest-message {
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
      gap: 8px;
      padding-top: 10px;
      border-top: 1px solid var(--border);
    }
    .message-preview {
      font-size: 13px;
      color: var(--text-muted);
      flex: 1;
      overflow: hidden;
      white-space: nowrap;
      text-overflow: ellipsis;
    }
    .message-time {
      font-size: 11px;
      color: var(--text-muted);
      white-space: nowrap;
    }
    .text-muted {
      color: var(--text-muted);
    }
  `],
})
export class ConversationListComponent implements OnInit {
  private readonly api = environment.apiUrl;

  conversations = signal<Conversation[]>([]);
  loading = signal(false);
  error = signal('');

  private currentUserId: number | null = null;

  constructor(
    private http: HttpClient,
    private notify: NotificationService,
    private auth: AuthService,
  ) {}

  ngOnInit(): void {
    this.currentUserId = this.auth.user()?.id ?? null;
    this.loadConversations();
  }

  loadConversations(): void {
    this.loading.set(true);
    this.error.set('');

    this.http.get<Conversation[]>(`${this.api}/conversations`).subscribe({
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

  getOtherPartyName(conv: Conversation): string {
    if (this.currentUserId === conv.client_user_id) {
      return conv.provider?.name || 'Provider #' + conv.provider_user_id;
    }
    return conv.client?.name || 'Client #' + conv.client_user_id;
  }
}
