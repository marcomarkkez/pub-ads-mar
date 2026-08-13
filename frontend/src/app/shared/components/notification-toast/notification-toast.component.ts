import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { NotificationService } from '../../../core/services/notification.service';

@Component({
  selector: 'app-notification-toast',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="toast-container">
      @for (n of notify.items(); track n.id) {
        <div class="toast" [attr.data-type]="n.type">
          <span class="toast-msg">{{ n.message }}</span>
          <button type="button" class="toast-close" aria-label="Cerrar"
                  (click)="notify.dismiss(n.id)">&times;</button>
        </div>
      }
    </div>
  `,
  styles: [`
    .toast-container {
      position: fixed;
      /* Below the navbar (z 1100) so a toast never overlaps the user menu / logout.
         The 72px offset alone did NOT achieve that, and the old z-index of 9999 made
         it worse: the bar is 56px tall, but the OPEN user menu hangs below it down to
         about y150 — straight through this column, which is up to 400px wide and
         pinned to the same right edge. At 9999 the toasts painted OVER the open
         dropdown and ate the click on "Sign Out", so pressing the avatar looked like
         it did nothing at all. The dropdown cannot outrank a toast on its own — it is
         trapped inside the navbar host's stacking context, so any z-index it carries
         only orders it WITHIN the bar — so the toast column is what has to yield.
         1050 still clears everything else: modals 500, Leaflet map 1000, sidebar 90. */
      top: 72px;
      right: 16px;
      z-index: 1050;
      display: flex;
      flex-direction: column;
      gap: 8px;
      max-width: 400px;
    }
    .toast {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 12px 16px;
      border-radius: 8px;
      font-size: 14px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.15);
      animation: slideIn 0.2s ease;

      &[data-type="success"] { background: #e8f5e9; color: #2e7d32; }
      &[data-type="error"]   { background: #fce4ec; color: #c62828; }
      &[data-type="warning"] { background: #fff3e0; color: #e65100; }
      &[data-type="info"]    { background: #e3f2fd; color: #1565c0; }
    }
    .toast-msg { flex: 1; }
    .toast-close {
      border: none;
      background: none;
      font-size: 18px;
      cursor: pointer;
      opacity: 0.6;
      color: inherit;
      &:hover { opacity: 1; }
    }
    @keyframes slideIn {
      from { transform: translateX(100%); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
  `],
})
export class NotificationToastComponent {
  constructor(public notify: NotificationService) {}
}
