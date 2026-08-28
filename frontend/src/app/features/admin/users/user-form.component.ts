import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink, ActivatedRoute } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';
import { User } from '../../../core/models';

@Component({
  selector: 'app-user-form',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './user-form.component.html',
  styleUrl: './user-form.component.scss',
})
export class UserFormComponent implements OnInit {
  private readonly api = environment.apiUrl;

  userId: number | null = null;
  isEdit = false;
  loading = signal(false);
  saving = signal(false);
  error = signal('');

  // Form fields
  name = '';
  email = '';
  password = '';
  role: 'client' | 'provider' | 'admin' | 'support' | 'payments' = 'client';
  phone = '';
  company_name = '';
  address = '';
  is_active = true;

  readonly roles = ['client', 'provider', 'admin', 'support', 'payments'] as const;

  constructor(
    private http: HttpClient,
    private router: Router,
    private route: ActivatedRoute,
    private notify: NotificationService,
  ) {}

  ngOnInit(): void {
    const idParam = this.route.snapshot.paramMap.get('id');
    if (idParam) {
      this.userId = Number(idParam);
      this.isEdit = true;
      this.loadUser();
    }
  }

  get isProvider(): boolean {
    return this.role === 'provider';
  }

  get pageTitle(): string {
    return this.isEdit ? 'Edit User' : 'New User';
  }

  private loadUser(): void {
    this.loading.set(true);
    this.error.set('');

    // Admin\UserController::show() returns the User model bare — `response()->json($user)` —
    // exactly like store()/update() and like the rows inside index()'s paginator. Only
    // POST /login wraps its user in `{ user: … }`, and this component was written against
    // that shape: `res.user` came back undefined and the read of `.name` threw inside the
    // subscriber, so `loading` never cleared and /admin/users/{id} span forever (WALK-6 W6-5).
    this.http.get<User>(`${this.api}/admin/users/${this.userId}`).subscribe({
      next: (user) => {
        this.name = user.name;
        this.email = user.email;
        this.role = user.role;
        this.phone = user.phone || '';
        this.company_name = user.company_name || '';
        this.address = user.address || '';
        this.is_active = user.is_active;
        this.loading.set(false);
      },
      error: (err) => {
        this.loading.set(false);
        this.error.set(err.error?.message || 'Failed to load user.');
        this.notify.error('Failed to load user.');
      },
    });
  }

  onSubmit(): void {
    // Basic validation
    if (!this.name || !this.email) {
      this.error.set('Name and email are required.');
      return;
    }
    if (!this.isEdit && !this.password) {
      this.error.set('Password is required for new users.');
      return;
    }
    if (this.isProvider && (!this.company_name || !this.address || !this.phone)) {
      this.error.set('Company name, address, and phone are required for providers.');
      return;
    }

    this.saving.set(true);
    this.error.set('');

    const payload: Record<string, unknown> = {
      name: this.name,
      email: this.email,
      role: this.role,
      phone: this.phone || null,
      company_name: this.company_name || null,
      address: this.address || null,
      is_active: this.is_active,
    };

    if (this.password) {
      payload['password'] = this.password;
    }

    const request$ = this.isEdit
      ? this.http.put(`${this.api}/admin/users/${this.userId}`, payload)
      : this.http.post(`${this.api}/admin/users`, payload);

    request$.subscribe({
      next: () => {
        this.notify.success(this.isEdit ? 'User updated successfully.' : 'User created successfully.');
        this.router.navigate(['/admin/users']);
      },
      error: (err) => {
        this.saving.set(false);
        const msg = err.error?.message || 'Failed to save user.';
        const fieldErrors = err.error?.errors;
        if (fieldErrors) {
          const first = Object.values(fieldErrors).flat()[0] as string;
          this.error.set(first || msg);
        } else {
          this.error.set(msg);
        }
      },
    });
  }
}
