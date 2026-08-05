import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../core/services/auth.service';
import { NotificationService } from '../../core/services/notification.service';
import { Account, AccountDeletionResponse } from '../../core/models';
import { ApiErrorCode, Blocker, parseApiError, parseBlockers } from '../../core/models/api-error';

/**
 * design.json §3 · AD-delguard-09 · UC-37 — the account owner's own account screen.
 *
 * BR-10: the endpoints (GET /account, DELETE /account, POST /account/cancel-deletion)
 * existed with no UI at all in front of them, which is the same gap as a dead button
 * read from the other end — a capability the platform grants and the person cannot use.
 *
 * The whole point of this screen is that DELETE has THREE outcomes and each one has to
 * look different, because they mean different things to the person reading them:
 *
 *   409 + blockers[]   an OPEN DISPUTE. Absolute; the confirmation cannot buy it. The
 *                      blockers are listed verbatim, because "no" without naming what is
 *                      holding the account leaves the owner with nothing to act on.
 *   409 CONFIRMATION_REQUIRED
 *                      the owner is about to destroy the proofs they uploaded, and
 *                      without proof of display no payment can be released to them. The
 *                      count comes from the API, never from a guess here.
 *   200 + account      PROGRAMMED, not performed: objects are still in use (blockers
 *                      says which), the account is unpublished and a purge date is set.
 *                      Nothing has been destroyed, and cancel-deletion undoes it.
 *   200 without account
 *                      actually deleted. The user row and its tokens are gone server
 *                      side, so the session is dropped locally (see endSessionLocally).
 */
