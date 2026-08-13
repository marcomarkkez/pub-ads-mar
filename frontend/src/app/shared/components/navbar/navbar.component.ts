import { Component, computed, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { CommonModule } from '@angular/common';
import { NavigationStart, Router, RouterLink, RouterLinkActive } from '@angular/router';
import { filter } from 'rxjs';
import { AuthService } from '../../../core/services/auth.service';

@Component({
  selector: 'app-navbar',
  standalone: true,
  imports: [CommonModule, RouterLink, RouterLinkActive],
  templateUrl: './navbar.component.html',
  styleUrl: './navbar.component.scss',
})
export class NavbarComponent {
  private auth = inject(AuthService);
  private router = inject(Router);
  user = this.auth.user;
  role = this.auth.userRole;
  /** Plain property on purpose: the click handler runs inside the zone, so the `@if`
   *  repaints without a signal. Verified end to end in `e2e-navbar.mjs` — don't "fix"
   *  this by reading it. */
  menuOpen = false;
  initials = computed(() => {
    const name = this.user()?.name || '';
    return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
  });

  constructor() {
    // An open menu paints a FULL-SCREEN `.backdrop` whose only job is to catch the next
    // click and shut the menu. The bar sits above that backdrop, so its own links (the
    // PubAds logo, Soporte) stay clickable while the menu is open — and following one
    // used to carry the backdrop onto the next screen, where it silently swallowed every
    // click until the user clicked once "at nothing". Leaving a route closes the menu.
    this.router.events
      .pipe(filter(e => e instanceof NavigationStart), takeUntilDestroyed())
      .subscribe(() => { this.menuOpen = false; });
  }

  logout(): void {
    this.auth.logout().subscribe();
  }
}
