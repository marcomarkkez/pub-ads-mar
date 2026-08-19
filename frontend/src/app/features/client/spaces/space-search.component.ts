import { Component, OnInit, AfterViewInit, OnDestroy, ViewChild, ElementRef, NgZone, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Router } from '@angular/router';
import * as L from 'leaflet';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';
import { Space, PaginatedResponse, formatSpaceType } from '../../../core/models';

// Minimal shape for the campaign picker (avoids coupling to the full Campaign model barrel).
interface CampaignLite { id: number; name: string; }

// Custom icons using div elements — no asset files needed
const CENTER_ICON = L.divIcon({
  html: `<div style="width:16px;height:16px;border-radius:50%;background:#2563eb;border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.5);"></div>`,
  iconSize: [16, 16],
  iconAnchor: [8, 8],
  className: '',
});

const SPACE_ICON = L.divIcon({
  html: `<div style="width:14px;height:14px;border-radius:50%;background:#e11d48;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.4);"></div>`,
  iconSize: [14, 14],
  iconAnchor: [7, 7],
  className: '',
});

const SPACE_ICON_SELECTED = L.divIcon({
  html: `<div style="width:18px;height:18px;border-radius:50%;background:#f97316;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.5);"></div>`,
  iconSize: [18, 18],
  iconAnchor: [9, 9],
  className: '',
});

