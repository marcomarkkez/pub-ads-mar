import { Component, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';
import { Campaign } from '../../../core/models';

@Component({
  selector: 'app-campaign-form',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  template: `
    <div class="page-header">
      <h1>New Campaign</h1>
      <a routerLink="/client/campaigns" class="btn btn-sm">Back to Campaigns</a>
    </div>

    <div class="card" style="max-width:600px;">
      @if (error()) {
        <div class="alert alert-error">{{ error() }}</div>
      }

      <form (ngSubmit)="onSubmit()">
        <div class="form-group">
          <label for="name">Campaign Name *</label>
          <input id="name" type="text" [(ngModel)]="name" name="name" placeholder="Enter campaign name" required />
        </div>

        <div class="form-group">
          <label for="description">Description</label>
          <textarea id="description" [(ngModel)]="description" name="description" placeholder="Describe your campaign..." rows="3"></textarea>
        </div>

        <div class="form-group">
          <label for="status">Status</label>
          <select id="status" [(ngModel)]="status" name="status">
            <option value="draft">Draft</option>
            <option value="active">Active</option>
          </select>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div class="form-group">
            <label for="start_date">Start Date</label>
            <input id="start_date" type="date" [(ngModel)]="start_date" name="start_date" />
          </div>

          <div class="form-group">
            <label for="end_date">End Date</label>
            <input id="end_date" type="date" [(ngModel)]="end_date" name="end_date" />
          </div>
        </div>

        <div class="form-group">
          <label for="budget">Budget ($)</label>
          <input id="budget" type="number" [(ngModel)]="budget" name="budget" placeholder="0.00" min="0" step="0.01" />
        </div>

        <div style="display:flex;gap:8px;margin-top:20px;">
          <button type="submit" class="btn" style="background:var(--primary);color:#fff;border-color:var(--primary);" [disabled]="submitting()">
            @if (submitting()) {
              <span class="spinner"></span> Creating...
            } @else {
              Create Campaign
            }
          </button>
          <a routerLink="/client/campaigns" class="btn">Cancel</a>
        </div>
      </form>
    </div>
  `,
})
export class CampaignFormComponent {
  private readonly api = environment.apiUrl;

  name = '';
  description = '';
  status = 'draft';
  start_date = '';
  end_date = '';
  budget: number | null = null;

  submitting = signal(false);
  error = signal('');

  constructor(
    private http: HttpClient,
    private router: Router,
    private notify: NotificationService,
  ) {}

  onSubmit(): void {
    if (!this.name.trim()) {
      this.error.set('Campaign name is required.');
      return;
    }

    this.submitting.set(true);
    this.error.set('');

    const payload: Record<string, unknown> = {
      name: this.name.trim(),
      description: this.description.trim() || null,
      status: this.status,
      start_date: this.start_date || null,
      end_date: this.end_date || null,
      budget: this.budget,
    };

    this.http.post<{ data: Campaign }>(`${this.api}/client/campaigns`, payload).subscribe({
      next: () => {
        this.notify.success('Campaign created successfully.');
        this.router.navigate(['/client/campaigns']);
      },
      error: (err) => {
        this.submitting.set(false);
        if (err.error?.errors) {
          const messages = Object.values(err.error.errors as Record<string, string[]>).flat();
          this.error.set(messages.join(' '));
        } else {
          this.error.set(err.error?.message || 'Failed to create campaign.');
        }
      },
    });
  }
}
