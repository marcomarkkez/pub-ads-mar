import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../core/services/auth.service';
import { NotificationService } from '../../core/services/notification.service';
import { Collaborator } from '../../core/models';
import {
  CLIENT_COLLABORATOR_ROLES,
  PROVIDER_COLLABORATOR_ROLES,
  roleBadgeLabel,
  roleDescription,
} from './collaborator-role.helper';

/**
 * design.json §3 / §2 — Collaborators is an ACCOUNT-scoped screen under Configurations,
 * owned by the ACCOUNT OWNER. It used to be a card inside campaign-detail, which was
 * structurally wrong: a collaborator points at an account and acts across ALL of its
 * campaigns, so nesting the screen under one campaign implied a scope that does not
 * exist. It now lives at /collaborators and nowhere else (UC-19).
 *
 * ONE screen for both sides (owner 2026-08-23: "cada uno es como una empresa"), which is
 * why it no longer sits under features/client/: the only thing that differs between a
 * client's account and a provider's is WHICH subroles it may hire, and that is one list.
 *
 * API: account-scoped GET/POST/DELETE /collaborators.
 */
@Component({
  selector: 'app-collaborator-list',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="page-header">
      <h1>Collaborators</h1>
    </div>

    <div class="card" style="margin-bottom:24px;">
      <div class="card-header">
        <h2>Invite a collaborator</h2>
      </div>
      <p style="color:var(--text-muted);font-size:13px;margin-bottom:12px;">
        Collaborators belong to your ACCOUNT, not to a single campaign: they act across all of
        your campaigns, within the limits of their subrole. Invite them by email.
      </p>

      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <input
          type="email"
          [(ngModel)]="newEmail"
          name="newEmail"
          placeholder="Collaborator email..."
          style="flex:1;min-width:220px;padding:8px 12px;border:1px solid var(--border);border-radius:6px;font-size:14px;"
        />
        <!-- §3: the two sides hire from DIFFERENT lists that share no name, so the picker
             is filled from the caller's own side and never from both at once. -->
        <select [(ngModel)]="newRole" name="newRole"
          style="padding:8px 12px;border:1px solid var(--border);border-radius:6px;font-size:14px;">
          @for (role of roles(); track role.value) {
            <option [value]="role.value">{{ role.label }}</option>
          }
        </select>
        <button class="btn btn-sm" style="background:var(--primary);color:#fff;border-color:var(--primary);"
          (click)="addCollaborator()" [disabled]="adding()">
          @if (adding()) { <span class="spinner"></span> } @else { Invite }
        </button>
      </div>
      <p style="color:var(--text-muted);font-size:12px;margin-top:8px;">{{ selectedRoleDescription() }}</p>
    </div>

    <div class="card">
      <div class="card-header">
        <h2>Your collaborators</h2>
      </div>

      @if (loading()) {
        <div class="loading-container"><span class="spinner"></span></div>
      } @else if (error()) {
        <div class="alert alert-error">{{ error() }}</div>
      } @else if (collaborators().length === 0) {
        <div class="empty-state" style="padding:24px;">
          <p>No collaborators yet. Invite one above.</p>
        </div>
      } @else {
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Person</th>
                <th>Email</th>
                <th>Subrole</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              @for (collab of collaborators(); track collab.id) {
                <tr>
                  <td><strong>{{ collab.user?.name || 'Invited' }}</strong></td>
                  <td>{{ collab.email }}</td>
                  <td>
                    <span class="badge badge-in_progress">{{ roleLabel(collab.role) }}</span>
                    <span class="role-note">{{ roleNote(collab.role) }}</span>
                  </td>
                  <td>
                    <span class="badge" [class]="'badge badge-' + (collab.status || 'pending')">
                      {{ collab.status || 'pending' }}
                    </span>
                  </td>
                  <td>
                    @if (collab.status !== 'revoked') {
                      <button class="btn btn-sm btn-danger" (click)="removeCollaborator(collab)">Revoke</button>
                    }
                  </td>
                </tr>
              }
            </tbody>
          </table>
        </div>
      }
    </div>
  `,
  styles: [`
    .role-note {
      display: block;
      font-size: 11px;
      color: var(--text-muted);
      max-width: 340px;
      margin-top: 4px;
    }
  `],
})
export class CollaboratorListComponent implements OnInit {
  private readonly api = environment.apiUrl;
  private http = inject(HttpClient);
  private notify = inject(NotificationService);
  private auth = inject(AuthService);

  /**
   * §3 — the subroles THIS account may hire, which the API validates against the ACCOUNT's
   * type (Collaborator::rolesFor). Offering the other side's list would mint a grant the
   * chat ACL then refuses without saying so — the 2026-07-17 installator bug, mirrored.
   */
  readonly roles = computed(() =>
    this.auth.userRole() === 'provider' ? PROVIDER_COLLABORATOR_ROLES : CLIENT_COLLABORATOR_ROLES,
  );

  readonly roleLabel = roleBadgeLabel;
  readonly roleNote = roleDescription;

  collaborators = signal<Collaborator[]>([]);
  loading = signal(true);
  error = signal('');
  adding = signal(false);

  newEmail = '';
  newRoleValue = signal<string>('');
  get newRole(): string { return this.newRoleValue(); }
  set newRole(value: string) { this.newRoleValue.set(value); }

  selectedRoleDescription = computed(() => roleDescription(this.newRoleValue()));

  ngOnInit(): void {
    // The default is the first subrole of THIS side's list, not a hardcoded 'publicist':
    // a provider submitting that would be refused by the API for a reason the form never
    // showed them, and the `<select>` would have been sitting on a value it never offered.
    this.newRoleValue.set(this.roles()[0].value);
    this.load();
  }

  load(): void {
    this.loading.set(true);
    this.error.set('');
    this.http.get<Collaborator[] | { data: Collaborator[] }>(`${this.api}/collaborators`).subscribe({
      next: (res) => {
        this.collaborators.set(Array.isArray(res) ? res : (res?.data ?? []));
        this.loading.set(false);
      },
      error: (err) => {
        this.loading.set(false);
        this.error.set(err.error?.message || 'Failed to load collaborators.');
      },
    });
  }

  addCollaborator(): void {
    const email = this.newEmail.trim();
    if (!email) {
      this.notify.warning('Please enter an email address.');
      return;
    }
    this.adding.set(true);
    this.http.post<Collaborator>(`${this.api}/collaborators`, {
      email,
      role: this.newRoleValue(),
    }).subscribe({
      next: (res) => {
        this.collaborators.update((list) => [...list, res]);
        this.newEmail = '';
        this.adding.set(false);
        this.notify.success('Collaborator invited.');
      },
      error: (err) => {
        this.adding.set(false);
        this.notify.error(err.error?.message || 'Failed to invite the collaborator.');
      },
    });
  }

  /**
   * §3 UC-19 — DELETE /collaborators/{id} REVOKES; it does not delete the row.
   * "Revoked, not deleted: the grant is the record of who had access and when it was
   * taken away." The list dropping the row was a lie the next reload undid — the
   * collaborator reappeared, now marked `revoked`. Mark it in place instead.
   */
  removeCollaborator(collab: Collaborator): void {
    if (!confirm(`Revoke access for "${collab.email}"? The grant stays on record as revoked.`)) return;
    this.http.delete(`${this.api}/collaborators/${collab.id}`).subscribe({
      next: () => {
        this.collaborators.update((list) =>
          list.map((c) => (c.id === collab.id ? { ...c, status: 'revoked' } : c)),
        );
        this.notify.success('Collaborator revoked.');
      },
      error: (err) => this.notify.error(err.error?.message || 'Failed to remove the collaborator.'),
    });
  }
}
