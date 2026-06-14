import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';
import { Campaign, Adset, Ad, Collaborator } from '../../../core/models';

@Component({
  selector: 'app-campaign-detail',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  template: `
    <div class="page-header">
      <h1>Campaign Details</h1>
      <a routerLink="/client/campaigns" class="btn btn-sm">Back to Campaigns</a>
    </div>

    @if (loading()) {
      <div class="loading-container"><span class="spinner"></span></div>
    } @else if (campaign()) {
      <!-- Campaign Info Card -->
      <div class="card" style="margin-bottom:24px;">
        <div class="card-header">
          <h2>{{ campaign()!.name }}</h2>
          <span class="badge" [class]="'badge badge-' + campaign()!.status">{{ campaign()!.status }}</span>
        </div>
        @if (campaign()!.description) {
          <p style="color:var(--text-muted);margin-bottom:12px;">{{ campaign()!.description }}</p>
        }
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px;font-size:13px;">
          <div>
            <span style="color:var(--text-muted);display:block;">Budget</span>
            <strong>{{ campaign()!.budget !== null ? ('$' + campaign()!.budget) : 'Not set' }}</strong>
          </div>
          <div>
            <span style="color:var(--text-muted);display:block;">Start Date</span>
            <strong>{{ campaign()!.start_date || 'Not set' }}</strong>
          </div>
          <div>
            <span style="color:var(--text-muted);display:block;">End Date</span>
            <strong>{{ campaign()!.end_date || 'Not set' }}</strong>
          </div>
          <div>
            <span style="color:var(--text-muted);display:block;">Created</span>
            <strong>{{ campaign()!.created_at | date:'mediumDate' }}</strong>
          </div>
        </div>
      </div>

      <!-- Orphan Backlog (C03): spaces added from search, not yet in an adset -->
      @if (orphans().length > 0) {
        <div class="card" style="margin-bottom:24px;border:2px solid var(--warning, #f59e0b);">
          <div class="card-header">
            <h2>Unassigned spaces ({{ orphans().length }})</h2>
          </div>
          <p style="color:var(--text-muted);font-size:13px;margin-bottom:12px;">
            These spaces were added to the campaign but are not in an adset yet, so they can't be booked.
            Move them into a new adset to continue.
          </p>
          <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;">
            @for (orphan of orphans(); track orphan.id) {
              <span class="badge" style="background:var(--hover);padding:6px 10px;">{{ orphan.name }}</span>
            }
          </div>
          <button class="btn btn-sm" style="background:var(--primary);color:#fff;border-color:var(--primary);"
            (click)="moveOrphansToAdset()" [disabled]="movingOrphans()">
            @if (movingOrphans()) {
              <span class="spinner"></span>
            } @else {
              Move all to a new adset
            }
          </button>
        </div>
      }

      <!-- Adsets Section -->
      <div class="card" style="margin-bottom:24px;">
        <div class="card-header">
          <h2>Adsets</h2>
        </div>

        <!-- Add Adset Inline Form -->
        <div style="display:flex;gap:8px;margin-bottom:20px;">
          <input
            type="text"
            [(ngModel)]="newAdsetName"
            name="newAdsetName"
            placeholder="New adset name..."
            style="flex:1;padding:8px 12px;border:1px solid var(--border);border-radius:6px;font-size:14px;"
          />
          <button class="btn btn-sm" style="background:var(--primary);color:#fff;border-color:var(--primary);"
            (click)="addAdset()" [disabled]="addingAdset()">
            @if (addingAdset()) {
              <span class="spinner"></span>
            } @else {
              Add Adset
            }
          </button>
        </div>

        @if (adsets().length === 0) {
          <div class="empty-state" style="padding:24px;">
            <p>No adsets yet. Add one above to organize your ads.</p>
          </div>
        }

        @for (adset of adsets(); track adset.id) {
          <div style="border:1px solid var(--border);border-radius:8px;padding:16px;margin-bottom:16px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
              <div>
                <strong style="font-size:15px;">{{ adset.name }}</strong>
                <span class="badge" [class]="'badge badge-' + adset.status" style="margin-left:8px;">{{ adset.status }}</span>
                @if (adset.location_name) {
                  <span style="color:var(--text-muted);font-size:12px;margin-left:8px;">{{ adset.location_name }}</span>
                }
              </div>
              <button class="btn btn-sm btn-danger" (click)="deleteAdset(adset)">Delete</button>
            </div>

            <!-- Ads list -->
            @if (adset.ads && adset.ads.length > 0) {
              <div style="margin-bottom:12px;">
                @for (ad of adset.ads; track ad.id) {
                  <div style="display:flex;align-items:center;gap:12px;padding:8px;border:1px solid var(--border);border-radius:6px;margin-bottom:6px;">
                    @if (ad.file_url && (ad.media_type === 'image' || ad.media_type === 'gif')) {
                      <img [src]="ad.file_url" [alt]="ad.name" style="width:48px;height:48px;object-fit:cover;border-radius:4px;" />
                    } @else {
                      <div style="width:48px;height:48px;background:var(--hover);border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:20px;">
                        @switch (ad.media_type) {
                          @case ('video') { &#9654; }
                          @case ('sound') { &#9835; }
                          @default { &#128196; }
                        }
                      </div>
                    }
                    <div style="flex:1;">
                      <div style="font-weight:600;font-size:13px;">{{ ad.name }}</div>
                      <div style="font-size:12px;color:var(--text-muted);">{{ ad.media_type }} {{ ad.file_name ? '- ' + ad.file_name : '' }}</div>
                    </div>
                    <button class="btn btn-sm btn-danger" (click)="deleteAd(adset, ad)">Delete</button>
                  </div>
                }
              </div>
            }

            <!-- Add Ad Toggle -->
            @if (showAdFormFor() === adset.id) {
              <div style="border:1px solid var(--border);border-radius:6px;padding:12px;background:var(--hover);">
                <div class="form-group">
                  <label>Ad Name *</label>
                  <input type="text" [(ngModel)]="newAdName" name="newAdName" placeholder="Ad name" />
                </div>
                <div class="form-group">
                  <label>Media Type *</label>
                  <select [(ngModel)]="newAdMediaType" name="newAdMediaType">
                    <option value="image">Image</option>
                    <option value="video">Video</option>
                    <option value="sound">Sound</option>
                    <option value="gif">GIF</option>
                  </select>
                </div>
                <div class="form-group">
                  <label>File *</label>
                  <input type="file" (change)="onFileSelected($event)" style="font-size:13px;" />
                </div>
                <div style="display:flex;gap:8px;">
                  <button class="btn btn-sm" style="background:var(--primary);color:#fff;border-color:var(--primary);"
                    (click)="addAd(adset)" [disabled]="addingAd()">
                    @if (addingAd()) {
                      <span class="spinner"></span>
                    } @else {
                      Upload Ad
                    }
                  </button>
                  <button class="btn btn-sm" (click)="showAdFormFor.set(null)">Cancel</button>
                </div>
              </div>
            } @else {
              <button class="btn btn-sm" (click)="openAdForm(adset.id)">+ Add Ad</button>
            }
          </div>
        }
      </div>

      <!-- Collaborators Section -->
      <div class="card">
        <div class="card-header">
          <h2>Collaborators</h2>
        </div>

        <!-- Add Collaborator -->
        <div style="display:flex;gap:8px;margin-bottom:16px;">
          <input
            type="email"
            [(ngModel)]="newCollabEmail"
            name="newCollabEmail"
            placeholder="Collaborator email..."
            style="flex:1;padding:8px 12px;border:1px solid var(--border);border-radius:6px;font-size:14px;"
          />
          <select [(ngModel)]="newCollabRole" name="newCollabRole"
            style="padding:8px 12px;border:1px solid var(--border);border-radius:6px;font-size:14px;">
            <option value="installator">Installator (proofs only)</option>
            <option value="publicist">Publicist (campaigns/ads, no money)</option>
            <option value="manager">Manager (everything incl. money)</option>
          </select>
          <button class="btn btn-sm" style="background:var(--primary);color:#fff;border-color:var(--primary);"
            (click)="addCollaborator()" [disabled]="addingCollab()">
            @if (addingCollab()) {
              <span class="spinner"></span>
            } @else {
              Add
            }
          </button>
        </div>

        @if (collaborators().length === 0) {
          <p style="color:var(--text-muted);font-size:13px;">No collaborators added yet.</p>
        } @else {
          @for (collab of collaborators(); track collab.id) {
            <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);">
              <div>
                <span style="font-weight:600;">{{ collab.user?.name || collab.email }}</span>
                <span style="color:var(--text-muted);font-size:12px;margin-left:8px;">{{ collab.email }}</span>
              </div>
              <button class="btn btn-sm btn-danger" (click)="removeCollaborator(collab)">Remove</button>
            </div>
          }
        }
      </div>
    }
  `,
})
export class CampaignDetailComponent implements OnInit {
  private readonly api = environment.apiUrl;
  private campaignId!: number;

