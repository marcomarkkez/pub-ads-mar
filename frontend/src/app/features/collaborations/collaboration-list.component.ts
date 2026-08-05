import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { CollaborationService } from '../../core/services/collaboration.service';
import { NotificationService } from '../../core/services/notification.service';
import { Collaboration } from '../../core/models';
import { parseApiError } from '../../core/models/api-error';
import {
  roleBadgeLabel,
  roleDescription,
} from '../client/collaborators/collaborator-role.helper';

/**
 * design.json §3 · UC-19/UC-20 · WALK-6 step 3 — "aceptar la invitación desde la otra
 * sesión", which until now could not be done from the application at all.
 *
 * This is the RECEIVING end and it is not the Collaborators screen: there the account
 * owner invites and revokes people on their OWN account; here I answer an invitation into
 * someone ELSE's. The API keeps the two apart for the same reason (/collaborations is not
 * under the /client prefix), and so does this screen.
 *
 * TWO RULES ARE LOAD-BEARING HERE, both stated on the screen and enforced by it:
 *
 *  1. ACCEPTING IS NOT REVERSIBLE (BR-15, owner 2026-08-04). It is said BEFORE the button
 *     is pressed, because a warning shown afterwards is not a warning, it is an apology.
 *  2. DECLINE EXISTS ONLY WHILE `pending`. Once accepted the endpoint answers 409 and
 *     points at Support, so an accepted row gets no button at all — EH-8: a control that
 *     exists only to be refused reads as a permission the platform does not grant.
 */
