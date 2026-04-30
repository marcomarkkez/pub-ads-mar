import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';
import { Space } from '../../../core/models';

@Component({
  selector: 'app-space-list',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="page-header">
      <h1>My Spaces</h1>
      <a routerLink="/provider/spaces/new" class="btn btn-primary" style="width:auto">+ Add Space</a>
    </div>

    @if (loading()) {
      <div class="loading-container"><span class="spinner"></span></div>
    } @else if (error()) {
      <div class="alert alert-error">{{ error() }}</div>
    } @else if (spaces().length === 0) {
      <div class="empty-state">
        <div class="empty-icon">&#x1F4CD;</div>
        <p>You haven't created any spaces yet.</p>
        <a routerLink="/provider/spaces/new" class="btn btn-primary" style="width:auto;margin-top:16px">Create your first space</a>
      </div>
    } @else {
      <div class="card">
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Name</th>
                <th>Type</th>
                <th>Location</th>
                <th>Price/Day</th>
                <th>Active</th>
                <th>Photos</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @for (space of spaces(); track space.id) {
                <tr>
                  <td>
                    <a [routerLink]="['/provider/spaces', space.id]" class="link">{{ space.name }}</a>
                  </td>
                  <td>
                    <span class="badge" [class]="'badge type-' + space.type">
                      {{ formatType(space.type) }}
                    </span>
                  </td>
                  <td>{{ space.location_name || (space.latitude + ', ' + space.longitude) }}</td>
                  <td>{{ space.price_per_day | currency:'EUR' }}</td>
                  <td>
                    <span class="badge" [class.badge-active]="space.is_active" [class.badge-cancelled]="!space.is_active">
                      {{ space.is_active ? 'Active' : 'Inactive' }}
                    </span>
                  </td>
                  <td>{{ space.photos?.length || 0 }}</td>
                  <td class="actions">
                    <a [routerLink]="['/provider/spaces', space.id]" class="btn btn-sm">View</a>
                    <a [routerLink]="['/provider/spaces', space.id, 'edit']" class="btn btn-sm">Edit</a>
                    <button class="btn btn-sm btn-danger" (click)="confirmDelete(space)" [disabled]="deleting()">
                      Delete
                    </button>
                  </td>
                </tr>
              }
            </tbody>
          </table>
        </div>
      </div>
    }
  `,
  styles: [`
    .link {
      color: var(--primary);
      text-decoration: none;
      font-weight: 600;
      &:hover { text-decoration: underline; }
    }
    .actions {
      display: flex;
      gap: 6px;
      white-space: nowrap;
    }
    .type-billboard { background: #dbeafe; color: #1e40af; }
    .type-big_screen { background: #ede9fe; color: #5b21b6; }
    .type-little_screen { background: #fce7f3; color: #9d174d; }
    .type-radio_station { background: #fef3c7; color: #92400e; }
    .type-other { background: #f3f4f6; color: #374151; }
  `],
})
export class SpaceListComponent implements OnInit {
  spaces = signal<Space[]>([]);
  loading = signal(false);
  error = signal('');
  deleting = signal(false);

  private readonly api = environment.apiUrl;

  constructor(
    private http: HttpClient,
    private notify: NotificationService,
  ) {}

  ngOnInit(): void {
    this.loadSpaces();
  }

  loadSpaces(): void {
    this.loading.set(true);
    this.error.set('');

    this.http.get<Space[]>(`${this.api}/provider/spaces`).subscribe({
      next: (res) => {
        this.spaces.set(res);
        this.loading.set(false);
      },
      error: (err) => {
        this.error.set(err.error?.message || 'Failed to load spaces.');
        this.loading.set(false);
      },
    });
  }

  confirmDelete(space: Space): void {
    if (!confirm(`Are you sure you want to delete "${space.name}"? This action cannot be undone.`)) {
      return;
    }
    this.deleting.set(true);

    this.http.delete(`${this.api}/provider/spaces/${space.id}`).subscribe({
      next: () => {
        this.spaces.update(list => list.filter(s => s.id !== space.id));
        this.notify.success(`Space "${space.name}" deleted.`);
        this.deleting.set(false);
      },
      error: (err) => {
        this.notify.error(err.error?.message || 'Failed to delete space.');
        this.deleting.set(false);
      },
    });
  }

  formatType(type: string): string {
    return type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
  }
}
