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
        ],
      },

      // Support routes
      {
        path: 'support',
        canActivate: [roleGuard('support')],
        children: [
          { path: 'tickets', loadComponent: () => import('./features/support/ticket-list.component').then(m => m.SupportTicketListComponent) },
          { path: 'tickets/:id', loadComponent: () => import('./features/support/ticket-detail.component').then(m => m.SupportTicketDetailComponent) },
        ],
      },

      // Payments routes
      {
        path: 'payments',
        canActivate: [roleGuard('payments')],
        children: [
          { path: 'review', loadComponent: () => import('./features/payments/payment-list.component').then(m => m.PaymentListComponent) },
          { path: 'proofs', loadComponent: () => import('./features/payments/proof-review-list.component').then(m => m.ProofReviewListComponent) },
        ],
      },

      // Shared routes (any authenticated user)
      { path: 'conversations', loadComponent: () => import('./features/shared/conversations/conversation-list.component').then(m => m.ConversationListComponent) },
      { path: 'conversations/:id', loadComponent: () => import('./features/shared/conversations/conversation-detail.component').then(m => m.ConversationDetailComponent) },
      { path: 'tickets', loadComponent: () => import('./features/shared/tickets/ticket-list.component').then(m => m.TicketListComponent) },
      { path: 'tickets/new', loadComponent: () => import('./features/shared/tickets/ticket-form.component').then(m => m.TicketFormComponent) },
      { path: 'tickets/:id', loadComponent: () => import('./features/shared/tickets/ticket-detail.component').then(m => m.TicketDetailComponent) },
    ],
  },

  // Redirects
  { path: '', redirectTo: '/login', pathMatch: 'full' },
  { path: '**', redirectTo: '/dashboard' },
];
