import { Injectable, signal, computed } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { Observable, tap, catchError, of } from 'rxjs';
import { environment } from '../../../environments/environment';
import { User, LoginRequest, RegisterRequest, AuthResponse, MeResponse, Permissions } from '../models';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly api = environment.apiUrl;
  private readonly TOKEN_KEY = 'auth_token';

  private currentUser = signal<User | null>(null);
  private permissions = signal<Permissions>({});

  readonly user = this.currentUser.asReadonly();
  readonly isLoggedIn = computed(() => !!this.currentUser());
  readonly userRole = computed(() => this.currentUser()?.role ?? null);

  /** Landing route after login, by role. Clients land on the Spaces map, not the dashboard. */
  landingRoute(role: string | null = this.userRole()): string {
    return role === 'client' ? '/client/spaces' : '/dashboard';
  }

  constructor(private http: HttpClient, private router: Router) {}

  get token(): string | null {
    return localStorage.getItem(this.TOKEN_KEY);
  }

  login(credentials: LoginRequest): Observable<AuthResponse> {
    return this.http.post<AuthResponse>(`${this.api}/login`, credentials).pipe(
      tap(res => {
        localStorage.setItem(this.TOKEN_KEY, res.token);
        this.currentUser.set(res.user);
        this.permissions.set(res.permissions);
      })
    );
  }

  register(data: RegisterRequest): Observable<AuthResponse> {
    return this.http.post<AuthResponse>(`${this.api}/register`, data).pipe(
      tap(res => {
        localStorage.setItem(this.TOKEN_KEY, res.token);
        this.currentUser.set(res.user);
        this.permissions.set(res.permissions);
      })
    );
  }

  logout(): Observable<void> {
    return this.http.post<void>(`${this.api}/logout`, {}).pipe(
      tap(() => this.clearSession()),
      catchError(() => {
        this.clearSession();
        return of(undefined as unknown as void);
      })
    );
  }

  loadUser(): Observable<MeResponse | null> {
    if (!this.token) return of(null);
    return this.http.get<MeResponse>(`${this.api}/me`).pipe(
      tap(res => {
        this.currentUser.set(res.user);
        this.permissions.set(res.permissions);
      }),
      catchError(() => {
        this.clearSession();
        return of(null);
      })
    );
  }

  hasPermission(resource: string, action: string): boolean {
    return !!this.permissions()[`${resource}.${action}`];
  }

  getPermissions(): Permissions {
    return this.permissions();
  }

  private clearSession(): void {
    localStorage.removeItem(this.TOKEN_KEY);
    this.currentUser.set(null);
    this.permissions.set({});
    this.router.navigate(['/login']);
  }
}
