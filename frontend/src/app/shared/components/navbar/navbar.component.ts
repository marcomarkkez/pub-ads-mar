import { Component, computed, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink, RouterLinkActive } from '@angular/router';
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
  user = this.auth.user;
  role = this.auth.userRole;
  menuOpen = false;
  initials = computed(() => {
    const name = this.user()?.name || '';
    return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
  });

  logout(): void {
    this.auth.logout().subscribe();
  }
}
