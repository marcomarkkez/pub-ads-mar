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

  deleteUser(user: User): void {
    if (!confirm(`Are you sure you want to delete "${user.name}"? This action cannot be undone.`)) {
      return;
    }

    this.http.delete(`${this.api}/admin/users/${user.id}`).subscribe({
      next: () => {
        this.notify.success(`User "${user.name}" deleted successfully.`);
        this.loadUsers(this.currentPage());
      },
      error: (err) => {
        this.notify.error(err.error?.message || 'Failed to delete user.');
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
