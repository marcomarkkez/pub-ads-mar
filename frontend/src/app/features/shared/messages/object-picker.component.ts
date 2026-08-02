import { Component, EventEmitter, Output, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { AuthService } from '../../../core/services/auth.service';
import { ChatObjectType } from '../../../core/models';

export interface PickedObject {
  object_type: ChatObjectType;
  object_id: number;
  label: string;
}

interface ObjOption { id: number; name?: string; label?: string; }

// UC-8 · design.json §10 — attach an object via TWO dropdowns (object type → the object,
// e.g. Ads → "My Ad #1"). Attaching is ownership-checked server-side.
@Component({
  selector: 'app-object-picker',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="picker">
      <div class="picker-field">
        <label>Tipo de objeto</label>
        <select [(ngModel)]="objectType" (ngModelChange)="onTypeChange()">
          <option value="">— Ninguno —</option>
          <option value="campaign">Campañas</option>
          <option value="adset">Adsets</option>
          <option value="ad">Ads</option>
          <option value="space">Espacios</option>
          <option value="booking">Reservas</option>
          <option value="payment">Pagos</option>
        </select>
      </div>

      @if (objectType) {
        <div class="picker-field">
          <label>Objeto</label>
          @if (loadingOptions()) {
            <span class="spinner"></span>
          } @else if (options().length > 0) {
            <select [(ngModel)]="objectId" (ngModelChange)="emit()">
              <option [ngValue]="null">— Elegir —</option>
              @for (o of options(); track o.id) {
                <option [ngValue]="o.id">{{ o.label || o.name || (objectType + ' #' + o.id) }}</option>
              }
            </select>
          } @else {
            <input type="number" min="1" placeholder="ID del objeto"
                   [(ngModel)]="objectId" (ngModelChange)="emit()" />
          }
        </div>
      }
    </div>
  `,
  styles: [`
    .picker { display: flex; gap: 16px; flex-wrap: wrap; }
    .picker-field { display: flex; flex-direction: column; gap: 4px; min-width: 180px; }
    .picker-field label { font-size: 12px; font-weight: 600; color: var(--text-muted); }
    .picker-field select, .picker-field input {
      padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px;
      font-size: 13px; background: var(--surface); color: var(--text);
    }
  `],
})
export class ObjectPickerComponent {
  @Output() picked = new EventEmitter<PickedObject | null>();

  private readonly api = environment.apiUrl;
  private http = inject(HttpClient);
  private auth = inject(AuthService);

  objectType: ChatObjectType | '' = '';
  objectId: number | null = null;
  options = signal<ObjOption[]>([]);
  loadingOptions = signal(false);

  // Best-effort per-(role,type) list endpoints. Unmapped pairs fall back to a numeric ID input,
  // so Support/Admin (who may attach ANY object) still work without a flat listing endpoint.
  private endpointFor(type: ChatObjectType): string | null {
    const role = this.auth.userRole();
    const map: Record<string, Partial<Record<ChatObjectType, string>>> = {
      client: { campaign: '/client/campaigns', booking: '/client/bookings' },
      provider: { space: '/provider/spaces', booking: '/provider/bookings' },
    };
    return map[role ?? '']?.[type] ?? null;
  }

  onTypeChange(): void {
    this.objectId = null;
    this.options.set([]);
    this.emit();
    if (!this.objectType) return;
    const url = this.endpointFor(this.objectType);
    if (!url) return; // numeric fallback
    this.loadingOptions.set(true);
    this.http.get<unknown>(`${this.api}${url}`).subscribe({
      next: (res) => {
        this.options.set(this.normalize(res));
        this.loadingOptions.set(false);
      },
      error: () => {
        this.options.set([]); // degrade to numeric ID input
        this.loadingOptions.set(false);
      },
    });
  }

  private normalize(res: unknown): ObjOption[] {
    const arr = Array.isArray(res)
      ? res
      : (res && Array.isArray((res as { data?: unknown }).data))
        ? (res as { data: unknown[] }).data
        : [];
    return (arr as Record<string, unknown>[])
      .filter(r => r && typeof r['id'] === 'number')
      .map(r => ({ id: r['id'] as number, name: (r['name'] as string) ?? undefined }));
  }

  emit(): void {
    if (this.objectType && this.objectId) {
      const opt = this.options().find(o => o.id === this.objectId);
      const label = opt?.name
        ? `${opt.name}`
        : `${this.objectType} #${this.objectId}`;
      this.picked.emit({ object_type: this.objectType, object_id: this.objectId, label });
    } else {
      this.picked.emit(null);
    }
  }

  reset(): void {
    this.objectType = '';
    this.objectId = null;
    this.options.set([]);
    this.emit();
  }
}