@Component({
  selector: 'app-collaboration-list',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="page-header">
      <h1>Invitations</h1>
    </div>

    <!-- The spinner only replaces the list when there is no list yet: a re-read after an
         answer must not blank out what the person is reading. -->
    @if (loading() && invitations().length === 0) {
      <div class="loading-container"><span class="spinner"></span></div>
    } @else if (error() && invitations().length === 0) {
      <div class="alert alert-error">{{ error() }}</div>
    } @else if (invitations().length === 0) {
      <div class="empty-state">
        <div class="empty-icon">&#128233;</div>
        <p>You have no invitations. When an account owner invites you to collaborate, it shows up here.</p>
      </div>
    } @else {
      @if (pending().length) {
        <p class="lead">
          Someone invited you to help run their account. Read what the subrole can do before you answer:
          accepting puts you inside somebody else's account, and you cannot take yourself back out.
        </p>
      }

      @for (inv of invitations(); track inv.id) {
        <div class="card invite" [class.invite--accepted]="inv.status === 'accepted'">
          <div class="invite-head">
            <h2>{{ inv.account?.name || 'Account #' + inv.account_id }}</h2>
            @if (inv.status === 'accepted') {
              <span class="badge badge-active">Accepted</span>
            } @else {
              <span class="badge badge-stale">Pending</span>
            }
          </div>

          <div class="rows">
            <div class="row">
              <span class="k">Subrole</span>
              <span class="v">{{ roleLabel(inv.role) }}</span>
            </div>
            @if (roleHelp(inv.role)) {
              <div class="row">
                <span class="k"></span>
                <span class="v sub">{{ roleHelp(inv.role) }}</span>
              </div>
            }
            @if (inv.invited_by?.name) {
              <div class="row"><span class="k">Invited by</span><span class="v">{{ inv.invited_by?.name }}</span></div>
            }
            <div class="row"><span class="k">Invited on</span><span class="v">{{ inv.created_at | date:'mediumDate' }}</span></div>
            <div class="row"><span class="k">Sent to</span><span class="v">{{ inv.email }}</span></div>
          </div>

          @if (refusal()[inv.id]) {
            <div class="alert alert-error">{{ refusal()[inv.id] }}</div>
          }

          @if (inv.status === 'pending') {
            <!-- BR-15 said BEFORE the click, never after it. -->
            <div class="warn-box">
              <strong>Accepting cannot be undone.</strong>
              Once you accept, you act inside this account and only its owner can remove you again.
              There is no way to leave a collaboration on your own. Declining, on the other hand,
              costs nothing: you were never in.
            </div>
            <div class="actions">
              <button class="btn btn-primary" style="width:auto" [disabled]="busyId() === inv.id"
                      (click)="accept(inv)">Accept and join</button>
              <button class="btn" style="width:auto" [disabled]="busyId() === inv.id"
                      (click)="decline(inv)">Decline</button>
            </div>
          } @else {
            <!-- EH-8 — no Decline button here: the endpoint answers 409 for an accepted
                 row, and a button that exists only to be refused reads as a permission. -->
            <p class="sub locked">
              You accepted this collaboration, so it can no longer be declined. Only the owner of the
              account can revoke your access; leaving it yourself has to go through Support, and that
              is a capability the platform does not have yet.
            </p>
          }
        </div>
      }
    }
  `,
  styles: [`
    .lead { color: var(--text-muted); font-size: 14px; max-width: 70ch; line-height: 1.55; }
    .invite { margin-bottom: 20px; }
    .invite--accepted { border-left: 4px solid #16a34a; }
    .invite-head { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
    .invite-head h2 { margin: 0; font-size: 1.1rem; }
    .rows { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
    .row { display: flex; gap: 16px; font-size: 14px; }
    .k { color: var(--text-muted); min-width: 110px; }
    .v { font-weight: 500; }
    .v.sub { font-weight: 400; color: var(--text-muted); max-width: 62ch; line-height: 1.45; }
    .sub { color: var(--text-muted); font-size: 13px; }
    .locked { max-width: 70ch; line-height: 1.5; margin: 0; }
    .warn-box {
      background: #fffbeb;
      border: 1px solid #fcd34d;
      border-radius: 6px;
      padding: 10px 14px;
      font-size: 13px;
      line-height: 1.5;
      max-width: 72ch;
      margin-bottom: 14px;
    }
    .actions { display: flex; gap: 8px; }
  `],
})
export class CollaborationListComponent implements OnInit {
  private collabs = inject(CollaborationService);
  private notify = inject(NotificationService);

  invitations = this.collabs.invitations;
  pending = computed(() => this.invitations().filter(i => i.status === 'pending'));

  // Both come from the service: the sidebar and this screen read ONE list, so accepting
  // here updates the menu without a second request.
  loading = this.collabs.loading;
  error = this.collabs.loadError;
  busyId = signal<number | null>(null);
  /** The API's own refusal sentence, per invitation. It knows the state; this screen does not. */
  refusal = signal<Record<number, string>>({});

  roleLabel = roleBadgeLabel;
  roleHelp = roleDescription;

  ngOnInit(): void {
    // force: the sidebar may have loaded this list minutes ago, and an invitation that
    // arrived since is exactly what someone opening this screen came to look at.
    this.collabs.refresh(true);
  }

  accept(inv: Collaboration): void {
    const ok = confirm(
      'Accept this invitation?\n\n' +
      'This cannot be undone. You will act inside this account, and only its owner can remove you.'
    );
    if (!ok) return;

    this.busyId.set(inv.id);
    this.clearRefusal(inv.id);

    this.collabs.accept(inv).subscribe({
      next: () => {
        this.busyId.set(null);
        this.notify.success('Invitation accepted. You can now act inside this account.');
      },
      error: (err: HttpErrorResponse) => {
        this.busyId.set(null);
        this.showRefusal(inv.id, err);
      },
    });
  }

  decline(inv: Collaboration): void {
    const ok = confirm('Decline this invitation? The owner would have to invite you again.');
    if (!ok) return;

    this.busyId.set(inv.id);
    this.clearRefusal(inv.id);

    this.collabs.decline(inv).subscribe({
      next: () => {
        this.busyId.set(null);
        this.notify.success('Invitation declined.');
      },
      error: (err: HttpErrorResponse) => {
        this.busyId.set(null);
        this.showRefusal(inv.id, err);
      },
    });
  }

  /**
   * A 409 here always means the row moved under us (revoked by the owner, or already
   * answered elsewhere). The message is shown verbatim and the list is re-read, so the
   * buttons on screen go back to matching what the endpoint will accept.
   */
  private showRefusal(id: number, err: HttpErrorResponse): void {
    const { message } = parseApiError(err);
    this.refusal.update(m => ({ ...m, [id]: message }));
    this.notify.error(message);
    this.collabs.refresh(true);
  }

  private clearRefusal(id: number): void {
    this.refusal.update(m => { const n = { ...m }; delete n[id]; return n; });
  }
}
