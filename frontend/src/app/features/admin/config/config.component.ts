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
    <div class="page-header">
      <h1>System Configurations</h1>
    </div>

    @if (loading()) {
      <div class="loading-container"><span class="spinner"></span></div>
    }
    @if (error()) {
      <div class="alert alert-error">{{ error() }}</div>
    }

    @if (!loading()) {
      <div class="card">
        <form (ngSubmit)="save()">
          @for (c of configs(); track c.key) {
            <div class="config-row">
              <div class="config-label">
                <label [for]="'cfg-' + c.key">{{ humanize(c.key) }}</label>
                <code class="config-key">{{ c.key }}</code>
              </div>
              <input class="config-input" [id]="'cfg-' + c.key" type="text"
                [(ngModel)]="c.value" [name]="c.key" placeholder="Not set" />
            </div>
          }

          @if (configs().length === 0) {
            <p class="empty-hint">No configurations defined.</p>
          }

          <label class="apply-scope">
            <input type="checkbox" [(ngModel)]="applyToInFlight" name="apply_scope" />
            <span>Apply to in-flight bookings too</span>
          </label>

          <button type="submit" class="btn btn-primary" [disabled]="saving()">
            @if (saving()) { <span class="spinner"></span> } @else { Save configurations }
          </button>
        </form>
      </div>
    }
  `,
  styles: [`
    .card { max-width: 720px; }
    .config-row {
      display: flex;
      align-items: center;
      gap: 16px;
      padding: 14px 0;
      border-bottom: 1px solid var(--border);
    }
    .config-row:first-child { padding-top: 0; }
    .config-label { flex: 0 0 240px; display: flex; flex-direction: column; gap: 2px; }
    .config-label label { font-weight: 600; font-size: 14px; color: var(--text); }
    .config-key {
      font-family: monospace;
      font-size: 11px;
      color: var(--text-muted);
      background: var(--hover);
      padding: 1px 6px;
      border-radius: 4px;
      align-self: flex-start;
    }
    .config-input {
      flex: 1;
      padding: 8px 12px;
      border: 1px solid var(--border);
      border-radius: 6px;
      font-size: 14px;
    }
    .empty-hint { color: var(--text-muted); font-size: 13px; }
    .apply-scope {
      display: flex;
      align-items: center;
      gap: 8px;
      margin: 20px 0;
      font-size: 14px;
      color: var(--text);
    }
    .apply-scope input { width: auto; }
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

  /** Turn a config key like "proof_deadline_days" into "Proof Deadline Days". */
  humanize(key: string): string {
    return key
      .replace(/[_-]+/g, ' ')
      .trim()
      .replace(/\b\w/g, (c) => c.toUpperCase());
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
