import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';
import { User, PaginatedResponse } from '../../../core/models';

@Component({
  selector: 'app-user-list',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './user-list.component.html',
  styleUrl: './user-list.component.scss',
})
export class UserListComponent implements OnInit {
  private readonly api = environment.apiUrl;

  users = signal<User[]>([]);
  loading = signal(false);
  error = signal('');
  currentPage = signal(1);
  lastPage = signal(1);
  total = signal(0);
  togglingId = signal<number | null>(null);

  constructor(
    private http: HttpClient,
    private notify: NotificationService,
  ) {}

  ngOnInit(): void {
    this.loadUsers();
  }

  loadUsers(page = 1): void {
    this.loading.set(true);
    this.error.set('');

    this.http.get<PaginatedResponse<User>>(`${this.api}/admin/users?page=${page}`).subscribe({
      next: (res) => {
        this.users.set(res.data);
        this.currentPage.set(res.current_page);
        this.lastPage.set(res.last_page);
        this.total.set(res.total);
        this.loading.set(false);
      },
      error: (err) => {
        this.loading.set(false);
        this.error.set(err.error?.message || 'Failed to load users.');
        this.notify.error('Failed to load users.');
      },
    });
  }

  /**
   * §3/§12 (owner 2026-08-03) — there is NO `DELETE /admin/users/{id}` any more: an admin
   * takes a person off their roles, only the account owner deletes an account. The Delete
   * button that used to live here fired at a route that no longer exists (404 every time),
   * and its confirm promised something the platform refuses to do.
   *
   * The capability the admin actually holds is `is_active` on PUT /admin/users/{id}.
   */
  toggleActive(user: User): void {
    const activate = !user.is_active;
    const verb = activate ? 'Reactivate' : 'Deactivate';

    if (!confirm(`${verb} "${user.name}"? Their account and all its data are kept either way.`)) {
      return;
    }

    this.togglingId.set(user.id);

    this.http.put<User>(`${this.api}/admin/users/${user.id}`, { is_active: activate }).subscribe({
      next: (res) => {
        this.users.update(list => list.map(u => (u.id === user.id ? { ...u, ...res } : u)));
        this.notify.success(`User "${user.name}" ${activate ? 'reactivated' : 'deactivated'}.`);
        this.togglingId.set(null);
      },
      error: (err) => {
        this.notify.error(err.error?.message || `Failed to ${verb.toLowerCase()} the user.`);
        this.togglingId.set(null);
      },
    });
  }

  goToPage(page: number): void {
    if (page >= 1 && page <= this.lastPage()) {
      this.loadUsers(page);
    }
  }

  get pages(): number[] {
    const total = this.lastPage();
    const current = this.currentPage();
    const pages: number[] = [];
    const start = Math.max(1, current - 2);
    const end = Math.min(total, current + 2);
    for (let i = start; i <= end; i++) {
      pages.push(i);
    }
    return pages;
  }
}
