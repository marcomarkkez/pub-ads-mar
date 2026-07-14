import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { NotificationService } from '../../core/services/notification.service';
import { Ticket, TicketMessage } from '../../core/models';

@Component({
  selector: 'app-support-ticket-detail',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  template: `
    <div class="page-header">
      <h1>
        <a routerLink="/support/tickets" class="back-link">&larr;</a>
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
            <div class="status-update">
              <select
                [(ngModel)]="selectedStatus"
                (ngModelChange)="updateStatus()"
                [disabled]="updatingStatus()"
              >
                <option value="open">Open</option>
                <option value="in_progress">In Progress</option>
                <option value="resolved">Resolved</option>
                <option value="closed">Closed</option>
              </select>
            </div>
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
              <span class="info-label">User</span>
              <span>
                {{ ticket()!.user?.name || 'User #' + ticket()!.user_id }}
                @if (ticket()!.user?.email) {
                  <br><span class="text-muted text-sm">{{ ticket()!.user!.email }}</span>
                }
              </span>
            </div>
            <div class="info-item">
              <span class="info-label">Created</span>
              <span>{{ ticket()!.created_at | date:'medium' }}</span>
            </div>
            @if (ticket()!.reference_type) {
              <div class="info-item">
                <span class="info-label">Reference</span>
                <span>{{ referenceLabel() }} #{{ ticket()!.reference_id }}</span>
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

          <!-- design.md §11 [F09]: Support powers — join the client↔provider chat
               (announced; relaxes masking) and FLAG money actions for Payments. -->
          @if (relatedConversationId() || referenceLabel() === 'Payment') {
            <div class="support-actions">
              <span class="info-label">Support actions</span>
              <div class="actions-row">
                @if (relatedConversationId()) {
                  <a class="btn btn-sm btn-primary" [routerLink]="['/conversations', relatedConversationId()]"
                     (click)="joinConversation()">Join conversation</a>
                }
                @if (referenceLabel() === 'Payment' && ticket()!.reference_id) {
                  <button class="btn btn-sm" (click)="flagPayoutHold()" [disabled]="acting()">Flag payout-hold</button>
                  <button class="btn btn-sm" (click)="flagRefund()" [disabled]="acting()">Flag refund</button>
                }
              </div>
            </div>
          }
        </div>

        <!-- Messages Section -->
        <div class="card messages-section">
          <div class="card-header">
            <h2>Messages</h2>
          </div>

          @if (ticket()!.messages && ticket()!.messages!.length > 0) {
            <div class="message-list">
              @for (msg of ticket()!.messages!; track msg.id) {
                <div class="reply-item" [class.support-reply]="msg.user?.role === 'support'">
                  <div class="reply-header">
                    <strong>{{ msg.user?.name || 'User' }}</strong>
                    <span class="reply-role badge" [ngClass]="msg.user?.role === 'support' ? 'badge-in_progress' : 'badge-open'">
                      {{ msg.user?.role || 'user' }}
                    </span>
                    <span class="reply-time">{{ msg.created_at | date:'short' }}</span>
                  </div>
                  <div class="reply-body">{{ msg.body }}</div>
                </div>
              }
            </div>
          } @else {
            <div class="empty-state">
              <p>No messages yet.</p>
            </div>
          }

          <!-- Reply Form -->
          <div class="reply-form">
            <div class="form-group">
              <label for="replyBody">Reply as Support</label>
              <textarea
                id="replyBody"
                [(ngModel)]="replyBody"
                placeholder="Write your support reply..."
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
    .status-update select {
      padding: 6px 10px;
      border: 1px solid var(--border);
      border-radius: 6px;
      font-size: 13px;
      background: var(--surface);
      color: var(--text);
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
    .text-muted { color: var(--text-muted); }
    .text-sm { font-size: 12px; }
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
    .reply-item.support-reply {
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
      font-size: 10px;
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
    .support-actions {
      padding-top: 16px;
      margin-top: 16px;
      border-top: 1px solid var(--border);
    }
    .actions-row {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 8px;
    }
  `],
})
export class SupportTicketDetailComponent implements OnInit {
  private readonly api = environment.apiUrl;
  private ticketId = 0;