  campaign = signal<Campaign | null>(null);
  adsets = signal<Adset[]>([]);
  collaborators = signal<Collaborator[]>([]);
  loading = signal(true);

  // Adset form
  newAdsetName = '';
  addingAdset = signal(false);

  // Ad form
  showAdFormFor = signal<number | null>(null);
  newAdName = '';
  newAdMediaType: 'image' | 'video' | 'sound' | 'gif' = 'image';
  selectedFile: File | null = null;
  addingAd = signal(false);

  // Collaborator form
  newCollabEmail = '';
  newCollabRole: 'installator' | 'publicist' | 'manager' = 'publicist';
  addingCollab = signal(false);

  // Orphan ads (backlog spaces added from search, not yet in an adset) — C03
  orphans = signal<Ad[]>([]);
  movingOrphans = signal(false);

  constructor(
    private http: HttpClient,
    private route: ActivatedRoute,
    private notify: NotificationService,
  ) {}

  ngOnInit(): void {
    this.campaignId = Number(this.route.snapshot.paramMap.get('id'));
    this.loadCampaign();
    this.loadAdsets();
    this.loadCollaborators();
    this.loadOrphans();
  }

  /* ── Data Loading ── */

  loadCampaign(): void {
    this.http.get<Campaign>(`${this.api}/client/campaigns/${this.campaignId}`).subscribe({
      next: (res) => {
        this.campaign.set(res);
        this.loading.set(false);
      },
      error: (err) => {
        this.notify.error(err.error?.message || 'Failed to load campaign.');
        this.loading.set(false);
      },
    });
  }

