import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';
import { AuthService } from '../../../core/services/auth.service';
import { Ticket, TicketMessage } from '../../../core/models';

@Component({
  selector: 'app-ticket-detail',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  template: `
    <div class="page-header">
      <h1>
        <a routerLink="/tickets" class="back-link">&larr;</a>
        Ticket Details
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

    @if (!loading() && ticket()) {
      <div class="ticket-layout">
        <!-- Ticket Info Card -->
        <div class="card ticket-info">
          <div class="card-header">
            <h2>{{ ticket()!.subject }}</h2>
          </div>
          <div class="info-grid">
            <div class="info-item">
              <span class="info-label">Status</span>
              <span class="badge" [ngClass]="'badge-' + ticket()!.status">
                {{ ticket()!.status }}
              </span>
            </div>
            <div class="info-item">
              <span class="info-label">Priority</span>
              <span class="badge" [ngClass]="'badge-' + ticket()!.priority">
                {{ ticket()!.priority }}
              </span>
            </div>
            <div class="info-item">
              <span class="info-label">Created</span>
              <span>{{ ticket()!.created_at | date:'medium' }}</span>
            </div>
            @if (ticket()!.reference_type) {
              <div class="info-item">
                <span class="info-label">Reference</span>
                <span>{{ ticket()!.reference_type }} #{{ ticket()!.reference_id }}</span>
              </div>
            }
            @if (ticket()!.assigned_to) {
              <div class="info-item">
                <span class="info-label">Assigned To</span>
                <span>{{ ticket()!.assigned_to!.name }}</span>
              </div>
            }
          </div>
          <div class="description-section">
            <span class="info-label">Description</span>
            <p class="description-text">{{ ticket()!.description }}</p>
          </div>
        </div>

        <!-- Messages Section -->
        <div class="card messages-section">
          <div class="card-header">
            <h2>Replies</h2>
          </div>

          @if (ticket()!.messages && ticket()!.messages!.length > 0) {
            <div class="message-list">
              @for (msg of ticket()!.messages!; track msg.id) {
                <div class="reply-item" [class.mine]="msg.user_id === currentUserId">
                  <div class="reply-header">
                    <strong>{{ msg.user?.name || 'User' }}</strong>
                    <span class="reply-role">{{ msg.user?.role || '' }}</span>
                    <span class="reply-time">{{ msg.created_at | date:'short' }}</span>
                  </div>
                  <div class="reply-body">{{ msg.body }}</div>
                </div>
              }
            </div>
          } @else {
            <div class="empty-state">
              <p>No replies yet.</p>
            </div>
          }

          <!-- Reply Form -->
          <div class="reply-form">
            <div class="form-group">
              <label for="replyBody">Your Reply</label>
              <textarea
                id="replyBody"
                [(ngModel)]="replyBody"
                placeholder="Write your reply..."
                rows="3"
              ></textarea>
            </div>
            <div class="form-actions">
              <button
                class="btn btn-primary send-btn"
                [disabled]="sending() || !replyBody.trim()"
                (click)="sendReply()"
              >
                @if (sending()) {
                  <span class="spinner"></span> Sending...
                } @else {
                  Send Reply
                }
              </button>
            </div>
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
    .ticket-layout {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }
    .info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 16px;
      margin-bottom: 16px;
    }
    .info-item {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .info-label {
      font-size: 12px;
      font-weight: 600;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }
    .description-section {
      padding-top: 16px;
      border-top: 1px solid var(--border);
    }
    .description-text {
      margin-top: 6px;
      line-height: 1.6;
      white-space: pre-wrap;
    }
    .message-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
      margin-bottom: 20px;
    }
    .reply-item {
      padding: 12px 16px;
      border-radius: 8px;
      background: var(--hover);
      border: 1px solid var(--border);
    }
    .reply-item.mine {
      background: var(--primary-bg);
      border-color: var(--primary-light);
    }
    .reply-header {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 6px;
      font-size: 13px;
    }
    .reply-role {
      font-size: 11px;
      color: var(--text-muted);
      background: var(--surface);
      padding: 1px 6px;
      border-radius: 4px;
    }
    .reply-time {
      margin-left: auto;
      font-size: 11px;
      color: var(--text-muted);
    }
    .reply-body {
      font-size: 14px;
      line-height: 1.5;
      white-space: pre-wrap;
    }
    .reply-form {
      border-top: 1px solid var(--border);
      padding-top: 16px;
    }
    .form-actions {
      display: flex;
      justify-content: flex-end;
    }
    .send-btn {
      width: auto;
    }
    .badge-low { background: #dbeafe; color: #1e40af; }
    .badge-medium { background: #fef3c7; color: #92400e; }
    .badge-high { background: #fee2e2; color: #991b1b; }
  `],
})
export class TicketDetailComponent implements OnInit {
  private readonly api = environment.apiUrl;
  private ticketId = 0;

  ticket = signal<Ticket | null>(null);
  loading = signal(false);
  error = signal('');
  sending = signal(false);
  replyBody = '';
  currentUserId: number | null = null;

  constructor(
    private http: HttpClient,
    private route: ActivatedRoute,
    private notify: NotificationService,
    private auth: AuthService,
  ) {}

  ngOnInit(): void {
    this.currentUserId = this.auth.user()?.id ?? null;
    this.ticketId = Number(this.route.snapshot.paramMap.get('id'));
    this.loadTicket();
  }

  loadTicket(): void {
    this.loading.set(true);
    this.error.set('');

    this.http.get<Ticket>(`${this.api}/tickets/${this.ticketId}`).subscribe({
      next: (res) => {
        this.ticket.set(res);
        this.loading.set(false);
      },
      error: (err) => {
        this.loading.set(false);
        this.error.set(err.error?.message || 'Failed to load ticket.');
        this.notify.error('Failed to load ticket.');
      },
    });
  }

  sendReply(): void {
    const body = this.replyBody.trim();
    if (!body) return;

    this.sending.set(true);

    this.http.post<{ data: TicketMessage }>(
      `${this.api}/tickets/${this.ticketId}/reply`,
      { body }
    ).subscribe({
      next: (res) => {
        this.ticket.update(t => {
          if (!t) return t;
          return {
            ...t,
            messages: [...(t.messages || []), res.data],
          };
        });
        this.replyBody = '';
        this.sending.set(false);
        this.notify.success('Reply sent.');
      },
      error: (err) => {
        this.sending.set(false);
        this.notify.error(err.error?.message || 'Failed to send reply.');
      },
    });
  }
}