  ticket = signal<Ticket | null>(null);
  loading = signal(false);
  error = signal('');
  sending = signal(false);
  updatingStatus = signal(false);
  relatedConversationId = signal<number | null>(null);
  acting = signal(false);
  replyBody = '';
  selectedStatus = '';

  constructor(
    private http: HttpClient,
    private route: ActivatedRoute,
    private notify: NotificationService,
  ) {}

  ngOnInit(): void {
    this.ticketId = Number(this.route.snapshot.paramMap.get('id'));
    this.loadTicket();
  }

  /**
   * The API exposes reference_type as the raw morph class
   * (e.g. "App\\Models\\Space"). Render a clean, human label.
   */
  referenceLabel(): string {
    const raw = this.ticket()?.reference_type;
    if (!raw) return '';
    const base = raw.split('\\').pop() || raw;
    return base.charAt(0).toUpperCase() + base.slice(1);
  }

  loadTicket(): void {
    this.loading.set(true);
    this.error.set('');

    this.http.get<Ticket>(`${this.api}/support/tickets/${this.ticketId}`).subscribe({
      next: (res) => {
        this.ticket.set(res);
        this.selectedStatus = res.status;
        this.relatedConversationId.set((res as { related_conversation_id?: number }).related_conversation_id ?? null);
        this.loading.set(false);
      },
      error: (err) => {
        this.loading.set(false);
        this.error.set(err.error?.message || 'Failed to load ticket.');
        this.notify.error('Failed to load ticket.');
      },
    });
  }

  updateStatus(): void {
    if (!this.selectedStatus || this.selectedStatus === this.ticket()?.status) return;

    this.updatingStatus.set(true);

    this.http.put<Ticket>(
      `${this.api}/support/tickets/${this.ticketId}`,
      { status: this.selectedStatus }
    ).subscribe({
      next: (res) => {
        this.ticket.set(res);
        this.updatingStatus.set(false);
        this.notify.success(`Ticket status updated to "${this.selectedStatus}".`);
      },
      error: (err) => {
        this.updatingStatus.set(false);
        // Revert to current status
        this.selectedStatus = this.ticket()?.status || 'open';
        this.notify.error(err.error?.message || 'Failed to update status.');
      },
    });
  }

  // design.md §11 [F09]: Support joins the client↔provider chat (announced;
  // relaxes PII masking). The routerLink navigates; this fires the join.
  joinConversation(): void {
    const cid = this.relatedConversationId();
    if (!cid) return;
    this.http.post(`${this.api}/support/conversations/${cid}/join`, {}).subscribe({
      next: () => this.notify.success('Joined the conversation — masking relaxed.'),
      error: (err) => this.notify.error(err.error?.message || 'Failed to join conversation.'),
    });
  }

  // Support FLAGS money actions; Payments decides + executes.
  flagPayoutHold(): void {
    this.flagPayment('flag-payout-hold', 'Payout-hold flagged for Payments.');
  }

  flagRefund(): void {
    this.flagPayment('flag-refund', 'Refund flagged for Payments.');
  }

  private flagPayment(action: string, msg: string): void {
    const pid = this.ticket()?.reference_id;
    if (!pid) return;
    this.acting.set(true);
    this.http.post(`${this.api}/support/payments/${pid}/${action}`, {}).subscribe({
      next: () => { this.acting.set(false); this.notify.success(msg); },
      error: (err) => { this.acting.set(false); this.notify.error(err.error?.message || 'Flag failed.'); },
    });
  }

  sendReply(): void {
    const body = this.replyBody.trim();
    if (!body) return;

    this.sending.set(true);

    this.http.post<TicketMessage>(
      `${this.api}/support/tickets/${this.ticketId}/reply`,
      { body }
    ).subscribe({
      next: (res) => {
        this.ticket.update(t => {
          if (!t) return t;
          return {
            ...t,
            messages: [...(t.messages || []), res],
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
