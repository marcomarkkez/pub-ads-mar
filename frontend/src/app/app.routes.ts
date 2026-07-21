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

      // Client routes
      {
        path: 'client',
        canActivate: [roleGuard('client')],
        children: [
          { path: 'campaigns', loadComponent: () => import('./features/client/campaigns/campaign-list.component').then(m => m.CampaignListComponent) },
          { path: 'campaigns/new', loadComponent: () => import('./features/client/campaigns/campaign-form.component').then(m => m.CampaignFormComponent) },
          { path: 'campaigns/:id', loadComponent: () => import('./features/client/campaigns/campaign-detail.component').then(m => m.CampaignDetailComponent) },
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
          { path: 'config', loadComponent: () => import('./features/admin/config/config.component').then(m => m.ConfigComponent) },
        ],
      },

      // Support has NO dedicated routes — Support acts on chats via the shared /messages
      // surface (design.md §10/§17); the old /support/tickets area is retired.

      // Payments routes — proof-review REMOVED (design.md B9: Payments must not review proof content)
      {
        path: 'payments',
        canActivate: [roleGuard('payments')],
        children: [
          { path: 'review', loadComponent: () => import('./features/payments/payment-list.component').then(m => m.PaymentListComponent) },
        ],
      },

      // Shared Messages — the ONE chat surface for ALL roles (design.md §10/§17; UC-8, UC-9)
      { path: 'messages', loadComponent: () => import('./features/shared/messages/chat-list.component').then(m => m.ChatListComponent) },
      { path: 'messages/new', loadComponent: () => import('./features/shared/messages/chat-new.component').then(m => m.ChatNewComponent) },
      { path: 'messages/:id', loadComponent: () => import('./features/shared/messages/chat-detail.component').then(m => m.ChatDetailComponent) },
    ],
  },

  // Redirects
  { path: '', redirectTo: '/login', pathMatch: 'full' },
  { path: '**', redirectTo: '/dashboard' },
];
