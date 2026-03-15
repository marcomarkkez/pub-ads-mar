import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpParams } from '@angular/common/http';
import { GoogleMapsModule } from '@angular/google-maps';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';
import { Space, PaginatedResponse } from '../../../core/models';

@Component({
  selector: 'app-space-search',
  standalone: true,
  imports: [CommonModule, FormsModule, GoogleMapsModule],
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

    <!-- Map View -->
    @if (showMap()) {
      <div class="card" style="margin-bottom:24px;padding:0;overflow:hidden;">
        @if (mapsApiLoaded()) {
          <google-map
            height="480px"
            width="100%"
            [center]="mapCenter()"
            [zoom]="mapZoom"
            (mapClick)="onMapClick($event)">
            <!-- Search center marker -->
            <map-marker
              [position]="mapCenter()"
              [options]="centerMarkerOptions">
            </map-marker>
            <!-- Space markers -->
            @for (space of spaces(); track space.id) {
              <map-marker
                [position]="{ lat: +space.latitude, lng: +space.longitude }"
                [options]="getSpaceMarkerOptions(space)"
                (mapClick)="selectSpace(space)">
              </map-marker>
            }
            @if (selectedSpace()) {
              <map-info-window>
                <div style="padding:8px;max-width:220px;">
                  <strong style="display:block;margin-bottom:4px;">{{ selectedSpace()!.name }}</strong>
                  <span style="font-size:12px;color:#666;">{{ formatType(selectedSpace()!.type) }}</span><br>
                  <strong style="color:#2563eb;">\${{ selectedSpace()!.price_per_day }}/day</strong>
                  @if (selectedSpace()!.width && selectedSpace()!.height) {
                    <span style="font-size:12px;color:#666;margin-left:8px;">{{ selectedSpace()!.width }}x{{ selectedSpace()!.height }}m</span>
                  }
                </div>
              </map-info-window>
            }
          </google-map>
        } @else {
          <div style="height:480px;display:flex;align-items:center;justify-content:center;color:var(--text-muted);">
            <div style="text-align:center;">
              <div style="font-size:32px;margin-bottom:8px;">🗺️</div>
              <p>Loading map...</p>
              @if (mapsApiError()) {
                <p style="color:var(--danger);font-size:12px;">{{ mapsApiError() }}</p>
              }
            </div>
          </div>
        }
      </div>
    }

    <!-- List Results -->
    @if (!showMap()) {
      @if (loading()) {
        <div class="loading-container"><span class="spinner"></span></div>
      } @else if (searched() && spaces().length === 0) {
        <div class="empty-state">
          <div class="empty-icon">&#128270;</div>
          <p>No spaces found for this search. Try expanding your radius or changing filters.</p>
        </div>
      } @else if (spaces().length > 0) {
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;">
          @for (space of spaces(); track space.id) {
            <div class="card" style="padding:0;overflow:hidden;" [class.selected-card]="selectedSpace()?.id === space.id" (click)="selectSpace(space)">
              <!-- Photo -->
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
                @if (space.location_name) {
                  <p style="color:var(--text-muted);font-size:12px;margin-bottom:6px;">{{ space.location_name }}</p>
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
    }
  `,
  styles: [`
    .selected-card {
      outline: 2px solid var(--primary);
    }
  `],
})
export class SpaceSearchComponent implements OnInit {
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
  showMap = signal(false);
  selectedSpace = signal<Space | null>(null);
  mapsApiLoaded = signal(false);
  mapsApiError = signal('');
  mapCenter = signal<google.maps.LatLngLiteral>({ lat: 25.6597, lng: -100.4023 });
  mapZoom = 12;

  centerMarkerOptions: google.maps.MarkerOptions = {
    icon: {
      path: google.maps.SymbolPath.CIRCLE,
      scale: 8,
      fillColor: '#2563eb',
      fillOpacity: 1,
      strokeColor: '#fff',
      strokeWeight: 2,
    },
    title: 'Search center',
  };

  constructor(
    private http: HttpClient,
    private notify: NotificationService,
  ) {}

  ngOnInit(): void {
    this.loadGoogleMaps();
    this.search();
  }

  private loadGoogleMaps(): void {
    if (typeof google !== 'undefined' && google.maps) {
      this.mapsApiLoaded.set(true);
      return;
    }
    const key = environment.googleMapsApiKey;
    if (!key || key === 'YOUR_GOOGLE_MAPS_API_KEY') {
      this.mapsApiError.set('Google Maps API key not configured.');
      return;
    }
    const script = document.createElement('script');
    script.src = `https://maps.googleapis.com/maps/api/js?key=${key}`;
    script.async = true;
    script.onload = () => this.mapsApiLoaded.set(true);
    script.onerror = () => this.mapsApiError.set('Failed to load Google Maps.');
    document.head.appendChild(script);
  }

  toggleView(): void {
    this.showMap.update(v => !v);
  }

  search(): void {
    this.currentPage.set(1);
    this.mapCenter.set({ lat: this.latitude, lng: this.longitude });
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
      },
      error: (err) => {
        this.notify.error(err.error?.message || 'Search failed.');
        this.loading.set(false);
      },
    });
  }

  onMapClick(event: google.maps.MapMouseEvent): void {
    if (event.latLng) {
      this.latitude = event.latLng.lat();
      this.longitude = event.latLng.lng();
      this.mapCenter.set({ lat: this.latitude, lng: this.longitude });
      this.search();
    }
  }

  selectSpace(space: Space): void {
    this.selectedSpace.set(space);
    this.mapCenter.set({ lat: +space.latitude, lng: +space.longitude });
  }

  getSpaceMarkerOptions(space: Space): google.maps.MarkerOptions {
    return {
      title: space.name,
      icon: {
        url: 'https://maps.google.com/mapfiles/ms/icons/red-dot.png',
      },
    };
  }

  getFirstPhoto(space: Space): string | null {
    if (space.photos && space.photos.length > 0) {
      return space.photos[0].file_url;
    }
    return null;
  }

  formatType(type: string): string {
    return type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
  }
}
