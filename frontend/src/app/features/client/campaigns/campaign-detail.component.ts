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
        @if (!editingCampaign()) {
          <div class="card-header">
            <h2>{{ campaign()!.name }}</h2>
            <div style="display:flex;gap:8px;align-items:center;">
              <span class="badge" [class]="'badge badge-' + campaign()!.status">{{ campaign()!.status }}</span>
              <button class="btn btn-sm" (click)="startEditCampaign()">Edit</button>
            </div>
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
              <strong>{{ campaign()!.start_date ? (campaign()!.start_date | date:'mediumDate') : 'Not set' }}</strong>
            </div>
            <div>
              <span style="color:var(--text-muted);display:block;">End Date</span>
              <strong>{{ campaign()!.end_date ? (campaign()!.end_date | date:'mediumDate') : 'Not set' }}</strong>
            </div>
            <div>
              <span style="color:var(--text-muted);display:block;">Created</span>
              <strong>{{ campaign()!.created_at | date:'mediumDate' }}</strong>
            </div>
          </div>
        } @else {
          <div class="card-header"><h2>Edit Campaign</h2></div>
          <div class="form-group">
            <label>Name *</label>
            <input type="text" [(ngModel)]="editForm.name" name="editName" />
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea [(ngModel)]="editForm.description" name="editDescription" rows="2"></textarea>
          </div>
          <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <div class="form-group" style="flex:1;min-width:140px;">
              <label>Status</label>
              <select [(ngModel)]="editForm.status" name="editStatus">
                <option value="draft">Draft</option>
                <option value="active">Active</option>
                <option value="paused">Paused</option>
                <option value="completed">Completed</option>
              </select>
            </div>
            <div class="form-group" style="flex:1;min-width:120px;">
              <label>Budget</label>
              <input type="number" [(ngModel)]="editForm.budget" name="editBudget" min="0" step="any" />
            </div>
            <div class="form-group" style="flex:1;min-width:130px;">
              <label>Start Date</label>
              <input type="date" [(ngModel)]="editForm.start_date" name="editStart" />
            </div>
            <div class="form-group" style="flex:1;min-width:130px;">
              <label>End Date</label>
              <input type="date" [(ngModel)]="editForm.end_date" name="editEnd" />
            </div>
          </div>
          <div style="display:flex;gap:8px;">
            <button class="btn btn-sm" style="background:var(--primary);color:#fff;border-color:var(--primary);"
              (click)="saveCampaign()" [disabled]="savingCampaign()">
              @if (savingCampaign()) { <span class="spinner"></span> } @else { Save }
            </button>
            <button class="btn btn-sm" (click)="editingCampaign.set(false)">Cancel</button>
          </div>
        }
      </div>

      <!-- Orphan Backlog (C03): spaces added from search, not yet in an adset -->
      @if (orphans().length > 0) {
        <div class="card" style="margin-bottom:24px;border:2px solid var(--warning, #f59e0b);">
          <div class="card-header">
            <h2>Unassigned spaces ({{ orphans().length }})</h2>
          </div>
          <p style="color:var(--text-muted);font-size:13px;margin-bottom:12px;">
            These spaces were added to the campaign but are not in an adset yet, so they can't be booked.
            Move them into a new adset, or into one of your existing adsets, to continue.
          </p>
          <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;">
            @for (orphan of orphans(); track orphan.id) {
              <span class="badge" style="background:var(--hover);padding:6px 10px;">{{ orphan.name }}</span>
            }
          </div>
          <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <button class="btn btn-sm" style="background:var(--primary);color:#fff;border-color:var(--primary);"
              (click)="moveOrphansToAdset()" [disabled]="movingOrphans()">
              @if (movingOrphans()) {
                <span class="spinner"></span>
              } @else {
                Move all to a new adset
              }
            </button>

            @if (adsets().length > 0) {
              <span style="color:var(--text-muted);font-size:13px;">or</span>
              <select [(ngModel)]="moveTargetAdsetId" name="moveTargetAdsetId" [disabled]="movingOrphans()">
                <option [ngValue]="null">Choose an existing adset…</option>
                @for (adset of adsets(); track adset.id) {
                  <option [ngValue]="adset.id">{{ adset.name }}</option>
                }
              </select>
              <button class="btn btn-sm"
                (click)="moveOrphansToAdset(moveTargetAdsetId)"
                [disabled]="movingOrphans() || moveTargetAdsetId === null">
                Move to this adset
              </button>
            }
          </div>
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
                    @if (ad.space_id) {
                      <button class="btn btn-sm" style="background:var(--primary);color:#fff;border-color:var(--primary);"
                        (click)="openBookForm(ad)">Book</button>
                    }
                    <button class="btn btn-sm btn-danger" (click)="deleteAd(adset, ad)">Delete</button>
                  </div>
                  @if (showBookFormFor() === ad.id) {
                    <div style="border:1px solid var(--border);border-radius:6px;padding:12px;margin:0 0 6px;background:var(--hover);display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
                      <div class="form-group" style="margin-bottom:0;">
                        <label>Start Date *</label>
                        <input type="date" [(ngModel)]="bookStart" name="bookStart" />
                      </div>
                      <div class="form-group" style="margin-bottom:0;">
                        <label>End Date *</label>
                        <input type="date" [(ngModel)]="bookEnd" name="bookEnd" />
                      </div>
                      <button class="btn btn-sm" style="background:var(--primary);color:#fff;border-color:var(--primary);"
                        (click)="bookAd(ad)" [disabled]="booking()">
                        @if (booking()) { <span class="spinner"></span> } @else { Confirm booking }
                      </button>
                      <button class="btn btn-sm" (click)="showBookFormFor.set(null)">Cancel</button>
                    </div>
                  }
                }
              </div>
            }

            <!--
              design.json §21 rule 4 / §5 (ad origin) — an ad ALWAYS carries a space_id:
              a space-less ad has nobody to pay and nowhere to send the files, so it can
              never be booked. The old "name + media type + upload" form minted exactly
              that and has been removed. Ads enter an adset from the campaign backlog,
              which is filled by booking spaces from the search.
            -->
            <a class="btn btn-sm" [routerLink]="['/client/spaces']"
              [queryParams]="{ campaign: campaignId, adset: adset.id }">+ Add space to this adset</a>
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
  // Read by the template to link into the space search for this campaign.
  campaignId!: number;

  campaign = signal<Campaign | null>(null);
  adsets = signal<Adset[]>([]);
  collaborators = signal<Collaborator[]>([]);
  loading = signal(true);

  // Adset form
  newAdsetName = '';
  addingAdset = signal(false);

  // Collaborator form
  newCollabEmail = '';
  newCollabRole: 'installator' | 'publicist' | 'manager' = 'publicist';
  addingCollab = signal(false);

  // Campaign spec edit (#3)
  editingCampaign = signal(false);
  savingCampaign = signal(false);
  editForm: {
    name: string;
    description: string;
    status: 'draft' | 'active' | 'paused' | 'completed';
    budget: number | null;
    start_date: string;
    end_date: string;
  } = { name: '', description: '', status: 'draft', budget: null, start_date: '', end_date: '' };

  // Per-ad booking (#3)
  showBookFormFor = signal<number | null>(null);
  bookStart = '';
  bookEnd = '';
  booking = signal(false);

  // Orphan ads (backlog spaces added from search, not yet in an adset) — C03
  orphans = signal<Ad[]>([]);
  movingOrphans = signal(false);
  moveTargetAdsetId: number | null = null;

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

  /* ── Campaign spec edit (#3) ── */

  startEditCampaign(): void {
    const c = this.campaign();
    if (!c) return;
    this.editForm = {
      name: c.name,
      description: c.description ?? '',
      status: (c.status === 'cancelled' ? 'paused' : c.status) as 'draft' | 'active' | 'paused' | 'completed',
      budget: c.budget,
      start_date: c.start_date ?? '',
      end_date: c.end_date ?? '',
    };
    this.editingCampaign.set(true);
  }

  saveCampaign(): void {
    if (!this.editForm.name.trim()) {
      this.notify.warning('Campaign name is required.');
      return;
    }
    this.savingCampaign.set(true);
    const payload = {
      name: this.editForm.name.trim(),
      description: this.editForm.description || null,
      status: this.editForm.status,
      budget: this.editForm.budget,
      start_date: this.editForm.start_date || null,
      end_date: this.editForm.end_date || null,
    };
    this.http.put<Campaign>(`${this.api}/client/campaigns/${this.campaignId}`, payload).subscribe({
      next: (res) => {
        this.campaign.set(res);
        this.editingCampaign.set(false);
        this.savingCampaign.set(false);
        this.notify.success('Campaign updated.');
      },
      error: (err) => {
        this.savingCampaign.set(false);
        this.notify.error(err.error?.message || 'Failed to update campaign.');
      },
    });
  }

  /* ── Per-ad booking (#3) ── */

  openBookForm(ad: Ad): void {
    this.showBookFormFor.set(ad.id);
    this.bookStart = '';
    this.bookEnd = '';
  }

  bookAd(ad: Ad): void {
    if (!ad.space_id) return;
    if (!this.bookStart || !this.bookEnd) {
      this.notify.warning('Please pick a start and end date.');
      return;
    }
    this.booking.set(true);
    this.http.post(`${this.api}/client/bookings`, {
      space_id: ad.space_id,
      ad_id: ad.id,
      adset_id: ad.adset_id,
      start_date: this.bookStart,
      end_date: this.bookEnd,
    }).subscribe({
      next: () => {
        this.booking.set(false);
        this.showBookFormFor.set(null);
        this.notify.success('Booking requested.');
      },
      error: (err) => {
        this.booking.set(false);
        this.notify.error(err.error?.message || 'Failed to create booking.');
      },
    });
  }

  /* ── Orphan backlog (C03) ── */

  // design.json → Filters & backlog: move orphan spaces into a NEW adset
  // (adsetId omitted) or an EXISTING one (adsetId given).
  moveOrphansToAdset(adsetId: number | null = null): void {
    const ad_ids = this.orphans().map(a => a.id);
    if (ad_ids.length === 0) return;
    this.movingOrphans.set(true);
    const payload: { ad_ids: number[]; adset_id?: number } = { ad_ids };
    if (adsetId !== null) payload.adset_id = adsetId;
    this.http.post(`${this.api}/client/campaigns/${this.campaignId}/adsets/move`, payload).subscribe({
      next: () => {
        this.movingOrphans.set(false);
        this.moveTargetAdsetId = null;
        this.notify.success(
          adsetId !== null
            ? 'Spaces moved into the adset — they are now bookable.'
            : 'Spaces moved into a new adset — they are now bookable.',
        );
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

  // design.json §21 rule 4 / §5 — `openAdForm`/`onFileSelected`/`addAd` used to mint a
  // space-less ad from a name + media type + file. That origin is a bug: ads come
  // from booking a space or from moving one in from the campaign backlog, so the
  // form and its handlers were removed rather than patched.

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
