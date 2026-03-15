import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink, ActivatedRoute } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { GoogleMapsModule } from '@angular/google-maps';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';
import { Space } from '../../../core/models';

@Component({
  selector: 'app-space-form',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink, GoogleMapsModule],
  template: `
    <div class="page-header">
      <h1>{{ isEdit() ? 'Edit Space' : 'New Space' }}</h1>
      <a routerLink="/provider/spaces" class="btn">Back to Spaces</a>
    </div>

    @if (loadingSpace()) {
      <div class="loading-container"><span class="spinner"></span></div>
    } @else {
      <div class="card">
        @if (error()) {
          <div class="alert alert-error">{{ error() }}</div>
        }

        <form (ngSubmit)="onSubmit()" #spaceForm="ngForm">
          <div class="form-group">
            <label for="name">Name *</label>
            <input id="name" name="name" [(ngModel)]="form.name" required placeholder="Space name">
          </div>

          <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" [(ngModel)]="form.description" placeholder="Describe this space..."></textarea>
          </div>

          <div class="form-group">
            <label for="type">Type *</label>
            <select id="type" name="type" [(ngModel)]="form.type" required>
              <option value="" disabled>Select type</option>
              <option value="billboard">Billboard</option>
              <option value="big_screen">Big Screen</option>
              <option value="little_screen">Little Screen</option>
              <option value="radio_station">Radio Station</option>
              <option value="other">Other</option>
            </select>
          </div>

          <!-- Map Location Picker -->
          <div class="form-group">
            <label>Location * <span style="font-weight:400;color:var(--text-muted);font-size:12px;">— click on the map to set coordinates</span></label>
            @if (mapsApiLoaded()) {
              <google-map
                height="320px"
                width="100%"
                [center]="mapCenter()"
                [zoom]="mapZoom"
                (mapClick)="onMapClick($event)"
                style="border-radius:8px;overflow:hidden;display:block;margin-bottom:8px;">
                @if (form.latitude && form.longitude) {
                  <map-marker
                    [position]="{ lat: form.latitude, lng: form.longitude }"
                    [options]="markerOptions">
                  </map-marker>
                }
              </google-map>
            } @else {
              <div style="height:320px;background:var(--hover);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--text-muted);margin-bottom:8px;">
                @if (mapsApiError()) {
                  <span style="color:var(--danger);font-size:13px;">{{ mapsApiError() }}</span>
                } @else {
                  <span>Loading map...</span>
                }
              </div>
            }

            <div class="form-row">
              <div class="form-group" style="margin-bottom:0;">
                <label for="latitude">Latitude *</label>
                <input id="latitude" name="latitude" type="number" step="any"
                  [(ngModel)]="form.latitude" required placeholder="e.g. 25.6597"
                  (ngModelChange)="onCoordsChange()">
              </div>
              <div class="form-group" style="margin-bottom:0;">
                <label for="longitude">Longitude *</label>
                <input id="longitude" name="longitude" type="number" step="any"
                  [(ngModel)]="form.longitude" required placeholder="e.g. -100.4023"
                  (ngModelChange)="onCoordsChange()">
              </div>
            </div>
          </div>

          <div class="form-group">
            <label for="location_text">Location Name</label>
            <input id="location_text" name="location_text" [(ngModel)]="form.location_text" placeholder="e.g. San Pedro Garza García, NL">
          </div>

          <div class="form-group">
            <label for="price_per_day">Price per Day (MXN) *</label>
            <input id="price_per_day" name="price_per_day" type="number" step="0.01" min="0" [(ngModel)]="form.price_per_day" required placeholder="0.00">
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="width">Width (m)</label>
              <input id="width" name="width" type="number" step="0.01" min="0" [(ngModel)]="form.width" placeholder="Width in meters">
            </div>
            <div class="form-group">
              <label for="height">Height (m)</label>
              <input id="height" name="height" type="number" step="0.01" min="0" [(ngModel)]="form.height" placeholder="Height in meters">
            </div>
          </div>

          <div class="form-group checkbox-group">
            <label>
              <input type="checkbox" name="is_active" [(ngModel)]="form.is_active">
              <span>Active (visible to clients)</span>
            </label>
          </div>

          <div class="form-actions">
            <a routerLink="/provider/spaces" class="btn">Cancel</a>
            <button type="submit" class="btn btn-primary" style="width:auto" [disabled]="saving() || spaceForm.invalid">
              @if (saving()) {
                <span class="spinner"></span> Saving...
              } @else {
                {{ isEdit() ? 'Update Space' : 'Create Space' }}
              }
            </button>
          </div>
        </form>
      </div>
    }
  `,
  styles: [`
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }
    .checkbox-group label {
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      font-weight: 400;

      input[type="checkbox"] {
        width: auto;
        accent-color: var(--primary);
      }
    }
    .form-actions {
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      margin-top: 24px;
      padding-top: 16px;
      border-top: 1px solid var(--border);
    }
  `],
})
export class SpaceFormComponent implements OnInit {
  isEdit = signal(false);
  loadingSpace = signal(false);
  saving = signal(false);
  error = signal('');
  mapsApiLoaded = signal(false);
  mapsApiError = signal('');

