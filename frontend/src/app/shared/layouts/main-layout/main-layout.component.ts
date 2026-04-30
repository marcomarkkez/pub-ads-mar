import { Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { NavbarComponent } from '../../components/navbar/navbar.component';
import { SidebarComponent } from '../../components/sidebar/sidebar.component';
import { NotificationToastComponent } from '../../components/notification-toast/notification-toast.component';

@Component({
  selector: 'app-main-layout',
  standalone: true,
  imports: [RouterOutlet, NavbarComponent, SidebarComponent, NotificationToastComponent],
  template: `
    <app-navbar />
    <div class="layout-body">
      <app-sidebar />
      <main class="main-content">
        <router-outlet />
      </main>
    </div>
    <app-notification-toast />
  `,
  styles: [`
    :host {
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }
    .layout-body {
      display: flex;
      flex: 1;
    }
    .main-content {
      flex: 1;
      padding: 24px;
      overflow-y: auto;
      background: var(--bg);
    }
  `],
})
export class MainLayoutComponent {}