@Component({
  selector: 'app-space-search',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="page-header">
      <h1>Explore Spaces</h1>
    </div>

    <!-- Search Form -->
    <div class="card" style="margin-bottom:24px;">
      <form (ngSubmit)="search()" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
        <div class="form-group" style="margin-bottom:0;flex:1;min-width:130px;">
          <label>Latitude</label>
          <input type="number" [(ngModel)]="latitude" name="latitude" step="any" />
        </div>
        <div class="form-group" style="margin-bottom:0;flex:1;min-width:130px;">
          <label>Longitude</label>
          <input type="number" [(ngModel)]="longitude" name="longitude" step="any" />
        </div>
        <div class="form-group" style="margin-bottom:0;flex:1;min-width:100px;">
          <label>Radius (km)</label>
          <input type="number" [(ngModel)]="radius_km" name="radius_km" min="1" max="500" />
        </div>
        <div class="form-group" style="margin-bottom:0;flex:1;min-width:140px;">
          <label>Type</label>
          <select [(ngModel)]="type" name="type">
            <option value="">All Types</option>
            <option value="billboard">Billboard</option>
            <option value="big_screen">Big Screen</option>
            <option value="little_screen">Little Screen</option>
            <option value="radio_station">Radio Station</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:0;flex:1;min-width:130px;">
          <label>Available From</label>
          <input type="date" [(ngModel)]="date_from" name="date_from" />
        </div>
        <div class="form-group" style="margin-bottom:0;flex:1;min-width:130px;">
          <label>Available To</label>
          <input type="date" [(ngModel)]="date_to" name="date_to" />
        </div>
        <div style="margin-bottom:0;display:flex;gap:8px;align-items:center;">
          <button type="submit" class="btn" style="background:var(--primary);color:#fff;border-color:var(--primary);" [disabled]="loading()">
            @if (loading()) {
              <span class="spinner"></span>
            } @else {
              Search
            }
          </button>
          <button type="button" class="btn" (click)="toggleView()" style="white-space:nowrap;">
            {{ showMap() ? 'List View' : 'Map View' }}
          </button>
        </div>
      </form>
    </div>

    <!-- Selection basket (C03): accumulate across searches, then add to a campaign -->
    @if (selection().length > 0) {
      <div class="card" style="margin-bottom:24px;border:2px solid var(--primary);">
        <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;">
          <div style="font-weight:600;">
            {{ selection().length }} space{{ selection().length === 1 ? '' : 's' }} selected
            <span style="color:var(--text-muted);font-weight:400;font-size:13px;">
              — {{ selectionNames() }}
            </span>
          </div>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <button type="button" class="btn" style="background:var(--primary);color:#fff;border-color:var(--primary);"
              [disabled]="submitting()" (click)="addToNewCampaign()">
              @if (submitting()) { <span class="spinner"></span> } @else { Add to new campaign }
            </button>
            <button type="button" class="btn" [disabled]="submitting()" (click)="openExistingPicker()">
              Add to existing campaign
            </button>
            <button type="button" class="btn" [disabled]="submitting()" (click)="clearSelection()">Clear</button>
          </div>
        </div>

        @if (showCampaignPicker()) {
          <div style="margin-top:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <select [(ngModel)]="chosenCampaignId" name="chosenCampaignId" style="min-width:220px;">
              <option [ngValue]="null">Select a campaign…</option>
              @for (c of campaigns(); track c.id) {
                <option [ngValue]="c.id">{{ c.name }}</option>
              }
            </select>
            <button type="button" class="btn" style="background:var(--primary);color:#fff;border-color:var(--primary);"
              [disabled]="submitting() || chosenCampaignId === null" (click)="addToExistingCampaign()">
              Add here
            </button>
            @if (campaigns().length === 0) {
              <span style="color:var(--text-muted);font-size:13px;">No campaigns yet — use "Add to new campaign".</span>
            }
          </div>
        }
      </div>
    }

    <!-- New-campaign modal (replaces the native prompt) -->
    @if (showNewCampaignModal()) {
      <div class="modal-overlay" (click)="cancelNewCampaign()">
        <div class="modal-dialog" (click)="$event.stopPropagation()">
          <h3 class="modal-title">New campaign</h3>
          <p class="modal-sub">
            {{ selection().length }} space{{ selection().length === 1 ? '' : 's' }} will be added to this campaign's backlog.
          </p>
          <div class="form-group" style="margin-bottom:16px;">
            <label for="newCampaignName">Campaign name</label>
            <input #newCampaignInput id="newCampaignName" type="text"
              [(ngModel)]="newCampaignName" name="newCampaignName"
              placeholder="e.g. Summer 2026"
              (keydown.enter)="confirmNewCampaign()"
              (keydown.escape)="cancelNewCampaign()" />
          </div>
          <div class="modal-actions">
            <button type="button" class="btn" [disabled]="submitting()" (click)="cancelNewCampaign()">Cancel</button>
            <button type="button" class="btn" style="background:var(--primary);color:#fff;border-color:var(--primary);"
              [disabled]="submitting() || !newCampaignName.trim()" (click)="confirmNewCampaign()">
              @if (submitting()) { <span class="spinner"></span> } @else { Create campaign }
            </button>
          </div>
        </div>
      </div>
    }

    <!-- Map-centered split layout (#2): [map | results]. In list view results go full-width. -->
    <div [class.split-view]="showMap()">
      <!-- Map (always rendered so ViewChild is available; shown/hidden via CSS) -->
      <div [style.display]="showMap() ? 'block' : 'none'" class="card map-card">
        <div #mapEl class="map-el"></div>
      </div>

      <!-- Results pane -->
      <div class="results-pane">
      @if (loading()) {
        <div class="loading-container"><span class="spinner"></span></div>
      } @else if (searched() && spaces().length === 0) {
        <div class="empty-state">
          <div class="empty-icon">&#128270;</div>
          <p>No spaces found for this search. Try expanding your radius or changing filters.</p>
        </div>
      } @else if (spaces().length > 0) {
        <div [class]="showMap() ? 'cards-col' : 'cards-grid'">
          @for (space of spaces(); track space.id) {
            <div class="card" style="padding:0;overflow:hidden;cursor:pointer;"
              [class.selected-card]="selectedSpace()?.id === space.id"
              (click)="selectSpace(space)">
              @if (getFirstPhoto(space)) {
                <img [src]="getFirstPhoto(space)" [alt]="space.name"
                  style="width:100%;height:160px;object-fit:cover;" />
              } @else {
                <div style="width:100%;height:160px;background:var(--hover);display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:28px;">
                  &#128247;
                </div>
              }
              <div style="padding:16px;">
                <div style="display:flex;align-items:start;justify-content:space-between;margin-bottom:8px;">
                  <h3 style="font-size:16px;font-weight:600;margin:0;">{{ space.name }}</h3>
                  <span class="badge badge-active" style="white-space:nowrap;">{{ formatType(space.type) }}</span>
                </div>
                @if (space.location_text) {
                  <p style="color:var(--text-muted);font-size:12px;margin-bottom:6px;">{{ space.location_text }}</p>
                }
                <div style="display:flex;flex-wrap:wrap;gap:12px;font-size:13px;margin-bottom:8px;">
                  <div>
                    <span style="color:var(--text-muted);">Price/day:</span>
                    <strong style="color:var(--primary);"> \${{ space.price_per_day }}</strong>
                  </div>
                  @if (space.width && space.height) {
                    <div>
                      <span style="color:var(--text-muted);">Size:</span>
                      <strong> {{ space.width }}x{{ space.height }}m</strong>
                    </div>
                  }
                  @if (space.distance_km !== undefined && space.distance_km !== null) {
                    <div>
                      <span style="color:var(--text-muted);">Distance:</span>
                      <strong> {{ space.distance_km | number:'1.1-1' }} km</strong>
                    </div>
                  }
                </div>
                @if (space.provider) {
                  <p style="font-size:12px;color:var(--text-muted);">
                    Provider: <strong>{{ space.provider.company_name || space.provider.name }}</strong>
                  </p>
                }
                <button type="button" class="btn"
                  (click)="$event.stopPropagation(); toggleSelection(space)"
                  [style.background]="isInSelection(space) ? 'var(--primary)' : ''"
                  [style.color]="isInSelection(space) ? '#fff' : ''"
                  style="width:100%;margin-top:8px;">
                  @if (isInSelection(space)) { ✓ En la selección } @else { ➕ Añadir a la selección }
                </button>
                <!-- design.json §10 (UC-8) — the ONLY path to a provider chat: from a published
                     listing, carrying the space as context. -->
                <button type="button" class="btn"
                  (click)="$event.stopPropagation(); messageProvider(space)"
                  style="width:100%;margin-top:6px;">
                  💬 Mensaje al proveedor
                </button>
              </div>
            </div>
          }
        </div>

        <!-- Pagination -->
        @if (lastPage() > 1) {
          <div class="pagination" style="margin-top:24px;">
            <button [disabled]="currentPage() <= 1" (click)="loadPage(currentPage() - 1)">Prev</button>
            @for (p of pages(); track p) {
              <button [class.active]="p === currentPage()" (click)="loadPage(p)">{{ p }}</button>
            }
            <button [disabled]="currentPage() >= lastPage()" (click)="loadPage(currentPage() + 1)">Next</button>
          </div>
        }

        <p style="text-align:center;color:var(--text-muted);font-size:12px;margin-top:8px;">
          Showing {{ spaces().length }} of {{ total() }} results
        </p>
      }
      </div><!-- /results-pane -->
    </div><!-- /split-view -->
  `,
  styles: [`
    .selected-card { outline: 2px solid var(--primary); }
    .map-card { margin-bottom: 24px; padding: 0; overflow: hidden; }
    .map-el { height: 480px; width: 100%; }
    .cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
    .cards-col { display: flex; flex-direction: column; gap: 12px; }
    /* Map-centered split: map left, scrollable results right (#2) */
    .split-view { display: flex; gap: 16px; align-items: flex-start; }
    .split-view .map-card { flex: 1 1 60%; margin-bottom: 0; position: sticky; top: 16px; }
    .split-view .map-el { height: calc(100vh - 240px); min-height: 480px; }
    .split-view .results-pane { flex: 1 1 40%; max-height: calc(100vh - 240px); overflow-y: auto; padding-right: 4px; }
    @media (max-width: 880px) {
      .split-view { flex-direction: column; }
      .split-view .map-card, .split-view .results-pane { width: 100%; max-height: none; position: static; }
    }
    /* New-campaign modal */
    .modal-overlay {
      position: fixed; inset: 0; z-index: 1000;
      background: rgba(0, 0, 0, 0.45);
      display: flex; align-items: center; justify-content: center;
      padding: 16px;
      animation: modal-fade 0.12s ease-out;
    }
    .modal-dialog {
      background: var(--surface, #fff);
      border-radius: 12px;
      box-shadow: 0 12px 40px rgba(0, 0, 0, 0.25);
      width: 100%; max-width: 420px;
      padding: 24px;
      animation: modal-pop 0.12s ease-out;
    }
    .modal-title { margin: 0 0 4px; font-size: 18px; font-weight: 600; }
    .modal-sub { margin: 0 0 16px; font-size: 13px; color: var(--text-muted); }
    .modal-actions { display: flex; justify-content: flex-end; gap: 8px; }
    @keyframes modal-fade { from { opacity: 0; } to { opacity: 1; } }
    @keyframes modal-pop { from { transform: translateY(8px) scale(0.98); opacity: 0; } to { transform: none; opacity: 1; } }
  `],
})
export class SpaceSearchComponent implements OnInit, AfterViewInit, OnDestroy {
  @ViewChild('mapEl') mapEl!: ElementRef<HTMLDivElement>;
  @ViewChild('newCampaignInput') newCampaignInput?: ElementRef<HTMLInputElement>;

  private readonly api = environment.apiUrl;

  // Default: San Pedro Garza García, Nuevo León
  latitude = 25.6597;
  longitude = -100.4023;
  radius_km = 20;
  type = '';
  date_from = '';
  date_to = '';

  spaces = signal<Space[]>([]);
  loading = signal(false);
  searched = signal(false);
  currentPage = signal(1);
  lastPage = signal(1);
  total = signal(0);
  pages = signal<number[]>([]);
  showMap = signal(true);
  selectedSpace = signal<Space | null>(null);

  // ── Selection basket (C03) ──────────────────────────────────────────────────
  selection = signal<Space[]>([]);
  submitting = signal(false);
  showCampaignPicker = signal(false);
  campaigns = signal<CampaignLite[]>([]);
  chosenCampaignId: number | null = null;

  // New-campaign modal (replaces the native prompt())
  showNewCampaignModal = signal(false);
  newCampaignName = '';

  private map: L.Map | null = null;
  private centerMarker: L.Marker | null = null;
  private spaceMarkers: L.Marker[] = [];

  constructor(
    private http: HttpClient,
    private notify: NotificationService,
    private zone: NgZone,
    private router: Router,
  ) {}

  ngOnInit(): void {
    this.search();
  }

  ngAfterViewInit(): void {
    this.zone.runOutsideAngular(() => this.initMap());
  }

  ngOnDestroy(): void {
    this.map?.remove();
    this.map = null;
  }

  // ── Map ────────────────────────────────────────────────────────────────────

  private initMap(): void {
    this.map = L.map(this.mapEl.nativeElement, {
      center: [this.latitude, this.longitude],
      zoom: 12,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      maxZoom: 19,
    }).addTo(this.map);

    this.centerMarker = L.marker([this.latitude, this.longitude], { icon: CENTER_ICON }).addTo(this.map);

    this.map.on('click', (e: L.LeafletMouseEvent) => {
      this.zone.run(() => {
        this.latitude = +e.latlng.lat.toFixed(6);
        this.longitude = +e.latlng.lng.toFixed(6);
        this.centerMarker?.setLatLng([this.latitude, this.longitude]);
        this.search();
      });
    });

    // The map can mount inside a flex column whose size settles after layout —
    // invalidate once and draw any spaces already loaded by the initial search.
    setTimeout(() => {
      this.map?.invalidateSize();
      this.updateMapMarkers();
    }, 0);
  }

  private updateMapMarkers(): void {
    if (!this.map) return;
    // Update center marker
    this.centerMarker?.setLatLng([this.latitude, this.longitude]);
    this.map.setView([this.latitude, this.longitude], this.map.getZoom());

    // Clear old space markers
    this.spaceMarkers.forEach(m => m.remove());
    this.spaceMarkers = [];

    // Add new space markers
    this.spaces().forEach(space => {
      const lat = +space.latitude;
      const lng = +space.longitude;
      const isSelected = this.selectedSpace()?.id === space.id;
      const marker = L.marker([lat, lng], {
        icon: isSelected ? SPACE_ICON_SELECTED : SPACE_ICON,
      }).addTo(this.map!);

      marker.bindPopup(`
        <div style="min-width:160px;">
          <strong style="display:block;margin-bottom:4px;">${space.name}</strong>
          <span style="font-size:12px;color:#666;">${this.formatType(space.type)}</span><br>
          <strong style="color:#2563eb;">$${space.price_per_day}/day</strong>
          ${space.width && space.height ? `<span style="font-size:12px;color:#666;margin-left:6px;">${space.width}×${space.height}m</span>` : ''}
        </div>
      `);

      marker.on('click', () => {
        this.zone.run(() => this.selectSpace(space));
      });

      this.spaceMarkers.push(marker);
    });
  }

  // ── View toggle ────────────────────────────────────────────────────────────

  toggleView(): void {
    this.showMap.update(v => !v);
    if (this.showMap() && this.map) {
      // Leaflet needs an invalidateSize() after being shown from display:none
      setTimeout(() => this.map?.invalidateSize(), 50);
      this.zone.runOutsideAngular(() => this.updateMapMarkers());
    }
  }

  // ── Search ─────────────────────────────────────────────────────────────────

  search(): void {
    this.currentPage.set(1);
    this.loadPage(1);
  }

  loadPage(page: number): void {
    this.loading.set(true);
    this.searched.set(true);

    let params = new HttpParams()
      .set('latitude', this.latitude.toString())
      .set('longitude', this.longitude.toString())
      .set('radius_km', this.radius_km.toString())
      .set('page', page.toString());

    if (this.type) params = params.set('type', this.type);
    if (this.date_from) params = params.set('date_from', this.date_from);
    if (this.date_to) params = params.set('date_to', this.date_to);

    this.http.get<PaginatedResponse<Space>>(`${this.api}/client/spaces/search`, { params }).subscribe({
      next: (res) => {
        this.spaces.set(res.data);
        this.currentPage.set(res.current_page);
        this.lastPage.set(res.last_page);
        this.total.set(res.total);
        this.pages.set(Array.from({ length: res.last_page }, (_, i) => i + 1));
        this.loading.set(false);
        if (this.showMap()) {
          this.zone.runOutsideAngular(() => this.updateMapMarkers());
        }
      },
      error: (err) => {
        this.notify.error(err.error?.message || 'Search failed.');
        this.loading.set(false);
      },
    });
  }

  selectSpace(space: Space): void {
    this.selectedSpace.set(space);
    if (this.showMap() && this.map) {
      this.map.setView([+space.latitude, +space.longitude], 15);
      this.zone.runOutsideAngular(() => this.updateMapMarkers());
    }
  }

  /**
   * UC-8 · design.json §10 — open a client↔provider inquiry FROM this published listing (the ONLY
   * path to a provider). Sends target=provider + the space as context; the backend anchors the
   * provider from the space, so the client never needs the provider's identity up front.
   */
  messageProvider(space: Space): void {
    this.router.navigate(['/messages/new'], {
      queryParams: { target: 'provider', object_type: 'space', object_id: space.id },
    });
  }

  // ── Selection basket (C03) ──────────────────────────────────────────────────

  isInSelection(space: Space): boolean {
    return this.selection().some(s => s.id === space.id);
  }

  toggleSelection(space: Space): void {
    if (this.isInSelection(space)) {
      this.selection.update(list => list.filter(s => s.id !== space.id));
    } else {
      this.selection.update(list => [...list, space]);
    }
  }

  selectionNames(): string {
    const names = this.selection().map(s => s.name);
    return names.length > 3 ? names.slice(0, 3).join(', ') + ` +${names.length - 3} more` : names.join(', ');
  }

  clearSelection(): void {
    this.selection.set([]);
    this.showCampaignPicker.set(false);
    this.chosenCampaignId = null;
  }

  addToNewCampaign(): void {
    this.newCampaignName = '';
    this.showNewCampaignModal.set(true);
    // Focus the field once the modal is in the DOM (native autofocus doesn't fire on *ngIf inserts).
    setTimeout(() => this.newCampaignInput?.nativeElement.focus(), 0);
  }

  cancelNewCampaign(): void {
    this.showNewCampaignModal.set(false);
    this.newCampaignName = '';
  }

  confirmNewCampaign(): void {
    const name = this.newCampaignName.trim();
    if (!name) {
      this.notify.warning('Please enter a campaign name.');
      return;
    }

    this.submitting.set(true);
    this.http.post<{ id: number }>(`${this.api}/client/campaigns`, { name }).subscribe({
      next: (campaign) => {
        this.showNewCampaignModal.set(false);
        this.newCampaignName = '';
        this.pushBacklog(campaign.id, true);
      },
      error: (err) => {
        this.notify.error(err.error?.message || 'Could not create the campaign.');
        this.submitting.set(false);
      },
    });
  }

  openExistingPicker(): void {
    this.showCampaignPicker.update(v => !v);
    if (this.showCampaignPicker() && this.campaigns().length === 0) {
      this.http.get<PaginatedResponse<CampaignLite> | CampaignLite[]>(`${this.api}/client/campaigns`).subscribe({
        next: (res) => this.campaigns.set(Array.isArray(res) ? res : res.data),
        error: () => this.notify.error('Could not load your campaigns.'),
      });
    }
  }

  addToExistingCampaign(): void {
    if (this.chosenCampaignId === null) return;
    this.submitting.set(true);
    this.pushBacklog(this.chosenCampaignId, false);
  }

  /** Send the selected space ids to a campaign's backlog as orphan ads. */
  private pushBacklog(campaignId: number, isNew: boolean): void {
    const space_ids = this.selection().map(s => s.id);
    this.http.post(`${this.api}/client/campaigns/${campaignId}/backlog`, { space_ids }).subscribe({
      next: () => {
        this.notify.success(`${space_ids.length} space(s) added to the campaign backlog.`);
        this.clearSelection();
        this.submitting.set(false);
        if (isNew) {
          this.router.navigate(['/client/campaigns', campaignId]);
        }
      },
      error: (err) => {
        this.notify.error(err.error?.message || 'Could not add spaces to the campaign.');
        this.submitting.set(false);
      },
    });
  }

  getFirstPhoto(space: Space): string | null {
    return (space.photos && space.photos.length > 0) ? space.photos[0].file_url : null;
  }

  // Null-safe on purpose: `spaces.type` is nullable and this is called from a template
  // expression, where a throw kills the whole update pass. See
  // core/models/space-type.helper.ts.
  formatType = formatSpaceType;
}