  loadAdsets(): void {
    this.http.get<Adset[]>(`${this.api}/client/campaigns/${this.campaignId}/adsets`).subscribe({
      next: (res) => this.adsets.set(res),
      error: () => {},
    });
  }

  loadCollaborators(): void {
    this.http.get<Collaborator[]>(`${this.api}/client/campaigns/${this.campaignId}/collaborators`).subscribe({
      next: (res) => this.collaborators.set(res),
      error: () => {},
    });
  }

  loadOrphans(): void {
    this.http.get<Ad[]>(`${this.api}/client/campaigns/${this.campaignId}/orphans`).subscribe({
      next: (res) => this.orphans.set(res),
      error: () => {},
    });
  }

  /* ── Orphan backlog (C03) ── */

  moveOrphansToAdset(): void {
    const ad_ids = this.orphans().map(a => a.id);
    if (ad_ids.length === 0) return;
    this.movingOrphans.set(true);
    this.http.post(`${this.api}/client/campaigns/${this.campaignId}/adsets/move`, { ad_ids }).subscribe({
      next: () => {
        this.movingOrphans.set(false);
        this.notify.success('Spaces moved into a new adset — they are now bookable.');
        this.loadAdsets();
        this.loadOrphans();
      },
      error: (err) => {
        this.movingOrphans.set(false);
        this.notify.error(err.error?.message || 'Failed to move spaces into an adset.');
      },
    });
  }

  /* ── Adsets ── */

