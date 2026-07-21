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
      <h1><a routerLink="/messages" class="back-link">&larr;</a> Abrir un chat</h1>
    </div>

    @if (error()) {
      <div class="alert alert-error">{{ error() }}</div>
    }

    <div class="card">
      <form (ngSubmit)="onSubmit()">
        <div class="form-group">
          <label for="subject">Asunto (opcional)</label>
          <input id="subject" type="text" name="subject" [(ngModel)]="subject"
                 placeholder="Motivo del chat…" />
        </div>

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
            @if (submitting()) { <span class="spinner"></span> Enviando… } @else { Abrir chat }
          </button>
        </div>
      </form>
    </div>
  `,
  styles: [`
    .back-link { text-decoration: none; color: var(--primary); margin-right: 8px; }
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

  subject = '';
  message = '';
  picked = signal<PickedObject | null>(null);
  locked = signal(false);
  submitting = signal(false);
  error = signal('');

  ngOnInit(): void {
    // Prefill + lock the object when arriving from a per-item "open a chat" button.
    const qp = this.route.snapshot.queryParamMap;
    const type = qp.get('object_type') as ChatObjectType | null;
    const id = qp.get('object_id');
    const subject = qp.get('subject');
    if (subject) this.subject = subject;
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
    const payload: Record<string, unknown> = { message: this.message.trim() };
    if (this.subject.trim()) payload['subject'] = this.subject.trim();
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
