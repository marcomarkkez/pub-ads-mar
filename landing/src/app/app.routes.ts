import { Routes } from '@angular/router';

/**
 * Cuatro rutas y ni una mas (owner 2026-08-05): la pagina, los dos textos legales que
 * un formulario de alta obliga a tener, y el acuse. Todo lo demas de la landing es una
 * seccion anclada dentro de la misma pagina, no una ruta.
 */
export const routes: Routes = [
  { path: '', loadComponent: () => import('./pages/landing.component').then((m) => m.LandingComponent) },
  { path: 'gracias', loadComponent: () => import('./pages/thank-you.component').then((m) => m.ThankYouComponent) },
  { path: 'terminos', loadComponent: () => import('./pages/terms.component').then((m) => m.TermsComponent) },
  { path: 'privacidad', loadComponent: () => import('./pages/privacy.component').then((m) => m.PrivacyComponent) },
  { path: '**', redirectTo: '' },
];