@Component({
  selector: 'app-account',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="page-header">
      <h1>Account</h1>
    </div>

    @if (loading()) {
      <div class="loading-container"><span class="spinner"></span></div>
    } @else if (error()) {
      <div class="alert alert-error">{{ error() }}</div>
    } @else {
      <!-- The alias form is only legal on a leading @if, never on an @else if. -->
      @if (account(); as acc) {
      <div class="card" style="margin-bottom:24px;">
        <div class="card-header"><h2>{{ acc.name }}</h2></div>
        <div class="rows">
          <div class="row"><span class="k">Type</span><span class="v">{{ acc.type }}</span></div>
          <div class="row">
            <span class="k">Status</span>
            <span class="v">
              @if (isScheduled()) {
                <span class="badge badge-cancelled">Unpublished</span>
              } @else {
                <span class="badge badge-active">Published</span>
              }
            </span>
          </div>
          @if (acc.campaigns_count !== undefined) {
            <div class="row"><span class="k">Campaigns</span><span class="v">{{ acc.campaigns_count }}</span></div>
          }
          @if (acc.collaborators_count !== undefined) {
            <div class="row"><span class="k">Collaborators</span><span class="v">{{ acc.collaborators_count }}</span></div>
          }
          <div class="row"><span class="k">Member since</span><span class="v">{{ acc.created_at | date:'mediumDate' }}</span></div>
        </div>
      </div>

      @if (isScheduled()) {
        <div class="card scheduled" style="margin-bottom:24px;">
          <h2>A deletion is programmed on this account</h2>
          <p>
            Your account left circulation on the spot &mdash; every listing it owns is out of the
            catalog. Nothing has been destroyed: the purge runs no earlier than
            <strong>{{ acc.delete_scheduled_at | date:'medium' }}</strong>, a person runs it by hand,
            and it re-checks every reason below before touching anything.
          </p>
          @if (acc.proof_loss_confirmed_at) {
            <p class="warn">
              You confirmed that the purge destroys the proofs you uploaded. Cancelling the deletion
              takes that confirmation back; programming another one asks you again.
            </p>
          }
          @if (blockers().length) {
            <p class="sub">It was programmed instead of performed because:</p>
            <ul class="blockers">
              @for (b of blockers(); track b.kind) {
                <li>{{ b.message }}</li>
              }
            </ul>
          }
          <button class="btn" style="width:auto" [disabled]="busy()" (click)="cancelDeletion()">
            Cancel programmed deletion
          </button>
        </div>
      } @else {
        <div class="card danger" style="margin-bottom:24px;">
          <h2>Delete this account</h2>
          <p>
            Only you can delete your account &mdash; an admin cannot, they can only take you off your
            roles. If anything is still attached to it, the deletion is <strong>programmed</strong>
            rather than performed: the account is unpublished now and destroyed later, and you can
            cancel until then.
          </p>

          @if (refusal()) {
            <div class="alert alert-error">{{ refusal() }}</div>
          }

          @if (blockers().length) {
            <p class="sub">What is holding this account:</p>
            <ul class="blockers">
              @for (b of blockers(); track b.kind) {
                <li>{{ b.message }}</li>
              }
            </ul>
          }

          @if (proofsAtRisk() !== null) {
            <div class="confirm-box">
              <p>
                Deleting your account destroys the <strong>{{ proofsAtRisk() }}</strong> proof(s) you
                uploaded. A proof is what a payout is argued from: without it a payment cannot be
                released to you.
              </p>
              <label class="check">
                <input type="checkbox" [(ngModel)]="confirmProofLoss" name="confirmProofLoss" />
                I understand my proofs will be destroyed and that no payment can be released without them.
              </label>
            </div>
          }

          <button class="btn btn-danger" style="width:auto" [disabled]="busy() || !canSubmitDelete()"
                  (click)="requestDeletion()">
            @if (proofsAtRisk() !== null) { Delete anyway } @else { Delete my account }
          </button>
        </div>
      }
      }
    }
  `,
  styles: [`
    .rows { display: flex; flex-direction: column; gap: 8px; }
    .row { display: flex; gap: 16px; font-size: 14px; }
    .k { color: var(--text-muted); min-width: 140px; }
    .v { font-weight: 500; text-transform: capitalize; }
    .card.danger { border-left: 4px solid #b91c1c; }
    .card.scheduled { border-left: 4px solid #d97706; }
    .card h2 { margin-top: 0; }
    .card p { line-height: 1.55; font-size: 14px; }
    .sub { color: var(--text-muted); font-size: 13px; margin-bottom: 4px; }
    .warn { color: #92400e; }
    .blockers { margin: 0 0 16px; padding-left: 20px; color: #991b1b; font-size: 13px; }
    .blockers li { margin-top: 4px; }
    .confirm-box {
      background: #fef2f2;
      border: 1px solid #fecaca;
      border-radius: 6px;
      padding: 12px 16px;
      margin-bottom: 16px;
    }
    .check { display: flex; gap: 8px; align-items: flex-start; font-size: 13px; }
    .btn-danger { background: #b91c1c; color: #fff; border-color: #b91c1c; }
  `],
})
export class AccountComponent implements OnInit {
  private readonly api = environment.apiUrl;
  private http = inject(HttpClient);
  private auth = inject(AuthService);
  private notify = inject(NotificationService);

  account = signal<Account | null>(null);
  loading = signal(false);
  busy = signal(false);
  error = signal('');
  /** The 409/200 `blockers[]` of the last attempt — never invented locally. */
  blockers = signal<Blocker[]>([]);
  /** The refusal sentence, shown verbatim: the API knows why better than this screen. */
  refusal = signal('');
  /** Proof count from a CONFIRMATION_REQUIRED refusal; null = not asked (yet). */
  proofsAtRisk = signal<number | null>(null);
  confirmProofLoss = false;

  isScheduled = computed(() => !!this.account()?.delete_scheduled_at);

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    this.loading.set(true);
    this.error.set('');

    this.http.get<Account>(`${this.api}/account`).subscribe({
      next: (res) => {
        this.account.set(res);
        this.loading.set(false);
      },
      error: (err: HttpErrorResponse) => {
        this.loading.set(false);
        this.error.set(parseApiError(err).message);
      },
    });
  }

  /**
   * The checkbox only gates the second attempt. Before the API has asked for it there is
   * nothing to confirm, so requiring it up front would be this screen inventing a rule.
   */
  canSubmitDelete(): boolean {
    return this.proofsAtRisk() === null || this.confirmProofLoss;
  }

  requestDeletion(): void {
    const ok = confirm(
      'Delete your account?\n\n' +
      'If anything is still attached to it the deletion is programmed, not performed, and you ' +
      'can cancel it until the purge runs.'
    );
    if (!ok) return;

    this.busy.set(true);
    this.refusal.set('');
    this.blockers.set([]);

    // Angular sends a body on DELETE only through this option; the API reads
    // `confirm_proof_loss` from the request body (AccountController::destroy).
    this.http.delete<AccountDeletionResponse>(`${this.api}/account`, {
      body: { confirm_proof_loss: this.confirmProofLoss },
    }).subscribe({
      next: (res) => {
        this.busy.set(false);

        if (res.account) {
          // Programmed, not performed. The blockers are the REASON, so they stay on
          // screen next to the date instead of vanishing with a toast.
          this.account.set(res.account);
          this.blockers.set(res.blockers ?? []);
          this.proofsAtRisk.set(null);
          this.confirmProofLoss = false;
          this.notify.success('Account unpublished and programmed for deletion. Nothing has been destroyed.');
          return;
        }

        this.notify.success('Account deleted.');
        this.auth.endSessionLocally();
      },
      error: (err: HttpErrorResponse) => {
        this.busy.set(false);
        const { code, message } = parseApiError(err);

        if (code === ApiErrorCode.ConfirmationRequired) {
          this.proofsAtRisk.set(Number(err.error?.proofs ?? 0));
          this.confirmProofLoss = false;
          this.refusal.set(message);
          return;
        }

        if (code === ApiErrorCode.AlreadyExists) {
          // A deletion is already programmed — re-read it rather than describe it here.
          this.refusal.set(message);
          this.load();
          return;
        }

        // OBJECT_IN_USE (an open dispute) and anything else: show the sentence AND the
        // list. BR-10 — a refusal that cannot say what is blocking it is a dead end.
        this.refusal.set(message);
        this.blockers.set(parseBlockers(err));
        this.notify.error(message);
      },
    });
  }

  cancelDeletion(): void {
    this.busy.set(true);

    this.http.post<{ message: string; account: Account }>(
      `${this.api}/account/cancel-deletion`, {}
    ).subscribe({
      next: (res) => {
        this.busy.set(false);
        this.account.set(res.account);
        this.blockers.set([]);
        this.refusal.set('');
        this.proofsAtRisk.set(null);
        this.confirmProofLoss = false;
        this.notify.success('Programmed deletion cancelled.');
      },
      error: (err: HttpErrorResponse) => {
        this.busy.set(false);
        this.notify.error(parseApiError(err).message);
      },
    });
  }
}
