import {
  Component,
  Input,
  Output,
  EventEmitter,
  AfterViewInit,
  OnChanges,
  OnDestroy,
  SimpleChanges,
  ElementRef,
  ViewChild,
  inject,
  NgZone,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import * as L from 'leaflet';
import { environment } from '../../../../environments/environment';

// ── HOW TO SWAP BACK TO GOOGLE MAPS ──────────────────────────────────────────
// 1. Set environment.mapProvider = 'google' in environment.ts
// 2. Set environment.googleMapsApiKey to your key
// 3. In ngAfterViewInit, call initGoogleMap() instead of initLeafletMap()
// 4. Replace L.Map / L.Marker types with google.maps.Map / google.maps.Marker
// 5. Add @angular/google-maps back to imports array
// 6. The @Input/@Output API of this component stays unchanged — consumers are unaffected.
// ──────────────────────────────────────────────────────────────────────────────

// Custom pin icon using a div — no asset files needed
const PIN_ICON = L.divIcon({
  html: `<div style="width:18px;height:18px;border-radius:50%;background:#e11d48;border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.4);"></div>`,
  iconSize: [18, 18],
  iconAnchor: [9, 9],
  className: '',
});

@Component({
  selector: 'app-location-picker',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <!-- what3words search input (hidden when readonly or no API key configured) -->
    @if (!readonly && hasW3wKey) {
      <div style="position:relative;margin-bottom:8px;">
        <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#e11d48;font-size:13px;font-weight:700;pointer-events:none;z-index:1;">///</span>
        <input
          type="text"
          [(ngModel)]="w3wInput"
          (input)="onW3wInput()"
          (keydown.enter)="resolveW3w(); $event.preventDefault()"
          placeholder="word.word.word — or click the map"
          style="width:100%;padding:8px 8px 8px 34px;border:1px solid var(--border);border-radius:6px;font-size:13px;box-sizing:border-box;">
        @if (w3wSuggestions.length > 0) {
          <ul style="position:absolute;z-index:9999;background:#fff;border:1px solid var(--border);border-radius:6px;list-style:none;margin:2px 0 0;padding:4px 0;width:100%;box-shadow:0 4px 12px rgba(0,0,0,.1);">
            @for (s of w3wSuggestions; track s.words) {
              <li (click)="pickSuggestion(s)"
                style="padding:8px 12px;cursor:pointer;font-size:13px;"
                (mouseenter)="$event.target && hoverItem($event, true)"
                (mouseleave)="$event.target && hoverItem($event, false)">
                <span style="color:#e11d48;font-weight:700;">///</span>{{ s.words }}
                <span style="color:var(--text-muted);font-size:11px;margin-left:6px;">{{ s.nearestPlace }}</span>
              </li>
            }
          </ul>
        }
      </div>
    }

    <!-- Leaflet map container -->
    <div #mapEl [style.height]="height" style="width:100%;border-radius:8px;overflow:hidden;"></div>

    @if (!readonly && currentLat !== null && currentLng !== null) {
      <p style="font-size:12px;color:var(--text-muted);margin-top:4px;margin-bottom:0;">
        {{ currentLat | number:'1.4-6' }}, {{ currentLng | number:'1.4-6' }}
        @if (w3wAddress) {
          &nbsp;·&nbsp;<span style="color:#e11d48;font-weight:600;">///</span><span>{{ w3wAddress }}</span>
        }
      </p>
    }

    @if (w3wError) {
      <p style="color:var(--danger);font-size:12px;margin-top:4px;">{{ w3wError }}</p>
    }
  `,
})
export class LocationPickerComponent implements AfterViewInit, OnChanges, OnDestroy {
  @ViewChild('mapEl') mapEl!: ElementRef<HTMLDivElement>;

  /** Initial / bound latitude */
  @Input() lat: number | null = null;
  /** Initial / bound longitude */
  @Input() lng: number | null = null;
  /** Map height CSS value */
  @Input() height = '320px';
  /** When true, clicking the map is disabled and no w3w input is shown */
  @Input() readonly = false;

  /** Emitted when the user picks a new location */
  @Output() locationChange = new EventEmitter<{ lat: number; lng: number }>();

  // what3words state
  w3wInput = '';
  w3wSuggestions: Array<{ words: string; nearestPlace: string }> = [];
  w3wError = '';
  w3wAddress = '';
  readonly hasW3wKey = environment.what3wordsApiKey !== 'YOUR_W3W_API_KEY' && !!environment.what3wordsApiKey;

  // Displayed coords (updated on pick)
  currentLat: number | null = null;
  currentLng: number | null = null;

  private map: L.Map | null = null;
  private marker: L.Marker | null = null;
  private w3wTimer: ReturnType<typeof setTimeout> | null = null;

  private readonly DEFAULT_LAT = 25.6597;
  private readonly DEFAULT_LNG = -100.4023;

  private readonly http = inject(HttpClient);
  private readonly zone = inject(NgZone);

  ngAfterViewInit(): void {
    // Run outside Angular to avoid extra change-detection cycles from Leaflet events
    this.zone.runOutsideAngular(() => this.initMap());
  }

  ngOnChanges(changes: SimpleChanges): void {
    if ((changes['lat'] || changes['lng']) && this.map) {
      this.zone.runOutsideAngular(() => this.syncMarker());
    }
  }

  ngOnDestroy(): void {
    if (this.w3wTimer) clearTimeout(this.w3wTimer);
    this.map?.remove();
    this.map = null;
  }

  // ── Map init ───────────────────────────────────────────────────────────────

  private initMap(): void {
    const centerLat = this.lat ?? this.DEFAULT_LAT;
    const centerLng = this.lng ?? this.DEFAULT_LNG;

    this.map = L.map(this.mapEl.nativeElement, {
      center: [centerLat, centerLng],
      zoom: 13,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      maxZoom: 19,
    }).addTo(this.map);

    if (this.lat !== null && this.lng !== null) {
      this.zone.run(() => {
        this.currentLat = this.lat;
        this.currentLng = this.lng;
      });
      this.placeMarker(this.lat!, this.lng!);
    }

    if (!this.readonly) {
      this.map.on('click', (e: L.LeafletMouseEvent) => {
        const lat = +e.latlng.lat.toFixed(7);
        const lng = +e.latlng.lng.toFixed(7);
        this.placeMarker(lat, lng);
        this.zone.run(() => {
          this.currentLat = lat;
          this.currentLng = lng;
          this.w3wSuggestions = [];
          this.w3wError = '';
          this.locationChange.emit({ lat, lng });
          this.lookupW3w(lat, lng);
        });
      });
    }
  }

  private syncMarker(): void {
    if (!this.map || this.lat === null || this.lng === null) return;
    this.placeMarker(this.lat, this.lng);
    this.map.setView([this.lat, this.lng], this.map.getZoom());
    this.zone.run(() => {
      this.currentLat = this.lat;
      this.currentLng = this.lng;
    });
  }

  private placeMarker(lat: number, lng: number): void {
    if (this.marker) {
      this.marker.setLatLng([lat, lng]);
    } else if (this.map) {
      this.marker = L.marker([lat, lng], { icon: PIN_ICON, draggable: !this.readonly }).addTo(this.map);
      if (!this.readonly) {
        this.marker.on('dragend', () => {
          const pos = this.marker!.getLatLng();
          const newLat = +pos.lat.toFixed(7);
          const newLng = +pos.lng.toFixed(7);
          this.zone.run(() => {
            this.currentLat = newLat;
            this.currentLng = newLng;
            this.locationChange.emit({ lat: newLat, lng: newLng });
            this.lookupW3w(newLat, newLng);
          });
        });
      }
    }
  }

  // ── what3words ─────────────────────────────────────────────────────────────

  onW3wInput(): void {
    this.w3wSuggestions = [];
    this.w3wError = '';
    if (this.w3wTimer) clearTimeout(this.w3wTimer);
    const val = this.w3wInput.trim().replace(/^\/+/, '');
    if (val.split('.').length < 2) return;
    this.w3wTimer = setTimeout(() => this.fetchSuggestions(val), 350);
  }

  resolveW3w(): void {
    const val = this.w3wInput.trim().replace(/^\/+/, '');
    if (val.split('.').length !== 3) return;
    this.fetchCoords(val);
  }

  pickSuggestion(s: { words: string; nearestPlace: string }): void {
    this.w3wInput = s.words;
    this.w3wSuggestions = [];
    this.fetchCoords(s.words);
  }

  hoverItem(e: Event, hovered: boolean): void {
    (e.target as HTMLElement).style.background = hovered ? 'var(--hover)' : '';
  }

  private fetchSuggestions(words: string): void {
    this.http.get<any>('https://api.what3words.com/v3/autosuggest', {
      params: {
        key: environment.what3wordsApiKey,
        input: words,
        'n-results': '3',
        focus: `${this.DEFAULT_LAT},${this.DEFAULT_LNG}`,
      },
    }).subscribe({
      next: (res) => {
        this.w3wSuggestions = (res.suggestions ?? []).map((s: any) => ({
          words: s.words,
          nearestPlace: s.nearestPlace ?? '',
        }));
      },
      error: () => { this.w3wError = 'Suggestion lookup failed.'; },
    });
  }

  private fetchCoords(words: string): void {
    this.http.get<any>('https://api.what3words.com/v3/convert-to-coordinates', {
      params: { key: environment.what3wordsApiKey, words },
    }).subscribe({
      next: (res) => {
        const lat = +res.coordinates.lat.toFixed(7);
        const lng = +res.coordinates.lng.toFixed(7);
        this.zone.runOutsideAngular(() => {
          this.placeMarker(lat, lng);
          this.map?.setView([lat, lng], 16);
        });
        this.currentLat = lat;
        this.currentLng = lng;
        this.w3wAddress = words;
        this.w3wError = '';
        this.locationChange.emit({ lat, lng });
      },
      error: () => { this.w3wError = 'Could not resolve what3words address.'; },
    });
  }

  private lookupW3w(lat: number, lng: number): void {
    if (!this.hasW3wKey) return;
    this.http.get<any>('https://api.what3words.com/v3/convert-to-3wa', {
      params: { key: environment.what3wordsApiKey, coordinates: `${lat},${lng}`, language: 'en' },
    }).subscribe({
      next: (res) => { this.w3wAddress = res.words ?? ''; },
      error: () => {},
    });
  }
}
