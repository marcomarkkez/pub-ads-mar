import { HttpInterceptorFn, HttpErrorResponse } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { AuthService } from '../services/auth.service';
import { NotificationService } from '../services/notification.service';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AuthService);
  const router = inject(Router);
  const notify = inject(NotificationService);

  let authReq = req;
  const token = auth.token;
  if (token) {
    authReq = req.clone({
      setHeaders: { Authorization: `Bearer ${token}` },
    });
  }

  return next(authReq).pipe(
    catchError((error: HttpErrorResponse) => {
      if (error.status === 401) {
        router.navigate(['/login']);
      } else if (error.status === 403) {
        notify.error('You do not have permission to perform this action.');
      } else if (error.status === 422) {
        const messages = error.error?.errors;
        if (messages) {
          const first = Object.values(messages).flat()[0] as string;
          if (first) notify.error(first);
        }
      } else if (error.status >= 500) {
        notify.error('Server error. Please try again later.');
      }
      return throwError(() => error);
    })
  );
};
