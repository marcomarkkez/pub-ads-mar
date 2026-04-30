import { Component, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';
import { NotificationService } from '../../../core/services/notification.service';

@Component({
  selector: 'app-register',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './register.component.html',
  styleUrl: './register.component.scss',
})
export class RegisterComponent {
  name = '';
  email = '';
  password = '';
  passwordConfirmation = '';
  role: 'client' | 'provider' = 'client';
  loading = signal(false);
  error = signal('');

  constructor(
    private auth: AuthService,
    private router: Router,
    private notify: NotificationService,
  ) {}

  onSubmit(): void {
    if (!this.name || !this.email || !this.password || !this.passwordConfirmation) {
      this.error.set('Please fill in all fields.');
      return;
    }
    if (this.password !== this.passwordConfirmation) {
      this.error.set('Passwords do not match.');
      return;
    }
    this.loading.set(true);
    this.error.set('');

    this.auth.register({
      name: this.name,
      email: this.email,
      password: this.password,
      password_confirmation: this.passwordConfirmation,
      role: this.role,
    }).subscribe({
      next: () => {
        this.notify.success('Account created successfully!');
        this.router.navigate(['/dashboard']);
      },
      error: (err) => {
        this.loading.set(false);
        const msg = err.error?.message || 'Registration failed.';
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
