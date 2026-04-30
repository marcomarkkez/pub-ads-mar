import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';
import { Campaign } from '../../../core/models';

@Component({
  selector: 'app-campaign-list',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="page-header">
      <h1>My Campaigns</h1>
      <a routerLink="/client/campaigns/new" class="btn btn-sm" style="background:var(--primary);color:#fff;border-color:var(--primary);">
        + New Campaign
      </a>
    </div>

    @if (loading()) {
      <div class="loading-container"><span class="spinner"></span></div>
    } @else if (campaigns().length === 0) {
      <div class="empty-state">
        <div class="empty-icon">📢</div>
        <p>No campaigns yet. Create your first campaign to get started.</p>
      </div>
    } @else {
      <div class="card">
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Name</th>
                <th>Status</th>
                <th>Budget</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Adsets</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @for (campaign of campaigns(); track campaign.id) {
                <tr>
                  <td>
                    <a [routerLink]="['/client/campaigns', campaign.id]" style="color:var(--primary);text-decoration:none;font-weight:600;">
                      {{ campaign.name }}
                    </a>
                  </td>
                  <td>
                    <span class="badge" [class]="'badge badge-' + campaign.status">
                      {{ campaign.status }}
                    </span>
                  </td>
                  <td>{{ campaign.budget !== null ? ('$' + campaign.budget) : '-' }}</td>
                  <td>{{ campaign.start_date || '-' }}</td>
                  <td>{{ campaign.end_date || '-' }}</td>
                  <td>{{ campaign.adsets_count ?? 0 }}</td>
                  <td>
                    <div style="display:flex;gap:6px;">
                      <a [routerLink]="['/client/campaigns', campaign.id]" class="btn btn-sm">View</a>
                      <button class="btn btn-sm btn-danger" (click)="deleteCampaign(campaign)">Delete</button>
                    </div>
                  </td>
                </tr>
              }
            </tbody>
          </table>
        </div>

        @if (lastPage() > 1) {
          <div class="pagination">
            <button [disabled]="currentPage() <= 1" (click)="loadPage(currentPage() - 1)">Prev</button>
            @for (p of pages(); track p) {
              <button [class.active]="p === currentPage()" (click)="loadPage(p)">{{ p }}</button>
            }
            <button [disabled]="currentPage() >= lastPage()" (click)="loadPage(currentPage() + 1)">Next</button>
          </div>
        }
      </div>
    }
  `,
})
export class CampaignListComponent implements OnInit {
  private readonly api = environment.apiUrl;

  campaigns = signal<Campaign[]>([]);
  loading = signal(true);
  currentPage = signal(1);
  lastPage = signal(1);
  pages = signal<number[]>([]);

  constructor(
    private http: HttpClient,
    private notify: NotificationService,
  ) {}

  ngOnInit(): void {
    this.loadPage(1);
  }

  loadPage(_page?: number): void {
    this.loading.set(true);
    this.http.get<Campaign[]>(`${this.api}/client/campaigns`).subscribe({
      next: (res) => {
        this.campaigns.set(res);
        this.loading.set(false);
      },
      error: (err) => {
        this.notify.error(err.error?.message || 'Failed to load campaigns.');
        this.loading.set(false);
      },
    });
  }

  deleteCampaign(campaign: Campaign): void {
    if (!confirm(`Delete campaign "${campaign.name}"? This cannot be undone.`)) return;

    this.http.delete(`${this.api}/client/campaigns/${campaign.id}`).subscribe({
      next: () => {
        this.notify.success('Campaign deleted.');
        this.campaigns.update(list => list.filter(c => c.id !== campaign.id));
      },
      error: (err) => {
        this.notify.error(err.error?.message || 'Failed to delete campaign.');
      },
    });
  }
}
