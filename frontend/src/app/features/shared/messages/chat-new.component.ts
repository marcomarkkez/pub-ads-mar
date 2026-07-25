import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NotificationService } from '../../../core/services/notification.service';
import { Chat, ChatObjectType } from '../../../core/models';
import { ObjectPickerComponent, PickedObject } from './object-picker.component';

// UC-9 · design.md §10/§17 — "contact support" is simply "open a chat" (objectless or
// object-attached via the two dropdowns). POST /chats, then POST /chats/{c}/objects if needed.
@Component({
  selector: 'app-chat-new',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink, ObjectPickerComponent],
  template: `
    <div class="page-header">
      <h1><a routerLink="/messages" class="back-link">&larr;</a> {{ heading() }}</h1>
    </div>

    @if (target === 'provider') {
      <p class="lead">Escríbele al proveedor sobre este espacio. Se adjunta como contexto de la conversación.</p>
    } @else {
      <p class="lead">Inicias un chat con el equipo de soporte. Puedes adjuntar un objeto
        (anuncio, espacio, reserva…) para darles contexto.</p>
    }

    @if (error()) {
      <div class="alert alert-error">{{ error() }}</div>
    }

    <div class="card">
      <form (ngSubmit)="onSubmit()">
        <div class="form-group">
          <label>Adjuntar un objeto (opcional)</label>
          <p class="hint">Elige el tipo de objeto y luego el objeto específico (p. ej. Ads → “Mi Ad #1”).</p>
          <app-object-picker (picked)="onPicked($event)"></app-object-picker>
          @if (locked()) {
            <p class="hint">Objeto adjunto desde el contexto: <strong>{{ picked()?.label }}</strong></p>
          }
        </div>

        <div class="form-group">
          <label for="message">Mensaje inicial</label>
          <textarea id="message" name="message" rows="4" [(ngModel)]="message"
                    placeholder="Escribe tu mensaje…" required></textarea>
        </div>

        <div class="form-actions">
          <a routerLink="/messages" class="btn">Cancelar</a>
          <button type="submit" class="btn btn-primary submit-btn" [disabled]="submitting()">
            @if (submitting()) { <span class="spinner"></span> Enviando… }
            @else { {{ target === 'provider' ? 'Enviar al proveedor' : 'Enviar a soporte' }} }
          </button>
        </div>
      </form>
    </div>
  `,
  styles: [`
    .back-link { text-decoration: none; color: var(--primary); margin-right: 8px; }
    .lead { color: var(--text-muted); margin: -8px 0 16px; font-size: 14px; }
    .hint { font-size: 12px; color: var(--text-muted); margin: 4px 0 8px; }
    .form-actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px; }
    .submit-btn { width: auto; }
  `],
})
export class ChatNewComponent implements OnInit {
  private readonly api = environment.apiUrl;
  private http = inject(HttpClient);
  private router = inject(Router);
  private route = inject(ActivatedRoute);
  private notify = inject(NotificationService);

  message = '';
  picked = signal<PickedObject | null>(null);
  locked = signal(false);
  submitting = signal(false);
  error = signal('');
  // UC-8/UC-9 · design.md §10 — the ENTRY POINT sets the counterparty. 'provider' arrives only
  // from a published listing (space attached); everything else is 'support'.
  target: 'support' | 'provider' = 'support';

  heading(): string {
    return this.target === 'provider' ? 'Mensaje al proveedor' : 'Chat con soporte';
  }

  ngOnInit(): void {
    // Prefill + lock the object when arriving from a per-item "open a chat" button.
    const qp = this.route.snapshot.queryParamMap;
    if (qp.get('target') === 'provider') this.target = 'provider';
    const type = qp.get('object_type') as ChatObjectType | null;
    const id = qp.get('object_id');
    if (type && id) {
      this.picked.set({ object_type: type, object_id: Number(id), label: `${type} #${id}` });
      this.locked.set(true);
    }
  }

  onPicked(obj: PickedObject | null): void {
    if (this.locked()) return; // context object wins
    this.picked.set(obj);
  }

  onSubmit(): void {
    if (!this.message.trim()) {
      this.error.set('El mensaje inicial es obligatorio.');
      return;
    }
    this.submitting.set(true);
    this.error.set('');

    const obj = this.picked();
    // The API expects `body` for the opening message; `target` routes the counterparty (§10/§17).
    const payload: Record<string, unknown> = { body: this.message.trim(), target: this.target };
    if (obj) {
      payload['object_type'] = obj.object_type;
      payload['object_id'] = obj.object_id;
    }

    this.http.post<Chat | { data: Chat }>(`${this.api}/chats`, payload).subscribe({
      next: (res) => {
        const chat = (res as { data?: Chat }).data ?? (res as Chat);
        this.notify.success('Chat abierto.');
        this.router.navigate(['/messages', chat.id]);
      },
      error: (err) => {
        this.submitting.set(false);
        this.error.set(err.error?.message || 'No se pudo abrir el chat.');
        this.notify.error('No se pudo abrir el chat.');
      },
    });
  }
}