  // Default center: San Pedro Garza García, NL
  mapCenter = signal<google.maps.LatLngLiteral>({ lat: 25.6597, lng: -100.4023 });
  mapZoom = 13;

  markerOptions: google.maps.MarkerOptions = {
    draggable: true,
    title: 'Space location',
  };

  form = {
    name: '',
    description: '',
    type: '' as string,
    latitude: null as number | null,
    longitude: null as number | null,
    location_text: '',
    price_per_day: null as number | null,
    width: null as number | null,
    height: null as number | null,
    is_active: true,
  };

  private spaceId: number | null = null;
  private readonly api = environment.apiUrl;

  constructor(
    private http: HttpClient,
    private router: Router,
    private route: ActivatedRoute,
    private notify: NotificationService,
  ) {}

  ngOnInit(): void {
    this.loadGoogleMaps();

    const idParam = this.route.snapshot.paramMap.get('id');
    const url = this.router.url;

    if (idParam && url.endsWith('/edit')) {
      this.spaceId = +idParam;
      this.isEdit.set(true);
      this.loadSpace();
    }
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

  onMapClick(event: google.maps.MapMouseEvent): void {
    if (event.latLng) {
      this.form.latitude = +event.latLng.lat().toFixed(7);
      this.form.longitude = +event.latLng.lng().toFixed(7);
      this.mapCenter.set({ lat: this.form.latitude, lng: this.form.longitude });
    }
  }

  onCoordsChange(): void {
    if (this.form.latitude && this.form.longitude) {
      this.mapCenter.set({ lat: this.form.latitude, lng: this.form.longitude });
    }
  }

  private loadSpace(): void {
    this.loadingSpace.set(true);

    this.http.get<{ data: Space }>(`${this.api}/provider/spaces/${this.spaceId}`).subscribe({
      next: (res) => {
        const s = res.data;
        this.form.name = s.name;
        this.form.description = s.description || '';
        this.form.type = s.type;
        this.form.latitude = s.latitude;
        this.form.longitude = s.longitude;
        this.form.location_text = s.location_text || s.location_name || '';
        this.form.price_per_day = s.price_per_day;
        this.form.width = s.width;
        this.form.height = s.height;
        this.form.is_active = s.is_active;
        if (s.latitude && s.longitude) {
          this.mapCenter.set({ lat: +s.latitude, lng: +s.longitude });
        }
        this.loadingSpace.set(false);
      },
      error: (err) => {
        this.error.set(err.error?.message || 'Failed to load space.');
        this.loadingSpace.set(false);
      },
    });
  }

  onSubmit(): void {
    this.saving.set(true);
    this.error.set('');

    const payload = { ...this.form };

    const request$ = this.isEdit()
      ? this.http.put(`${this.api}/provider/spaces/${this.spaceId}`, payload)
      : this.http.post(`${this.api}/provider/spaces`, payload);

    request$.subscribe({
      next: () => {
        this.notify.success(this.isEdit() ? 'Space updated successfully.' : 'Space created successfully.');
        this.router.navigate(['/provider/spaces']);
      },
      error: (err) => {
        this.error.set(err.error?.message || 'Failed to save space.');
        this.saving.set(false);
      },
    });
  }
}
