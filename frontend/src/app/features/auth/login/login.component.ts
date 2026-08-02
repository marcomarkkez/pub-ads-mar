import { Component, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';
import { NotificationService } from '../../../core/services/notification.service';
import { parseApiError } from '../../../core/models/api-error';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './login.component.html',
  styleUrl: './login.component.scss',
})
export class LoginComponent {
  email = '';
  password = '';
  loading = signal(false);
  error = signal('');

  constructor(
    private auth: AuthService,
    private router: Router,
    private notify: NotificationService,
  ) {}

  onSubmit(): void {
    if (!this.email || !this.password) {
      this.error.set('Please fill in all fields.');
      return;
    }
    this.loading.set(true);
    this.error.set('');

    this.auth.login({ email: this.email, password: this.password }).subscribe({
      next: () => {
        this.notify.success('Welcome back!');
        this.router.navigateByUrl(this.auth.landingRoute());
      },
      error: (err: HttpErrorResponse) => {
        this.loading.set(false);
        const { code, message } = parseApiError(err);
        this.error.set(`${message} (${code})`);
      },
    });
  }
}
