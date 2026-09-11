import { Injectable, computed, inject, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, tap } from 'rxjs';
import { environment } from '../../../environments/environment';
import { Collaboration } from '../models';
import { AuthService } from './auth.service';

/**
 * design.json §3 (UC-19/UC-20 · WALK-6 step 3) — the invitations addressed to ME.
 *
 * WHY THIS IS A SERVICE and not just state inside the screen: the sidebar has to know
 * whether there is anything to attend BEFORE the screen is opened — an entry that is
 * always there is noise, and an entry that is never there is the dead end this closes.
 * So the list lives in one place and both the sidebar and the screen read the same
 * signal; accepting on the screen updates the sidebar with no second request.
 */
@Injectable({ providedIn: 'root' })
export class CollaborationService {
  private readonly api = environment.apiUrl;
  private http = inject(HttpClient);
  private auth = inject(AuthService);

  private list = signal<Collaboration[]>([]);

  /**
   * WHOSE invitations are in `list`, by user id — NOT a boolean "already loaded".
   *
   * It was a boolean, and this service is `providedIn: 'root'`, so it outlives the
   * session: `AuthService.clearSession()` drops the token and ROUTER-navigates to /login
   * (no page reload), which leaves this singleton untouched. Sign out, sign in as somebody
   * else in the same tab, and the sidebar's `refresh()` hit `if (loaded) return` and never
   * asked the API about the person now holding the session — so the second user got the
   * FIRST user's list, for the rest of the tab's life:
   *
   *   • invited provider2 from client1's account, signed in as provider2 in the same tab →
   *     no 📨 Invitations entry at all, because client1's (empty) list was still cached.
   *     Only a hard reload — the one thing a person walking WALK-6 has no reason to do —
   *     brought it back. Marco hit exactly this by hand (2026-09-05); the API was right
   *     the whole way down, which is why it read as "the invitation never arrived".
   *   • and the mirror of it: sign in as somebody WITH invitations, sign out, sign in as
   *     somebody without → the menu badges a count belonging to the previous user.
   *
   * Keying the cache on the identity it was always implicitly about makes both cases the
   * same one question ("is this list the current user's?"), which cannot answer stale.
   */
  private loadedFor = signal<number | null>(null);
  private loadingSig = signal(false);
  private errorSig = signal('');

  readonly invitations = this.list.asReadonly();
  readonly loading = this.loadingSig.asReadonly();
  /** Set when the last load failed. The SCREEN shows it; the sidebar ignores it. */
  readonly loadError = this.errorSig.asReadonly();

  /** Invitations I can still act on. The sidebar badges THIS number. */
  readonly pendingCount = computed(() => this.list().filter(i => i.status === 'pending').length);

  /**
   * Whether the sidebar entry exists at all.
   *
   * Non-empty, not "has pending": an ACCEPTED collaboration is a standing fact about
   * which accounts I can act inside, and hiding it the moment I accept would delete the
   * only screen that says so. A person who was never invited to anything still sees
   * nothing, which is the rule that matters.
   */
  readonly hasAny = computed(() => this.list().length > 0);

  /**
   * GET /collaborations. Staff are skipped rather than 403'd: the routes are
   * `role:client,provider`, so for admin/support/payments the request is a guaranteed
   * refusal, and a console full of expected 403s hides the unexpected ones.
   */
  refresh(force = false): void {
    const role = this.auth.userRole();
    const me = this.auth.user()?.id ?? null;

    if (!this.auth.isLoggedIn() || (role !== 'client' && role !== 'provider')) {
      this.list.set([]);
      // Signed out (or staff): nothing is cached for anybody, so the next session starts
      // by asking instead of by trusting whatever the last one left behind.
      this.loadedFor.set(null);
      return;
    }

    if (this.loadedFor() === me && !force) return;

    // A list belonging to somebody else is not "stale data", it is the wrong person's —
    // drop it BEFORE the request rather than after it, or the sidebar renders the previous
    // user's count for as long as the fetch takes. A forced refresh for the SAME user
    // keeps what is on screen: that one really is just a re-read.
    if (this.loadedFor() !== me) this.list.set([]);

    this.loadingSig.set(true);
    this.errorSig.set('');

    this.http.get<Collaboration[]>(`${this.api}/collaborations`).subscribe({
      next: (res) => {
        this.list.set(Array.isArray(res) ? res : []);
        this.loadedFor.set(me);
        this.loadingSig.set(false);
      },
      // No toast here on purpose: this feeds a MENU entry, and a menu that pops a red
      // error on every page load because one optional list failed is worse than a menu
      // that is briefly missing an entry. The failure is recorded in `loadError` so the
      // screen — where the person is actually looking for it — can say so.
      error: (err) => {
        this.errorSig.set(err?.error?.message || 'Could not load your invitations.');
        // Stamped with the CURRENT user all the same: a failed load is still an answer
        // about this session, and re-asking on every navigation would turn one failure
        // into a request per page. The screen's own refresh(true) is the retry.
        this.loadedFor.set(me);
        this.loadingSig.set(false);
      },
    });
  }

  accept(invitation: Collaboration): Observable<Collaboration> {
    return this.http.post<Collaboration>(
      `${this.api}/collaborations/${invitation.id}/accept`, {}
    ).pipe(tap(res => {
      this.replace(res);
      // The accepted row's account is the one I may now act inside — write it into the
      // session immediately (AuthService.noteCollaborationAccepted), no reload.
      const accountId = res.account?.id ?? res.account_id;
      if (accountId) this.auth.noteCollaborationAccepted(accountId);
    }));
  }

  decline(invitation: Collaboration): Observable<Collaboration> {
    return this.http.post<Collaboration>(
      `${this.api}/collaborations/${invitation.id}/decline`, {}
    ).pipe(tap(res => {
      // A declined row leaves the list for good: GET /collaborations returns only
      // pending + accepted, so keeping it would make this screen disagree with its
      // own endpoint on the next load.
      this.list.update(l => l.filter(i => i.id !== res.id));
    }));
  }

  private replace(row: Collaboration): void {
    this.list.update(l => l.map(i => (i.id === row.id ? { ...i, ...row } : i)));
  }
}
