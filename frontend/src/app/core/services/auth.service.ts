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

  // BR-8 · §3 — the ACCOUNT CONTEXT, straight from GET /me. Before this the SPA had no
  // owner signal at all and approximated it with the permission map.
  private accountIdSig = signal<number | null>(null);
  private isOwnerSig = signal(false);
  private collaboratingOnSig = signal<number[]>([]);

  readonly user = this.currentUser.asReadonly();
  readonly isLoggedIn = computed(() => !!this.currentUser());
  readonly userRole = computed(() => this.currentUser()?.role ?? null);

  /** The caller's OWN account id; null for staff roles, which have no account. */
  readonly accountId = this.accountIdSig.asReadonly();

  /**
   * §3 — the caller owns an account (`account_id !== null`). Under the MVP unique index
   * on `users.account_id` this is true for EVERY registered client and provider, so it
   * means "has an account of their own", not "is not a collaborator".
   */
  readonly isAccountOwner = this.isOwnerSig.asReadonly();

  /** Accounts belonging to OTHER people that this user collaborates on (accepted). */
  readonly collaboratingOn = this.collaboratingOnSig.asReadonly();

  /** True when this human ALSO acts as someone else's collaborator. Never exclusive
   *  with `isAccountOwner()` — the two roles stack on one person. */
  readonly isCollaborator = computed(() => this.collaboratingOnSig().length > 0);

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
      tap(res => this.startSession(res)),
    );
  }

  register(data: RegisterRequest): Observable<AuthResponse> {
    return this.http.post<AuthResponse>(`${this.api}/register`, data).pipe(
      tap(res => this.startSession(res)),
    );
  }

  /**
   * /login and /register return the user + permissions but NOT the account context
   * (BR-8: account_id / is_owner / collaborating_on live on /me). The APP_INITIALIZER
   * only runs on a full page load, so without this follow-up a user who just logged in
   * would carry an empty account context for the whole session.
   */
  private startSession(res: AuthResponse): void {
    localStorage.setItem(this.TOKEN_KEY, res.token);
    this.currentUser.set(res.user);
    this.permissions.set(res.permissions);
    this.loadUser().subscribe();
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
        // BR-8 — only /me carries the account context; login/register do not, which is
        // why they chain a loadUser() above. Without it a freshly logged-in client had
        // no account signal until the next full page load.
        this.accountIdSig.set(res.account_id ?? null);
        this.isOwnerSig.set(!!res.is_owner);
        this.collaboratingOnSig.set(res.collaborating_on ?? []);
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
    this.accountIdSig.set(null);
    this.isOwnerSig.set(false);
    this.collaboratingOnSig.set([]);
    this.router.navigate(['/login']);
  }
}
