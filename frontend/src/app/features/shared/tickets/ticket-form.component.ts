import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';
import { Ticket } from '../../../core/models';

@Component({
  selector: 'app-ticket-form',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  template: `
    <div class="page-header">
      <h1>
        <a routerLink="/tickets" class="back-link">&larr;</a>
        New Ticket
      </h1>
    </div>

    @if (error()) {
      <div class="alert alert-error">{{ error() }}</div>
    }

    <div class="card">
      <form (ngSubmit)="onSubmit()">
        <div class="form-group">
          <label for="subject">Subject</label>
          <input
            id="subject"
            type="text"
            [(ngModel)]="subject"
            name="subject"
            placeholder="Brief description of your issue"
            required
          />
        </div>

        <div class="form-group">
          <label for="description">Description</label>
          <textarea
            id="description"
            [(ngModel)]="description"
            name="description"
            placeholder="Describe your issue in detail..."
            rows="5"
            required
          ></textarea>
        </div>

        <div class="form-group">
          <label for="priority">Priority</label>
          <select id="priority" [(ngModel)]="priority" name="priority">
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
          </select>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="reference_type">Reference Type (optional)</label>
            <select id="reference_type" [(ngModel)]="reference_type" name="reference_type" [disabled]="lockReference()">
              <option value="">None</option>
              <option value="campaign">Campaign</option>
              <option value="adset">Adset</option>
              <option value="ad">Ad</option>
              <option value="space">Space</option>
              <option value="booking">Booking</option>
            </select>
          </div>

          <div class="form-group">
            <label for="reference_id">Reference ID (optional)</label>
            <input
              id="reference_id"
              type="number"
              [(ngModel)]="reference_id"
              name="reference_id"
              placeholder="ID"
              min="1"
              [disabled]="lockReference()"
            />
          </div>
        </div>

        <div class="form-actions">
          <a routerLink="/tickets" class="btn">Cancel</a>
          <button type="submit" class="btn btn-primary submit-btn" [disabled]="submitting()">
            @if (submitting()) {
              <span class="spinner"></span> Submitting...
            } @else {
              Submit Ticket
            }
          </button>
        </div>
      </form>
    </div>
  `,
  styles: [`
    .back-link {
      text-decoration: none;
      color: var(--primary);
      margin-right: 8px;
    }
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }
    .form-actions {
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      margin-top: 20px;
    }
    .submit-btn {
      width: auto;
    }
  `],
})
export class TicketFormComponent implements OnInit {
  private readonly api = environment.apiUrl;

  subject = '';
  description = '';
  priority: 'low' | 'medium' | 'high' = 'medium';
  reference_type = '';
  reference_id: number | null = null;

  submitting = signal(false);
  error = signal('');
  lockReference = signal(false);

  constructor(
    private http: HttpClient,
    private router: Router,
    private route: ActivatedRoute,
    private notify: NotificationService,
  ) {}

  ngOnInit(): void {
    // Prefill + lock the reference when arriving from a per-item Support button.
    // Accept both key styles (ref_type/ref_id and type/id) for robustness.
    const qp = this.route.snapshot.queryParamMap;
    const refType = qp.get('ref_type') ?? qp.get('type');
    const refId = qp.get('ref_id') ?? qp.get('id');
    const subject = qp.get('subject');

    if (subject) {
      this.subject = subject;
    }
    if (refType) {
      this.reference_type = refType;
    }
    if (refId !== null && refId !== '') {
      this.reference_id = Number(refId);
    }
    // Lock the type/id controls only when a valid reference came in via query params.
    if (refType && refId !== null && refId !== '') {
      this.lockReference.set(true);
    }
  }

  onSubmit(): void {
    if (!this.subject.trim() || !this.description.trim()) {
      this.error.set('Subject and description are required.');
      return;
    }

    this.submitting.set(true);
    this.error.set('');

    const payload: Record<string, unknown> = {
      subject: this.subject.trim(),
      description: this.description.trim(),
      priority: this.priority,
    };

    if (this.reference_type && ['ad', 'adset', 'campaign', 'space'].includes(this.reference_type)) {
      payload['ticketable_type'] = this.reference_type;
      payload['ticketable_id'] = this.reference_id;
    }

    this.http.post<{ data: Ticket }>(`${this.api}/tickets`, payload).subscribe({
      next: () => {
        this.notify.success('Ticket submitted successfully.');
        this.router.navigate(['/tickets']);
      },
      error: (err) => {
        this.submitting.set(false);
        this.error.set(err.error?.message || 'Failed to submit ticket.');
        this.notify.error('Failed to submit ticket.');
      },
    });
  }
}
