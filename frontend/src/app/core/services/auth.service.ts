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
   * BR-8 · §3 — /login and /register now answer with the SAME account block as /me, so
   * the session starts COMPLETE: the sidebar computes off `isAccountOwner()` on the very
   * first render after login, with no reload and no second request.
   *
   * Before this, the account context only ever arrived through /me, and /me only ran in
   * the APP_INITIALIZER — i.e. on a full page load. A user who had just logged in lost
   * their Collaborators tab until they refreshed the browser.
   *
   * The `account_id === undefined` branch is the compatibility path, not dead code: the
   * API change ships in the same MVP but not necessarily in the same deploy, and against
   * an older backend "no account block" must mean "go ask /me", never "not an owner".
   * `null` is a real answer (staff have no account) and is honoured as one.
   */
  private startSession(res: AuthResponse): void {
    localStorage.setItem(this.TOKEN_KEY, res.token);
    this.currentUser.set(res.user);
    this.permissions.set(res.permissions);

    if (res.account_id === undefined) {
      this.loadUser().subscribe();
      return;
    }

    this.applyAccountContext(res);
  }

  /**
   * The ONE writer of the account signals. /me and login/register feed the same three
   * fields, so they go through the same setter — two copies of this would be two chances
   * for the screens to disagree about who owns what.
   */
  private applyAccountContext(res: {
    account_id?: number | null;
    is_owner?: boolean;
    collaborating_on?: number[];
  }): void {
    this.accountIdSig.set(res.account_id ?? null);
    this.isOwnerSig.set(!!res.is_owner);
    this.collaboratingOnSig.set(res.collaborating_on ?? []);
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

  /**
   * §3 · UC-20 — one accepted invitation, folded into the live session.
   *
   * `collaborating_on` is filled from the `accepted` rows, so the instant
   * POST /collaborations/{id}/accept returns 200 the session's copy of it is stale. This
   * takes the SAME path login/register take: the response that already carries the
   * authoritative fact (the accepted row's `account.id`) writes the signal, instead of a
   * /me round trip whose only job would be to tell us what we were just told.
   *
   * Idempotent, because accepting twice is the same fact stated twice (the endpoint says
   * so explicitly) and a duplicate id in this list would double-count nothing usefully.
   */
  noteCollaborationAccepted(accountId: number): void {
    this.collaboratingOnSig.update(ids => (ids.includes(accountId) ? ids : [...ids, accountId]));
  }

  /**
   * Drop the session WITHOUT calling /logout. Used after DELETE /account succeeds
   * outright: the user row and its Sanctum tokens are already gone server-side, so
   * POST /logout would 401 on a token that no longer exists and the only honest move
   * left is local. Everything else must keep using logout().
   */
  endSessionLocally(): void {
    this.clearSession();
  }

  loadUser(): Observable<MeResponse | null> {
    if (!this.token) return of(null);
    return this.http.get<MeResponse>(`${this.api}/me`).pipe(
      tap(res => {
        this.currentUser.set(res.user);
        this.permissions.set(res.permissions);
        // BR-8 — same three signals login/register write, through the same setter.
        this.applyAccountContext(res);
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
