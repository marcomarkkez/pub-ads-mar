import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';

interface SystemConfig {
  id: number;
  key: string;
  value: string | null;
  updated_by_user_id: number | null;
}

@Component({
  selector: 'app-config',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="config-page">
      <h2>System Configurations</h2>

      <p *ngIf="loading()">Loading…</p>
      <p class="error" *ngIf="error()">{{ error() }}</p>

      <form *ngIf="!loading()" (ngSubmit)="save()">
        <div class="config-row" *ngFor="let c of configs()">
          <label [for]="'cfg-' + c.key">{{ c.key }}</label>
          <input [id]="'cfg-' + c.key" type="text" [(ngModel)]="c.value" [name]="c.key" />
        </div>

        <label class="apply-scope">
          <input type="checkbox" [(ngModel)]="applyToInFlight" name="apply_scope" />
          Apply to in-flight bookings too
        </label>

        <button type="submit" [disabled]="saving()">
          {{ saving() ? 'Saving…' : 'Save configurations' }}
        </button>
      </form>
    </div>
  `,
  styles: [`
    .config-page { max-width: 640px; padding: 1rem; }
    .config-row { display: flex; align-items: center; gap: 1rem; margin-bottom: .75rem; }
    .config-row label { flex: 0 0 220px; font-family: monospace; }
    .config-row input { flex: 1; padding: .4rem .6rem; }
    .apply-scope { display: flex; align-items: center; gap: .5rem; margin: 1rem 0; }
    .error { color: #c0392b; }
    button { padding: .5rem 1rem; }
  `],
})
export class ConfigComponent implements OnInit {
  private readonly api = environment.apiUrl;

  loading = signal(false);
  saving = signal(false);
  error = signal('');
  configs = signal<SystemConfig[]>([]);
  applyToInFlight = false;

  constructor(
    private http: HttpClient,
    private notify: NotificationService,
  ) {}

  ngOnInit(): void {
    this.load();
  }

  private load(): void {
    this.loading.set(true);
    this.error.set('');

    this.http.get<SystemConfig[]>(`${this.api}/admin/configurations`).subscribe({
      next: (res) => {
        this.configs.set(res);
        this.loading.set(false);
      },
      error: (err) => {
        this.loading.set(false);
        this.error.set(err.error?.message || 'Failed to load configurations.');
        this.notify.error('Failed to load configurations.');
      },
    });
  }

  save(): void {
    this.saving.set(true);

    const payload = {
      configs: this.configs().map((c) => ({ key: c.key, value: c.value })),
      apply_scope: this.applyToInFlight ? 'all' : 'new_only',
    };

    this.http.put<SystemConfig[]>(`${this.api}/admin/configurations`, payload).subscribe({
      next: (res) => {
        this.configs.set(res);
        this.saving.set(false);
        this.notify.success('Configurations saved.');
      },
      error: (err) => {
        this.saving.set(false);
        this.notify.error(err.error?.message || 'Failed to save configurations.');
      },
    });
  }
}
