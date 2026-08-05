import { Routes } from '@angular/router';
import { authGuard, guestGuard } from './core/guards/auth.guard';
import { roleGuard } from './core/guards/role.guard';
import { AuthLayoutComponent } from './shared/layouts/auth-layout/auth-layout.component';
import { MainLayoutComponent } from './shared/layouts/main-layout/main-layout.component';

export const routes: Routes = [
  // Auth pages (guest only)
  {
    path: '',
    component: AuthLayoutComponent,
    canActivate: [guestGuard],
    children: [
      { path: '', redirectTo: 'login', pathMatch: 'full' },
      { path: 'login', loadComponent: () => import('./features/auth/login/login.component').then(m => m.LoginComponent) },
      { path: 'register', loadComponent: () => import('./features/auth/register/register.component').then(m => m.RegisterComponent) },
    ],
  },

  // Authenticated pages
  {
    path: '',
    component: MainLayoutComponent,
    canActivate: [authGuard],
    children: [
      { path: 'dashboard', loadComponent: () => import('./features/dashboard/dashboard.component').then(m => m.DashboardComponent) },

      // design.json §3 · UC-37 — the owner's OWN account (GET/DELETE /account,
      // POST /account/cancel-deletion). Not under /client or /provider because the API
      // is not either: the routes are gated `role:client,provider` and act on the
      // caller, with no {id} to point at anybody else. Staff have no account at all.
      {
        path: 'account',
        canActivate: [roleGuard('client', 'provider')],
        loadComponent: () => import('./features/account/account.component').then(m => m.AccountComponent),
      },

      // design.json §3 · UC-19/UC-20 (WALK-6 step 3) — invitations addressed to ME.
      // Top-level and role:client,provider for the same reason /collaborations is not
      // under the /client prefix: answering an invitation is an act on somebody ELSE's
      // account, so it cannot be scoped by the caller's own. NOT under /client also
      // because provider-side subroles exist and a provider must be able to answer.
      {
        path: 'collaborations',
        canActivate: [roleGuard('client', 'provider')],
        loadComponent: () => import('./features/collaborations/collaboration-list.component')
          .then(m => m.CollaborationListComponent),
      },

      // Client routes
      {
        path: 'client',
        canActivate: [roleGuard('client')],
        children: [
          { path: 'campaigns', loadComponent: () => import('./features/client/campaigns/campaign-list.component').then(m => m.CampaignListComponent) },
          { path: 'campaigns/new', loadComponent: () => import('./features/client/campaigns/campaign-form.component').then(m => m.CampaignFormComponent) },
          { path: 'campaigns/:id', loadComponent: () => import('./features/client/campaigns/campaign-detail.component').then(m => m.CampaignDetailComponent) },
          // design.json §2/§3 (UC-19) — Collaborators is ACCOUNT-scoped and belongs to the
          // account OWNER, so it is its own screen, NOT a card inside one campaign.
          { path: 'collaborators', loadComponent: () => import('./features/client/collaborators/collaborator-list.component').then(m => m.CollaboratorListComponent) },
          { path: 'spaces', loadComponent: () => import('./features/client/spaces/space-search.component').then(m => m.SpaceSearchComponent) },
          { path: 'bookings', loadComponent: () => import('./features/client/bookings/booking-list.component').then(m => m.BookingListComponent) },
          { path: 'invoices', loadComponent: () => import('./features/client/invoices/invoice-list.component').then(m => m.InvoiceListComponent) },
          { path: 'wallet', loadComponent: () => import('./features/client/wallet/wallet.component').then(m => m.WalletComponent) },
        ],
      },

      // Provider routes
      {
        path: 'provider',
        canActivate: [roleGuard('provider')],
        children: [
          { path: 'spaces', loadComponent: () => import('./features/provider/spaces/space-list.component').then(m => m.SpaceListComponent) },
          { path: 'spaces/new', loadComponent: () => import('./features/provider/spaces/space-form.component').then(m => m.SpaceFormComponent) },
          { path: 'spaces/:id', loadComponent: () => import('./features/provider/spaces/space-detail.component').then(m => m.SpaceDetailComponent) },
          { path: 'spaces/:id/edit', loadComponent: () => import('./features/provider/spaces/space-form.component').then(m => m.SpaceFormComponent) },
          { path: 'bookings', loadComponent: () => import('./features/provider/bookings/provider-booking-list.component').then(m => m.ProviderBookingListComponent) },
          { path: 'proofs', loadComponent: () => import('./features/provider/proofs/proof-list.component').then(m => m.ProofListComponent) },
        ],
      },

      // Admin routes
      {
        path: 'admin',
        canActivate: [roleGuard('admin')],
        children: [
          { path: 'users', loadComponent: () => import('./features/admin/users/user-list.component').then(m => m.UserListComponent) },
          { path: 'users/new', loadComponent: () => import('./features/admin/users/user-form.component').then(m => m.UserFormComponent) },
          { path: 'users/:id', loadComponent: () => import('./features/admin/users/user-form.component').then(m => m.UserFormComponent) },
          { path: 'permissions', loadComponent: () => import('./features/admin/permissions/permissions-editor.component').then(m => m.PermissionsEditorComponent) },
          { path: 'oversight', loadComponent: () => import('./features/admin/oversight/oversight.component').then(m => m.OversightComponent) },
          // UC-29 + UC-32 · design.json §12/§8 — the ONLY screen where Admin writes:
          // takedown/restore, freeze/unfreeze, payout stop and clawback. All audited.
          { path: 'moderation', loadComponent: () => import('./features/admin/moderation/moderation.component').then(m => m.AdminModerationComponent) },
          // UC-31 · design.json §12 — the immutable audit log (read-only).
          { path: 'audit', loadComponent: () => import('./features/admin/audit/audit-log.component').then(m => m.AuditLogComponent) },
          { path: 'config', loadComponent: () => import('./features/admin/config/config.component').then(m => m.ConfigComponent) },
        ],
      },

      // Support has NO dedicated routes — Support acts on chats via the shared /messages
      // surface (design.json §10/§17); the old /support/tickets area is retired.

      // Payments routes — proof-review REMOVED (design.json B9: Payments must not review proof content)
      {
        path: 'payments',
        canActivate: [roleGuard('payments')],
        children: [
          { path: 'review', loadComponent: () => import('./features/payments/payment-list.component').then(m => m.PaymentListComponent) },
        ],
      },

      // Shared Messages — the ONE chat surface for ALL roles (design.json §10/§17; UC-8, UC-9)
      { path: 'messages', loadComponent: () => import('./features/shared/messages/chat-list.component').then(m => m.ChatListComponent) },
      { path: 'messages/new', loadComponent: () => import('./features/shared/messages/chat-new.component').then(m => m.ChatNewComponent) },
      { path: 'messages/:id', loadComponent: () => import('./features/shared/messages/chat-detail.component').then(m => m.ChatDetailComponent) },
    ],
  },

  // Redirects
  { path: '', redirectTo: '/login', pathMatch: 'full' },
  { path: '**', redirectTo: '/dashboard' },
];