  addAdset(): void {
    if (!this.newAdsetName.trim()) {
      this.notify.warning('Please enter an adset name.');
      return;
    }
    this.addingAdset.set(true);
    this.http.post<Adset>(`${this.api}/client/campaigns/${this.campaignId}/adsets`, {
      name: this.newAdsetName.trim(),
    }).subscribe({
      next: (res) => {
        this.adsets.update(list => [...list, res]);
        this.newAdsetName = '';
        this.addingAdset.set(false);
        this.notify.success('Adset added.');
      },
      error: (err) => {
        this.addingAdset.set(false);
        this.notify.error(err.error?.message || 'Failed to add adset.');
      },
    });
  }

  deleteAdset(adset: Adset): void {
    if (!confirm(`Delete adset "${adset.name}" and all its ads?`)) return;
    this.http.delete(`${this.api}/client/campaigns/${this.campaignId}/adsets/${adset.id}`).subscribe({
      next: () => {
        this.adsets.update(list => list.filter(a => a.id !== adset.id));
        this.notify.success('Adset deleted.');
      },
      error: (err) => this.notify.error(err.error?.message || 'Failed to delete adset.'),
    });
  }

  /* ── Ads ── */

  openAdForm(adsetId: number): void {
    this.showAdFormFor.set(adsetId);
    this.newAdName = '';
    this.newAdMediaType = 'image';
    this.selectedFile = null;
  }

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.selectedFile = input.files?.[0] ?? null;
  }

  addAd(adset: Adset): void {
    if (!this.newAdName.trim()) {
      this.notify.warning('Please enter an ad name.');
      return;
    }
    if (!this.selectedFile) {
      this.notify.warning('Please select a file.');
      return;
    }

    this.addingAd.set(true);
    const formData = new FormData();
    formData.append('name', this.newAdName.trim());
    formData.append('media_type', this.newAdMediaType);
    formData.append('file', this.selectedFile);

    this.http.post<Ad>(
      `${this.api}/client/campaigns/${this.campaignId}/adsets/${adset.id}/ads`,
      formData
    ).subscribe({
      next: (res) => {
        this.adsets.update(list =>
          list.map(a => a.id === adset.id
            ? { ...a, ads: [...(a.ads || []), res] }
            : a
          )
        );
        this.showAdFormFor.set(null);
        this.addingAd.set(false);
        this.notify.success('Ad uploaded.');
      },
      error: (err) => {
        this.addingAd.set(false);
        this.notify.error(err.error?.message || 'Failed to upload ad.');
      },
    });
  }

  deleteAd(adset: Adset, ad: Ad): void {
    if (!confirm(`Delete ad "${ad.name}"?`)) return;
    this.http.delete(
      `${this.api}/client/campaigns/${this.campaignId}/adsets/${adset.id}/ads/${ad.id}`
    ).subscribe({
      next: () => {
        this.adsets.update(list =>
          list.map(a => a.id === adset.id
            ? { ...a, ads: (a.ads || []).filter(d => d.id !== ad.id) }
            : a
          )
        );
        this.notify.success('Ad deleted.');
      },
      error: (err) => this.notify.error(err.error?.message || 'Failed to delete ad.'),
    });
  }

  /* ── Collaborators ── */

  addCollaborator(): void {
    if (!this.newCollabEmail.trim()) {
      this.notify.warning('Please enter an email address.');
      return;
    }
    this.addingCollab.set(true);
    this.http.post<Collaborator>(`${this.api}/client/campaigns/${this.campaignId}/collaborators`, {
      email: this.newCollabEmail.trim(),
      role: this.newCollabRole,
    }).subscribe({
      next: (res) => {
        this.collaborators.update(list => [...list, res]);
        this.newCollabEmail = '';
        this.addingCollab.set(false);
        this.notify.success('Collaborator added.');
      },
      error: (err) => {
        this.addingCollab.set(false);
        this.notify.error(err.error?.message || 'Failed to add collaborator.');
      },
    });
  }

  removeCollaborator(collab: Collaborator): void {
    if (!confirm(`Remove collaborator "${collab.email}"?`)) return;
    this.http.delete(`${this.api}/client/campaigns/${this.campaignId}/collaborators/${collab.id}`).subscribe({
      next: () => {
        this.collaborators.update(list => list.filter(c => c.id !== collab.id));
        this.notify.success('Collaborator removed.');
      },
      error: (err) => this.notify.error(err.error?.message || 'Failed to remove collaborator.'),
    });
  }
}
