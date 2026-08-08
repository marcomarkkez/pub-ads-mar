import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { SignupService } from './signup.service';

/**
 * El formulario basico del alta. Pide lo minimo que `POST /api/register` exige y ni un
 * campo mas: nombre, email, contraseña y de que lado del marketplace viene.
 *
 * El selector de rol no es un adorno. Este marketplace tiene dos entradas —quien busca
 * espacio y quien lo tiene— y el backend guarda esa eleccion en `users.role`, que decide
 * el menu entero desde el primer login. Preguntarlo aqui evita que alguien se de de alta
 * como cliente y descubra que queria ser proveedor.
 */
@Component({
  selector: 'ld-signup-form',
  standalone: true,
  imports: [ReactiveFormsModule],
  template: `
    <form class="signup" [formGroup]="form" (ngSubmit)="submit()" novalidate>
      <div class="roles">
        <label class="role" [class.on]="form.value.role === 'client'">
          <input type="radio" formControlName="role" value="client">
          <span class="role-t">Quiero anunciarme</span>
          <span class="role-d">Busco espacios donde poner mi campaña</span>
        </label>
        <label class="role" [class.on]="form.value.role === 'provider'">
          <input type="radio" formControlName="role" value="provider">
          <span class="role-t">Tengo espacios</span>
          <span class="role-d">Quiero rentabilizar mis muros, vallas o locales</span>
        </label>
      </div>

      <div class="field">
        <label for="name">Nombre</label>
        <input id="name" formControlName="name" autocomplete="name" placeholder="Marco Márquez">
        @if (touched('name')) { <p class="err">Dinos cómo llamarte.</p> }
      </div>

      <div class="field">
        <label for="email">Email</label>
        <input id="email" type="email" formControlName="email" autocomplete="email" placeholder="marco@empresa.com">
        @if (touched('email')) { <p class="err">Necesitamos un email válido.</p> }
      </div>

      <div class="field">
        <label for="company">Empresa <span class="opt">opcional</span></label>
        <input id="company" formControlName="company_name" autocomplete="organization" placeholder="Publicidad del Norte">
      </div>

      <div class="field">
        <label for="password">Contraseña</label>
        <input id="password" type="password" formControlName="password" autocomplete="new-password" placeholder="Mínimo 8 caracteres">
        @if (touched('password')) { <p class="err">Ocho caracteres como mínimo.</p> }
      </div>

      <button class="btn btn-lg" type="submit" [disabled]="sending()">
        {{ sending() ? 'Creando tu cuenta…' : 'Crear cuenta gratis' }}
      </button>

      @if (failure()) { <p class="err form-err">{{ failure() }}</p> }

      <p class="legal">
        Al crear la cuenta aceptas los
        <a href="/terminos">términos y condiciones</a> y la
        <a href="/privacidad">política de privacidad</a>.
      </p>
    </form>
  `,
})
export class SignupFormComponent {
  private readonly fb = inject(FormBuilder);
  private readonly signup = inject(SignupService);
  private readonly router = inject(Router);

  readonly sending = signal(false);
  readonly failure = signal('');

  readonly form = this.fb.nonNullable.group({
    role: ['client' as 'client' | 'provider', Validators.required],
    name: ['', [Validators.required, Validators.maxLength(255)]],
    email: ['', [Validators.required, Validators.email]],
    company_name: [''],
    password: ['', [Validators.required, Validators.minLength(8)]],
  });

  /** Un error solo se enseña cuando la persona ya paso por el campo, no mientras escribe. */
  touched(name: string): boolean {
    const c = this.form.get(name);
    return !!c && c.invalid && (c.dirty || c.touched);
  }

  submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.sending.set(true);
    this.failure.set('');
    const v = this.form.getRawValue();

    this.signup
      .register({
        name: v.name,
        email: v.email,
        password: v.password,
        // El endpoint valida `confirmed`, asi que el par viaja igual aunque la landing
        // solo pida la contraseña una vez: repetirla en una pagina de captacion cuesta
        // altas y no protege de nada que el usuario no pueda arreglar recuperandola.
        password_confirmation: v.password,
        role: v.role,
        company_name: v.company_name || undefined,
      })
      .subscribe({
        next: () => this.router.navigateByUrl('/gracias'),
        error: (e: { status?: number; error?: { message?: string } }) => {
          this.sending.set(false);
          // 422 con este payload es casi siempre el email repetido, y decirlo por su nombre
          // ahorra el bucle de "no sé qué he hecho mal" en el unico paso que importa.
          this.failure.set(
            e.status === 422
              ? 'Ese email ya tiene cuenta. Prueba a entrar en su lugar.'
              : e.error?.message || 'No hemos podido crear la cuenta. Inténtalo de nuevo en un momento.'
          );
        },
      });
  }
}
