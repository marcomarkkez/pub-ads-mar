import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';

interface PermissionsOverview {
  permissions: Record<string, Record<string, Record<string, boolean>>>;
  available_roles: string[];
  available_resources: string[];
  available_actions: string[];
}

interface RolePermissions {
  role: string;
  permissions: Record<string, Record<string, boolean>>;
}

@Component({
  selector: 'app-permissions-editor',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './permissions-editor.component.html',
  styleUrl: './permissions-editor.component.scss',
})
export class PermissionsEditorComponent implements OnInit {
  private readonly api = environment.apiUrl;

  loading = signal(false);
  saving = signal(false);
  error = signal('');

  roles = signal<string[]>([]);
  resources = signal<string[]>([]);
  actions = signal<string[]>([]);
  selectedRole = signal<string>('');
  matrix = signal<Record<string, Record<string, boolean>>>({});

  constructor(
    private http: HttpClient,
    private notify: NotificationService,
  ) {}

  ngOnInit(): void {
    this.loadOverview();
  }

  private loadOverview(): void {
    this.loading.set(true);
    this.error.set('');

    this.http.get<PermissionsOverview>(`${this.api}/admin/permissions`).subscribe({
      next: (res) => {
        this.roles.set(res.available_roles);
        this.resources.set(res.available_resources);
        this.actions.set(res.available_actions);

        // Select the first role by default
        if (res.available_roles.length > 0) {
          this.selectRole(res.available_roles[0]);
        } else {
          this.loading.set(false);
        }
      },
      error: (err) => {
        this.loading.set(false);
        this.error.set(err.error?.message || 'Failed to load permissions.');
        this.notify.error('Failed to load permissions.');
      },
    });
  }

  selectRole(role: string): void {
    this.selectedRole.set(role);
    this.loading.set(true);
    this.error.set('');

    this.http.get<RolePermissions>(`${this.api}/admin/permissions/${role}`).subscribe({
      next: (res) => {
        this.matrix.set(res.permissions);
        this.loading.set(false);
      },
      error: (err) => {
        this.loading.set(false);
        this.error.set(err.error?.message || 'Failed to load role permissions.');
        this.notify.error('Failed to load role permissions.');
      },
    });
  }

  isChecked(resource: string, action: string): boolean {
    const m = this.matrix();
    return !!m[resource]?.[action];
  }

  onToggle(resource: string, action: string): void {
    // Toggle the local matrix value
    const m = { ...this.matrix() };
    if (!m[resource]) {
      m[resource] = {};
    }
    m[resource] = { ...m[resource], [action]: !m[resource][action] };
    this.matrix.set(m);

    // Compute enabled actions for this resource
    const enabledActions = this.actions().filter(a => m[resource][a]);

    this.saving.set(true);

    this.http.patch(`${this.api}/admin/permissions/${this.selectedRole()}/${resource}`, {
      actions: enabledActions,
    }).subscribe({
      next: () => {
        this.saving.set(false);
        this.notify.success(`Permissions updated for ${resource}.`);
      },
      error: (err) => {
        this.saving.set(false);
        // Revert the toggle on failure
        m[resource] = { ...m[resource], [action]: !m[resource][action] };
        this.matrix.set(m);
        this.notify.error(err.error?.message || 'Failed to update permission.');
      },
    });
  }
}
